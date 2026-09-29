<?php
require_once 'db.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT value FROM settings WHERE key = 'admin_email'");
$masterEmail = $stmt->fetchColumn();

$subAdmins = $pdo->query("SELECT email, created_at FROM admin_accounts ORDER BY created_at")->fetchAll();

echo json_encode([
    'master_admin_email' => $masterEmail ?: '(未設定・デフォルト luxewave.0002@gmail.com が使われます)',
    'sub_admin_accounts' => $subAdmins,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
