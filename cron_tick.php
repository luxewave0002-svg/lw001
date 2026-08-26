<?php
// 定期実行専用のエンドポイント。24時間ONのまま放置されている「技術発生」を自動的にOFFにし、
// 履歴には ended_reason = 'timeout' として記録する。
// 外部（GitHub Actionsのscheduleワークフロー）から一定間隔で呼び出す想定。

require_once 'db.php';
header('Content-Type: application/json');

$MAX_ACTIVE_HOURS = 24;

$stmt = $pdo->prepare("SELECT user_id, level, started_at FROM level_activation WHERE started_at IS NOT NULL AND started_at < datetime('now', ?)");
$stmt->execute(['-' . $MAX_ACTIVE_HOURS . ' hours']);
$staleRows = $stmt->fetchAll();

$closedCount = 0;
foreach ($staleRows as $row) {
    stopLevelActivation($pdo, $row['user_id'], $row['level'], 'timeout');
    writeLog($pdo, $row['user_id'], 'level_auto_off', "Level.{$row['level']} が{$MAX_ACTIVE_HOURS}時間ONのまま放置されたため自動的にOFFにしました。");
    $closedCount++;
}

echo json_encode(['checked' => count($staleRows), 'closed' => $closedCount, 'ranAt' => date('Y-m-d H:i:s')]);
