<?php
// SwitchBot デバイス一覧取得用スクリプト

// エラー出力設定
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// 共通設定ファイルを読み込む
require_once 'config.php';

$allDevices = [];

foreach ($devicesConfig as $index => $device) {
    $token = $device['token'];
    $secret = $device['secret'];

    // トークンが未設定の場合はスキップ
    if (strpos($token, '貼り付け') !== false) continue;

    // Open API v1.1 の署名生成プロセス
    $t = time() * 1000;
    $nonce = uniqid('', true);
    $data = $token . $t . $nonce;
    $sign = base64_encode(hash_hmac('sha256', $data, $secret, true));

    $url = "https://api.switch-bot.com/v1.1/devices";

    // cURLでSwitchBotサーバーへ送信
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
        $allDevices[] = ['account_index' => $index + 1, 'success' => false, 'error' => $err];
    } else {
        $allDevices[] = ['account_index' => $index + 1, 'response' => json_decode($response, true)];
    }
}

// 見やすくフォーマットして出力
echo json_encode($allDevices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);