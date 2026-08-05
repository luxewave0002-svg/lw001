<?php
// プラグの現在の状態（ON/OFF）を取得するAPI

require_once 'db.php';
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) && !isSessionValid($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'forced_logout' => true]);
    exit;
}

// 共通設定ファイルを読み込む
require_once 'config.php';

$targetDeviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : 'all';

$results = [];
$allSuccess = true;
$statuses = []; // deviceId => 'on' or 'off'

foreach ($devicesConfig as $index => $device) {
    $deviceId = $device['deviceId'];
    $token = $device['token'];
    $secret = $device['secret'];

    if (strpos($deviceId, '貼り付け') !== false) continue;
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
    } else {
        $responseData = json_decode($response, true);
        if (isset($responseData['statusCode']) && $responseData['statusCode'] === 100) {
            $statuses[$deviceId] = strtolower($responseData['body']['power']);
        } else {
            $allSuccess = false;
        }
    }
}

echo json_encode(['success' => $allSuccess, 'statuses' => $statuses]);
?>