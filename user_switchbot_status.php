<?php
require_once 'db.php';

header('Content-Type: application/json');

if (!isSessionValid($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Not logged in', 'forced_logout' => true]);
    exit;
}

$targetDeviceId = isset($_GET['device_id']) ? $_GET['device_id'] : 'all';

// ユーザーに紐づくデバイス情報を取得
$stmt = $pdo->prepare("SELECT * FROM devices WHERE user_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$_SESSION['user_id']]);
$devices = $stmt->fetchAll();

// SwitchBotのエラーメッセージを分かりやすく変換
function getSwitchBotErrorMessage($statusCode, $defaultMessage = '') {
    switch ($statusCode) {
        case 151: return 'デバイスが見つかりません。';
        case 152: return 'デバイスがオフラインです。';
        case 160: return '未対応のコマンドです。';
        case 161: return 'デバイスがオフラインです。';
        case 171: return 'ハブがオフラインです。';
        case 190: return 'デバイス内部エラー。';
        default: return $defaultMessage ? "エラー: " . $defaultMessage : '不明な通信エラー。';
    }
}

$results = [];
$allSuccess = true;
$statuses = []; // device_id => 'on' or 'off'

foreach ($devices as $device) {
    $deviceId = $device['device_id'];
    $token = $device['token'];
    $secret = $device['secret'];

    if ($targetDeviceId !== 'all' && $deviceId !== $targetDeviceId) continue;

    $t = time() * 1000;
    $nonce = uniqid('', true);
    $data = $token . $t . $nonce;
    $sign = base64_encode(hash_hmac('sha256', $data, $secret, true));

    $url = "https://api.switch-bot.com/v1.1/devices/{$deviceId}/status";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $token",
        "sign: $sign",
        "t: $t",
        "nonce: $nonce",
        "Content-Type: application/json; charset=utf8"
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        $allSuccess = false;
        $statuses[$deviceId] = 'error: ' . $err;
    } else {
        $responseData = json_decode($response, true);
        if (isset($responseData['statusCode']) && $responseData['statusCode'] === 100) {
            $statuses[$deviceId] = strtolower($responseData['body']['power']);
        } else {
            $allSuccess = false;
            $statuses[$deviceId] = 'error: ' . getSwitchBotErrorMessage($responseData['statusCode'] ?? 0, $responseData['message'] ?? '');
        }
    }
}

echo json_encode(['success' => $allSuccess, 'statuses' => $statuses]);
?>