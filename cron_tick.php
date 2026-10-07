<?php
// 定期実行専用のエンドポイント。24時間ONのまま放置されている「技術発生」を自動的にOFFにし、
// 履歴には ended_reason = 'timeout' として記録する（終了時刻は開始+24時間）。
// 外部（GitHub Actionsのscheduleワークフロー）から呼び出す想定。
// ※ GitHubのscheduleは実行間隔が保証されないため、主たる24時間制御はログイン時刻基準のリクエスト時チェック（db.php）。

header('Content-Type: application/json');

try {
    require_once 'db.php';

    $MAX_ACTIVE_SECONDS = 86400;
    $cutoff = date('Y-m-d H:i:s', time() - $MAX_ACTIVE_SECONDS);

    $stmt = $pdo->prepare("SELECT user_id, level, started_at FROM level_activation WHERE started_at IS NOT NULL AND started_at < ?");
    $stmt->execute([$cutoff]);
    $staleRows = $stmt->fetchAll();

    $closedCount = 0;
    foreach ($staleRows as $row) {
        stopLevelActivation($pdo, $row['user_id'], $row['level'], 'timeout', strtotime($row['started_at']) + $MAX_ACTIVE_SECONDS);
        writeLog($pdo, $row['user_id'], 'level_auto_off', "Level.{$row['level']} が24時間ONのまま放置されたため自動的にOFFにしました。");
        $closedCount++;
    }

    echo json_encode(['checked' => count($staleRows), 'closed' => $closedCount, 'ranAt' => date('Y-m-d H:i:s')]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
