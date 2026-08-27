<?php
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'not_logged_in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf_failed']);
    exit;
}

$level = filter_var($_POST['level'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
$action = $_POST['action'] ?? '';

if (!$level || !in_array($action, ['start', 'stop', 'status'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_params']);
    exit;
}

$userId = $_SESSION['user_id'];

// このLevelが解除済みか（Limitedレベルはダッシュボードでの CODE 入力、通常Levelはパスワード発行）を確認する
$unlocked = isLimitedLevel($level) ? isLimitedLevelUnlocked($pdo, $userId, $level) : isLevelUnlocked($pdo, $userId, $level);
if (!$unlocked) {
    http_response_code(403);
    echo json_encode(['error' => 'level_locked']);
    exit;
}

if ($action === 'start') {
    $startedAt = startLevelActivation($pdo, $userId, $level);
    writeLog($pdo, $userId, 'level_on', "Level.{$level} の技術発生をONにしました。");
} elseif ($action === 'stop') {
    stopLevelActivation($pdo, $userId, $level, 'manual');
    $startedAt = null;
    writeLog($pdo, $userId, 'level_off', "Level.{$level} の技術発生をOFFにしました。");
} else { // status
    $startedAt = getLevelActivation($pdo, $userId, $level);
}

echo json_encode([
    'level' => $level,
    'startedAt' => $startedAt,
    'startedAtMs' => $startedAt ? strtotime($startedAt) * 1000 : null,
    'serverNowMs' => time() * 1000,
]);
