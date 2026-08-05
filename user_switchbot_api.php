<?php
require_once 'db.php';

header('Content-Type: application/json');

if (!isSessionValid($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Not logged in', 'forced_logout' => true]);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    echo json_encode(['success' => false, 'error' => 'CSRF token validation failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$deviceId = $input['device_id'] ?? '';
$command = $input['action'] ?? 'turnOn';

if (!$deviceId) {
    echo json_encode(['success' => false, 'error' => 'Device ID is missing']);
    exit;
}

// ログイン中のユーザーが所有しているデバイスか確認し、TokenとSecretをDBから取得
$stmt = $pdo->prepare("SELECT * FROM devices WHERE device_id = ? AND user_id = ?");
$stmt->execute([$deviceId, $_SESSION['user_id']]);
$device = $stmt->fetch();

if (!$device) {
    echo json_encode(['success' => false, 'error' => 'Device not found or access denied']);
    exit;
}

$token = $device['token'];
$secret = $device['secret'];

// 操作ログを記録
writeLog($pdo, $_SESSION['user_id'], 'user_switchbot_action', "個別デバイス({$device['device_name']})に {$command} コマンドを送信しました。");

// SwitchBotのエラーメッセージを分かりやすく変換
function getSwitchBotErrorMessage($statusCode, $defaultMessage = '') {
    switch ($statusCode) {
        case 151: return 'デバイスが見つかりません。IDを確認してください。';
        case 152: return 'デバイスがオフラインです。電源やWi-Fiを確認してください。';
        case 160: return 'このコマンドはサポートされていません。';
        case 161: return 'デバイスがオフラインです。電源やWi-Fiを確認してください。';
        case 171: return 'ハブがオフラインです。';
        case 190: return 'デバイスの内部エラーが発生しました。';
        default: return $defaultMessage ? "エラー: " . $defaultMessage : '不明な通信エラーが発生しました。';
    }
}

// --- SwitchBot API への送信処理 ---
$t = time() * 1000;
$nonce = uniqid('', true);
$data = $token . $t . $nonce;
$sign = base64_encode(hash_hmac('sha256', $data, $secret, true));

$url = "https://api.switch-bot.com/v1.1/devices/{$deviceId}/commands";
$body = json_encode([
    "command" => $command,
    "parameter" => "default",
    "commandType" => "command"
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: $token",
    "sign: $sign",
    "t: $t",
    "nonce: $nonce",
    "Content-Type: application/json; charset=utf8",
    "Content-Length: " . strlen($body)
]);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['success' => false, 'error' => '通信エラー: ' . $err]);
} else {
    $responseData = json_decode($response, true);
    if (isset($responseData['statusCode']) && $responseData['statusCode'] === 100) {
        echo json_encode(['success' => true]);
    } else {
        $errorMsg = getSwitchBotErrorMessage($responseData['statusCode'] ?? 0, $responseData['message'] ?? '');
        echo json_encode(['success' => false, 'error' => $errorMsg]);
    }
}
?>