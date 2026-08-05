<?php
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isSessionValid($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Not logged in', 'forced_logout' => true]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$secret = $input['secret'] ?? '';

if (!$token || !$secret) {
    echo json_encode(['success' => false, 'error' => 'Token and Secret are required']);
    exit;
}

$t = time() * 1000;
$nonce = uniqid('', true);
$data = $token . $t . $nonce;
$sign = base64_encode(hash_hmac('sha256', $data, $secret, true));

$url = "https://api.switch-bot.com/v1.1/devices";

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
    echo json_encode(['success' => false, 'error' => 'CURL Error: ' . $err]);
} else {
    $resData = json_decode($response, true);
    if (isset($resData['statusCode']) && $resData['statusCode'] === 100) {
        $devices = [];
        if (isset($resData['body']['deviceList'])) {
            foreach ($resData['body']['deviceList'] as $device) {
                // デバイスタイプに「Plug」が含まれるものだけを抽出
                if (stripos($device['deviceType'], 'Plug') !== false) {
                    $devices[] = ['deviceId' => $device['deviceId'], 'deviceName' => $device['deviceName'], 'deviceType' => $device['deviceType']];
                }
            }
        }
        echo json_encode(['success' => true, 'devices' => $devices]);
    } else {
        echo json_encode(['success' => false, 'error' => $resData['message'] ?? 'API Error']);
    }
}
?>