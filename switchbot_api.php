<?php
require_once 'db.php';
// エラー出力設定（JSONを壊さないために非表示に）
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) && !isSessionValid($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'forced_logout' => true]);
    exit;
}

// 共通設定ファイルを読み込む
require_once 'config.php';

// JSからのデータを受け取る
$input = json_decode(file_get_contents('php://input'), true);
$command = isset($input['action']) ? $input['action'] : 'turnOn';
$targetDeviceId = isset($input['deviceId']) ? $input['deviceId'] : 'all';

// リクエストボディの作成
$body = json_encode([
    "command" => $command,
    "parameter" => "default",
    "commandType" => "command"
]);

// 操作ログを記録 (ゲスト操作として記録)
$targetDesc = ($targetDeviceId === 'all') ? "全体プラグ" : "プラグ({$targetDeviceId})";
writeLog($pdo, null, 'switchbot_action', "{$targetDesc} に {$command} コマンドを送信しました。");

$results = [];
$allSuccess = true;

// 登録されたすべてのデバイスに順番にコマンドを送信
foreach ($devicesConfig as $device) {
    $deviceId = $device['deviceId'];
    $token = $device['token'];
    $secret = $device['secret'];

    // IDが未設定（プレースホルダーのまま）の場合はスキップ
    if (strpos($deviceId, '貼り付け') !== false) continue;

    // 指定されたデバイスID以外はスキップ（allの場合はすべて実行）
    if ($targetDeviceId !== 'all' && $deviceId !== $targetDeviceId) continue;

    // リクエストごとに署名を生成（都度作り直す必要があります）
    $t = time() * 1000;
    $nonce = uniqid('', true);
    $data = $token . $t . $nonce;
    $sign = base64_encode(hash_hmac('sha256', $data, $secret, true));

    $url = "https://api.switch-bot.com/v1.1/devices/{$deviceId}/commands";

    // cURLでSwitchBotサーバーへ送信
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
        $allSuccess = false;
        $results[] = ['deviceId' => $deviceId, 'success' => false, 'error' => $err];
    } else {
        $responseData = json_decode($response, true);
        if (isset($responseData['statusCode']) && $responseData['statusCode'] === 100) {
            $results[] = ['deviceId' => $deviceId, 'success' => true];
        } else {
            $allSuccess = false;
            $results[] = ['deviceId' => $deviceId, 'success' => false, 'error' => $responseData];
        }
    }
}

echo json_encode(['success' => $allSuccess, 'results' => $results]);
?>
