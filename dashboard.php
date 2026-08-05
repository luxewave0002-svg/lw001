<?php
require_once 'db.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// PC版表示の強制フラグをセッションに保存
if (isset($_GET['force_pc'])) {
    if ($_GET['force_pc'] == '1') {
        $_SESSION['force_pc'] = true;
    } elseif ($_GET['force_pc'] == '0') {
        unset($_SESSION['force_pc']);
    }
}

// スマホでもPCと同じダッシュボードを表示する（簡易版のmobile_dashboard.phpへは振り分けない）

$csrfToken = generateCsrfToken();

$userId = $_SESSION['user_id'];
$message = '';
$message_class = ''; // 'success' or 'error'

// 現在有効な招待コード（未使用かつ無効化されていないもの）を取得
$myInviteStmt = $pdo->prepare("SELECT code, created_at FROM invite_codes WHERE issued_to_user_id = ? AND used_by_user_id IS NULL AND revoked_at IS NULL ORDER BY id DESC LIMIT 1");
$myInviteStmt->execute([$userId]);
$myInviteCode = $myInviteStmt->fetch();

// 招待コードの発行履歴（使用済み・無効化したものも含めて新しい順）
$myInviteHistoryStmt = $pdo->prepare("SELECT code, created_at, used_at, used_by_user_id, revoked_at FROM invite_codes WHERE issued_to_user_id = ? ORDER BY id DESC");
$myInviteHistoryStmt->execute([$userId]);
$myInviteHistory = $myInviteHistoryStmt->fetchAll();

// 自分のLevelパスワード（有効な発行済み分のみ。削除済みは除外）を取得
$myLevelPwStmt = $pdo->prepare("SELECT level, password FROM level_passwords WHERE user_id = ? AND revoked_at IS NULL AND password IS NOT NULL ORDER BY level ASC");
$myLevelPwStmt->execute([$userId]);
$myLevelPasswords = $myLevelPwStmt->fetchAll();

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// CSRF検証 (すべてのPOSTリクエスト)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die('CSRF token validation failed. 不正なリクエストです。');
    }
}

// 招待コードの再発行処理（古い未使用コードは無効化して履歴に残し、新しいコードを1つ発行する）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reissue_invite_code') {
    try {
        $newInviteCode = generateInviteCode();
        $now = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        // 未使用の古いコードは削除せず無効化して履歴に残す
        $revokeStmt = $pdo->prepare("UPDATE invite_codes SET revoked_at = ? WHERE issued_to_user_id = ? AND used_by_user_id IS NULL AND revoked_at IS NULL");
        $revokeStmt->execute([$now, $userId]);
        $revokedCount = $revokeStmt->rowCount();

        $pdo->prepare("INSERT INTO invite_codes (code, issued_to_user_id, created_at) VALUES (?, ?, ?)")
            ->execute([$newInviteCode, $userId, $now]);

        $pdo->commit();

        $message = $revokedCount > 0
            ? "招待コードを再発行しました。新しいコード: 「 {$newInviteCode} 」（以前の未使用コードは無効になりました。履歴には残ります）"
            : "新しい招待コードを発行しました: 「 {$newInviteCode} 」";

        writeLog($pdo, $userId, 'invite_code_reissue', "招待コードを再発行: {$newInviteCode}");
        $message_class = 'success';

        // 表示用に取得し直す
        $myInviteStmt->closeCursor();
        $myInviteStmt->execute([$userId]);
        $myInviteCode = $myInviteStmt->fetch();
        $myInviteHistoryStmt->closeCursor();
        $myInviteHistoryStmt->execute([$userId]);
        $myInviteHistory = $myInviteHistoryStmt->fetchAll();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "招待コードの再発行に失敗しました。";
        $message_class = 'error';
    }
}

// デバイス名の変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_device_name') {
    $editDeviceId = $_POST['device_id'] ?? '';
    $newDeviceName = $_POST['device_name'] ?? '';
    
    if ($editDeviceId && $newDeviceName) {
        $stmt = $pdo->prepare("UPDATE devices SET device_name = ? WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$newDeviceName, $editDeviceId, $userId])) {
            $message = "デバイス名を更新しました。";
            $message_class = 'success';
        } else {
            $message = "デバイス名の更新に失敗しました。";
            $message_class = 'error';
        }
    }
}

// デバイス情報（Token等）の変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_device_info') {
    $editDeviceId = $_POST['device_id'] ?? '';
    $newDeviceIdStr = $_POST['new_device_id'] ?? '';
    $newToken = $_POST['new_token'] ?? '';
    $newSecret = $_POST['new_secret'] ?? '';
    $newIcon = $_POST['new_icon'] ?? '🔌';
    $newColor = $_POST['new_color'] ?? 'text-gray-100';

    if ($editDeviceId && $newDeviceIdStr && $newToken && $newSecret) {
        $stmt = $pdo->prepare("UPDATE devices SET device_id = ?, token = ?, secret = ?, icon = ?, color = ? WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$newDeviceIdStr, $newToken, $newSecret, $newIcon, $newColor, $editDeviceId, $userId])) {
            $message = "デバイス情報を更新しました。";
            $message_class = 'success';
        } else {
            $message = "デバイス情報の更新に失敗しました。";
            $message_class = 'error';
        }
    } else {
        $message = "すべての項目を入力してください。";
        $message_class = 'error';
    }
}

// デバイスの削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_device') {
    $deleteId = $_POST['delete_id'] ?? '';
    
    if ($deleteId) {
        $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$deleteId, $userId])) {
            $message = "デバイスを削除しました。";
            $message_class = 'success';
        }
    }
}

// デバイスの追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_device') {
    $deviceName = $_POST['device_name'] ?? '';
    $deviceId = $_POST['device_id'] ?? '';
    $token = $_POST['token'] ?? '';
    $secret = $_POST['secret'] ?? '';
    $icon = $_POST['icon'] ?? '🔌';
    $color = $_POST['color'] ?? 'text-gray-100';

    if ($deviceName && $deviceId && $token && $secret) {
        $stmt = $pdo->prepare("INSERT INTO devices (user_id, device_name, device_id, token, secret, icon, color) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$userId, $deviceName, $deviceId, $token, $secret, $icon, $color])) {
            $message = "デバイスを追加しました！";
            $message_class = 'success';
        } else {
            $message = "追加に失敗しました。";
            $message_class = 'error';
        }
    }
}

// メールアドレス変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_email') {
    $newEmail = $_POST['new_email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($newEmail && $password) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            try {
                $updateStmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                if ($updateStmt->execute([$newEmail, $userId])) {
                    $_SESSION['email'] = $newEmail; // セッションのメールアドレスも更新
                    $message = "メールアドレスが正常に変更されました。";
                    $message_class = 'success';
                }
            } catch (PDOException $e) {
                // 23000 は「一意性制約違反（UNIQUE）」つまり重複のエラーコードです
                if ($e->getCode() == 23000) {
                    $message = "このメールアドレスは既に登録されています。";
                    $message_class = 'error';
                } else {
                    $message = "メールアドレスの変更中にエラーが発生しました。";
                    $message_class = 'error';
                }
            }
        } else {
            $message = "パスワードが正しくありません。";
            $message_class = 'error';
        }
    } else {
        $message = "すべてのフィールドを入力してください。";
        $message_class = 'error';
    }
}

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        $message = "新しいパスワードが一致しません。";
        $message_class = 'error';
    } elseif ($currentPassword && $newPassword) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($currentPassword, $user['password'])) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($updateStmt->execute([$newHash, $userId])) {
                $message = "パスワードが正常に変更されました。";
                $message_class = 'success';
            }
        } else {
            $message = "現在のパスワードが正しくありません。";
            $message_class = 'error';
        }
    } else {
        $message = "すべてのフィールドを入力してください。";
        $message_class = 'error';
    }
}

// アカウント削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    $password = $_POST['password'] ?? '';

    if ($password) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // トランザクションを開始してデータの整合性を保つ
            $pdo->beginTransaction();
            try {
                // 1. ユーザーに紐づくデバイスを削除
                $deleteDevicesStmt = $pdo->prepare("DELETE FROM devices WHERE user_id = ?");
                $deleteDevicesStmt->execute([$userId]);

                // 2. ユーザー自身を削除
                $deleteUserStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $deleteUserStmt->execute([$userId]);

                $pdo->commit();

                session_destroy();
                header("Location: login.php?deleted=true");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "アカウントの削除中にエラーが発生しました。";
                $message_class = 'error';
            }
        } else {
            $message = "パスワードが正しくありません。アカウントを削除できませんでした。";
            $message_class = 'error';
        }
    } else {
        $message = "削除するにはパスワードの入力が必要です。";
        $message_class = 'error';
    }
}

// デバイスの並び替え処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['move_up', 'move_down'])) {
    $moveId = $_POST['device_id'] ?? '';
    $direction = $_POST['action'];

    if ($moveId) {
        // 現在のデバイス一覧を取得して順番を整える
        $stmt = $pdo->prepare("SELECT id, sort_order FROM devices WHERE user_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$userId]);
        $currentDevices = $stmt->fetchAll();

        foreach ($currentDevices as $index => $device) {
            $currentDevices[$index]['sort_order'] = $index;
        }

        $targetIndex = -1;
        foreach ($currentDevices as $index => $device) {
            if ($device['id'] == $moveId) {
                $targetIndex = $index;
                break;
            }
        }

        if ($targetIndex !== -1) {
            if ($direction === 'move_up' && $targetIndex > 0) {
                $temp = $currentDevices[$targetIndex - 1]['sort_order'];
                $currentDevices[$targetIndex - 1]['sort_order'] = $currentDevices[$targetIndex]['sort_order'];
                $currentDevices[$targetIndex]['sort_order'] = $temp;
            } elseif ($direction === 'move_down' && $targetIndex < count($currentDevices) - 1) {
                $temp = $currentDevices[$targetIndex + 1]['sort_order'];
                $currentDevices[$targetIndex + 1]['sort_order'] = $currentDevices[$targetIndex]['sort_order'];
                $currentDevices[$targetIndex]['sort_order'] = $temp;
            }

            // データベースの並び順を更新
            $pdo->beginTransaction();
            try {
                $updateStmt = $pdo->prepare("UPDATE devices SET sort_order = ? WHERE id = ?");
                foreach ($currentDevices as $device) {
                    $updateStmt->execute([$device['sort_order'], $device['id']]);
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    }
}

// 登録済みデバイス一覧を取得
$stmt = $pdo->prepare("SELECT * FROM devices WHERE user_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$userId]);
$devices = $stmt->fetchAll();

// 最後に登録したTokenとSecretを取得
$lastToken = '';
$lastSecret = '';
if (!empty($devices)) {
    $maxIdDevice = null;
    $maxId = -1;
    foreach ($devices as $device) {
        if ($device['id'] > $maxId) {
            $maxId = $device['id'];
            $maxIdDevice = $device;
        }
    }
    if ($maxIdDevice) {
        $lastToken = $maxIdDevice['token'];
        $lastSecret = $maxIdDevice['secret'];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - LUXE WAVE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Chromeの自動入力(鍵)アイコンと目のアイコンが重なって、枠からはみ出て見える現象を防ぐ */
        input::-webkit-credentials-auto-fill-button,
        input::-webkit-contacts-auto-fill-button {
            visibility: hidden;
            display: none !important;
            pointer-events: none;
            position: absolute;
            right: 0;
        }
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }
        /* Toggle Switch CSS */
        .toggle-checkbox:checked { right: 0; border-color: #ffffff; }
        .toggle-checkbox:checked + .toggle-label { background-color: #ffffff; }
        .toggle-checkbox { right: 0; z-index: 1; border-color: #4b5563; transition: all 0.3s; }
        .toggle-label { width: 3rem; height: 1.5rem; background-color: #4b5563; border-radius: 9999px; transition: all 0.3s; }
        .toggle-dot { top: 0.125rem; left: 0.125rem; width: 1.25rem; height: 1.25rem; background-color: #1f2937; border-radius: 50%; transition: all 0.3s; }
        .toggle-checkbox:checked ~ .toggle-dot { transform: translateX(1.5rem); background-color: #000000; }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black min-h-screen text-white font-light p-6 relative z-0 overflow-x-hidden">

    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <div class="max-w-4xl mx-auto px-2 sm:px-0">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 sm:mb-10 border-b border-white/20 pb-4 mt-8 gap-4 sm:gap-0">
            <h1 class="text-2xl tracking-widest uppercase">My Dashboard</h1>
            <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                <span class="text-sm text-gray-300 w-full sm:w-auto mb-1 sm:mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['email']); ?></span>
                <a href="index.php" class="text-xs border border-white/30 px-3 py-1 rounded hover:bg-white/10 transition">HOME</a>
                <?php if (isMobile()): ?>
                <a href="?force_pc=0" class="text-xs border border-white/30 px-3 py-1 rounded hover:bg-white/10 transition">スマホ版に戻る</a>
                <?php endif; ?>
                <a href="?logout=1" class="text-xs border border-white/30 px-3 py-1 rounded hover:bg-white/10 transition">Logout</a>
            </div>
        </div>

        <?php if($message): ?>
            <?php
            $base_class = 'px-4 py-2 rounded mb-6 text-sm';
            $class = ($message_class === 'error')
                ? 'bg-red-900/50 border border-red-500 text-red-200'
                : 'bg-green-900/50 border border-green-500 text-green-200';
            ?>
            <div class="<?php echo $base_class . ' ' . $class; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- 招待コード -->
        <div class="bg-white/5 border border-white/10 rounded-lg p-4 mb-4">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm text-gray-300 tracking-wider">Your Invite Code</span>
                <?php if ($myInviteCode): ?>
                    <span class="font-mono text-white bg-black/40 px-3 py-1 rounded"><?php echo htmlspecialchars($myInviteCode['code']); ?></span>
                    <span class="text-xs text-green-500/70">（未使用）</span>
                <?php else: ?>
                    <span class="text-xs text-gray-500">有効なコードがありません。再発行してください。</span>
                <?php endif; ?>

                <form method="POST" class="ml-auto" onsubmit="return confirm('招待コードを再発行しますか？<?php echo $myInviteCode ? '\n現在の未使用コードは使えなくなります（履歴には残ります）。' : ''; ?>');">
                    <input type="hidden" name="action" value="reissue_invite_code">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <button type="submit" class="text-xs bg-white/10 hover:bg-white/20 border border-white/20 px-3 py-1.5 rounded transition-colors text-gray-300 hover:text-white tracking-wider">再発行</button>
                </form>
            </div>

            <?php if (!empty($myInviteHistory)): ?>
                <details class="mt-3 border-t border-white/10 pt-3">
                    <summary class="text-xs text-gray-400 tracking-wider cursor-pointer hover:text-gray-200">発行履歴（<?php echo count($myInviteHistory); ?>件）</summary>
                    <div class="mt-3 flex flex-col gap-2">
                        <?php foreach ($myInviteHistory as $inviteRow): ?>
                            <?php
                            if ($inviteRow['used_by_user_id']) {
                                $inviteState = '使用済み';
                                $inviteStateClass = 'text-gray-500';
                                $inviteStateDate = $inviteRow['used_at'];
                            } elseif ($inviteRow['revoked_at']) {
                                $inviteState = '無効（再発行済み）';
                                $inviteStateClass = 'text-red-300/70';
                                $inviteStateDate = $inviteRow['revoked_at'];
                            } else {
                                $inviteState = '有効';
                                $inviteStateClass = 'text-green-500/70';
                                $inviteStateDate = '';
                            }
                            ?>
                            <div class="flex flex-wrap items-center gap-3 text-xs bg-black/20 px-3 py-2 rounded">
                                <span class="font-mono <?php echo $inviteRow['used_by_user_id'] || $inviteRow['revoked_at'] ? 'text-gray-500 line-through' : 'text-white'; ?>"><?php echo htmlspecialchars($inviteRow['code']); ?></span>
                                <span class="<?php echo $inviteStateClass; ?>"><?php echo $inviteState; ?></span>
                                <span class="text-gray-600 ml-auto">
                                    発行 <?php echo htmlspecialchars((string)$inviteRow['created_at']); ?>
                                    <?php if ($inviteStateDate): ?>
                                        / <?php echo $inviteState === '使用済み' ? '使用' : '無効化'; ?> <?php echo htmlspecialchars((string)$inviteStateDate); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>

        <!-- Levelパスワード（発行済み分） -->
        <div class="bg-white/5 border border-white/10 rounded-lg p-4 mb-8">
            <span class="text-sm text-gray-300 tracking-wider">Level Passwords</span>
            <div class="flex flex-wrap items-center gap-3 mt-3">
                <?php if (empty($myLevelPasswords)): ?>
                    <span class="text-xs text-gray-500">まだ発行されていません。</span>
                <?php else: ?>
                    <?php foreach ($myLevelPasswords as $lvlPw): ?>
                        <div class="flex items-center gap-2 bg-black/20 px-3 py-1.5 rounded-lg">
                            <span class="text-xs text-gray-400">Level.<?php echo (int)$lvlPw['level']; ?></span>
                            <span class="font-mono text-white bg-black/40 px-2 py-1 rounded text-sm"><?php echo htmlspecialchars($lvlPw['password']); ?></span>
                            <button type="button" onclick="copyLevelPassword(this, '<?php echo htmlspecialchars($lvlPw['password'], ENT_QUOTES); ?>')" aria-label="パスワードをコピー" class="text-gray-400 hover:text-white transition-colors p-1 focus:outline-none">
                                <svg class="copy-icon w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                </svg>
                                <svg class="check-icon w-4 h-4 hidden text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 個人のプラグ一覧 -->
        <h2 class="text-xl mb-4 tracking-wider">Your Devices</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
            <?php foreach ($devices as $device): ?>
                <div id="device_card_<?php echo $device['id']; ?>" class="bg-black/30 border border-white/10 p-5 sm:p-6 rounded-xl backdrop-blur-sm relative transition-colors duration-500">
                    
                    <!-- デバイス名編集フォーム -->
                    <form method="POST" class="mb-4 flex items-center gap-2 pr-36 sm:pr-48">
                        <input type="hidden" name="action" value="edit_device_name">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                        <span class="text-xl"><?php echo htmlspecialchars($device['icon'] ?? '🔌'); ?></span>
                        <input type="text" name="device_name" value="<?php echo htmlspecialchars($device['device_name']); ?>" required class="bg-transparent border-b border-transparent focus:border-white/30 focus:bg-white/5 px-1 py-0.5 text-base sm:text-lg outline-none w-full transition-all placeholder-gray-500 truncate <?php echo htmlspecialchars($device['color'] ?? 'text-white'); ?>">
                        <button type="submit" class="text-[10px] bg-white/10 hover:bg-white/20 border border-white/20 px-2 py-1 rounded tracking-widest transition-colors whitespace-nowrap text-gray-300 hover:text-white">RENAME</button>
                    </form>
                    <div class="mb-3 text-xs text-yellow-400/80 tracking-widest font-mono pl-1">
                        Lv. <?php echo htmlspecialchars($device['level'] ?? 1); ?>
                        <?php if (!empty($device['level_updated_at'])): ?>
                            <span class="text-[10px] text-gray-500 ml-2">Updated: <?php echo htmlspecialchars(date('Y.m.d H:i', strtotime($device['level_updated_at']))); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- アクションボタン群 -->
                    <div class="absolute top-5 right-5 sm:top-6 sm:right-6 flex items-center gap-2 sm:gap-3">
                        <div class="flex items-center gap-0.5 sm:gap-1 bg-white/5 border border-white/10 rounded px-1">
                            <form method="POST" class="m-0 p-0">
                                <input type="hidden" name="action" value="move_up">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                <button type="submit" class="text-[10px] px-1 py-1 text-gray-400 hover:text-white transition-colors" title="上へ">▲</button>
                            </form>
                            <form method="POST" class="m-0 p-0 border-l border-white/10">
                                <input type="hidden" name="action" value="move_down">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                <button type="submit" class="text-[10px] px-1 py-1 text-gray-400 hover:text-white transition-colors" title="下へ">▼</button>
                            </form>
                        </div>
                        <button type="button" onclick="toggleEditForm('edit_form_<?php echo $device['id']; ?>')" class="text-xs text-blue-400/80 hover:text-blue-300 transition-colors tracking-widest">EDIT</button>
                        <form method="POST" onsubmit="return confirm('本当にこのデバイスを削除しますか？');" class="m-0 p-0">
                            <input type="hidden" name="action" value="delete_device">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="delete_id" value="<?php echo $device['id']; ?>">
                            <button type="submit" class="text-xs text-red-500/70 hover:text-red-400 transition-colors tracking-widest">DELETE</button>
                        </form>
                    </div>

                    <div class="flex items-center justify-between bg-black/40 p-4 rounded-lg">
                        <span class="text-xs text-gray-400">Power Control</span>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-500">OFF</span>
                            <div class="relative inline-block w-12 h-6 align-middle select-none">
                                <input type="checkbox" id="toggle_<?php echo $device['id']; ?>" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-transparent border-2 appearance-none cursor-pointer outline-none" 
                                    onchange="toggleMyDevice('<?php echo $device['device_id']; ?>', this.checked, 'status_<?php echo $device['id']; ?>')"/>
                                <label for="toggle_<?php echo $device['id']; ?>" class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer border border-white/30"></label>
                                <div class="toggle-dot absolute block w-5 h-5 rounded-full shadow inset-y-0 left-0 mt-0.5 ml-0.5 pointer-events-none"></div>
                            </div>
                            <span class="text-xs text-gray-500">ON</span>
                        </div>
                    </div>
                    <p id="status_<?php echo $device['id']; ?>" class="mt-3 text-right text-[10px] text-gray-500">Ready</p>

                    <!-- デバイス情報編集フォーム（アコーディオン） -->
                    <div id="edit_form_<?php echo $device['id']; ?>" class="hidden mt-4 pt-4 border-t border-white/10">
                        <form method="POST" class="flex flex-col gap-3">
                            <input type="hidden" name="action" value="edit_device_info">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                            
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-1">Device ID</label>
                                <input type="text" name="new_device_id" value="<?php echo htmlspecialchars($device['device_id']); ?>" required class="bg-white/5 border border-white/20 rounded px-3 py-1.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-1">Token</label>
                                <input type="text" name="new_token" value="<?php echo htmlspecialchars($device['token']); ?>" required class="bg-white/5 border border-white/20 rounded px-3 py-1.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-1">Secret</label>
                                <input type="text" name="new_secret" value="<?php echo htmlspecialchars($device['secret']); ?>" required class="bg-white/5 border border-white/20 rounded px-3 py-1.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-1">Icon (絵文字など)</label>
                                <input type="text" name="new_icon" value="<?php echo htmlspecialchars($device['icon'] ?? '🔌'); ?>" required class="bg-white/5 border border-white/20 rounded px-3 py-1.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-400 block mb-1">Color</label>
                                <select name="new_color" class="bg-white/5 border border-white/20 rounded px-3 py-1.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                                    <?php 
                                    $colors = [
                                        'text-gray-100' => 'ホワイト',
                                        'text-red-400' => 'レッド',
                                        'text-blue-400' => 'ブルー',
                                        'text-green-400' => 'グリーン',
                                        'text-yellow-400' => 'イエロー',
                                        'text-purple-400' => 'パープル',
                                        'text-pink-400' => 'ピンク'
                                    ];
                                    foreach($colors as $val => $name): 
                                    ?>
                                        <option value="<?php echo $val; ?>" <?php echo (($device['color'] ?? 'text-gray-100') === $val) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="flex justify-end mt-2 items-center">
                                <button type="button" onclick="toggleEditForm('edit_form_<?php echo $device['id']; ?>')" class="text-xs text-gray-400 hover:text-white mr-4 transition-colors">CANCEL</button>
                                <button type="submit" class="text-xs bg-white/10 hover:bg-white/20 border border-white/20 px-4 py-1.5 rounded tracking-widest transition-colors text-white">UPDATE INFO</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if(empty($devices)): ?>
                <p class="text-sm text-gray-400">登録されているデバイスがありません。</p>
            <?php endif; ?>
        </div>

        <!-- デバイス追加フォーム -->
        <h2 class="text-xl mb-4 tracking-wider">Add New Device</h2>
        <div class="bg-black/30 border border-white/10 p-6 rounded-xl backdrop-blur-sm flex flex-col gap-4">
            
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-blue-900/20 border border-blue-500/30 p-3 rounded-lg gap-3 sm:gap-0 my-1">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-400 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <span class="text-xs text-blue-100">Token と Secret の調べ方がわからない場合はこちら</span>
                </div>
                <a href="how_to_get_token.php" target="_blank" class="flex items-center justify-center gap-1 text-xs bg-blue-600/40 hover:bg-blue-600/70 text-white border border-blue-400/50 px-4 py-2 rounded transition-colors tracking-wider whitespace-nowrap w-full sm:w-auto shadow-lg shadow-blue-900/20">
                    <span>調べ方を見る</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </a>
            </div>

            <!-- Step 1: Token と Secret を入力してデバイス一覧を取得 -->
            <div id="fetch_devices_section" class="flex flex-col gap-4">
                <p class="text-xs text-gray-400">Token と Secret を入力して、連携可能なデバイスを検索します。</p>
                
                <?php if ($lastToken && $lastSecret): ?>
                <button type="button" onclick="fetchDevicesWithSavedCredentials('<?php echo htmlspecialchars($lastToken, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lastSecret, ENT_QUOTES); ?>')" class="bg-indigo-600/80 hover:bg-indigo-600 text-white py-2.5 rounded tracking-widest text-sm border border-indigo-400/50 w-full transition-colors focus:outline-none flex items-center justify-center gap-2 shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    前回登録したアカウントからデバイスを検索
                </button>
                <div class="flex items-center text-xs text-gray-500 my-1">
                    <div class="flex-grow border-t border-white/10"></div>
                    <span class="px-3 tracking-wider">OR</span>
                    <div class="flex-grow border-t border-white/10"></div>
                </div>
                <?php endif; ?>

                <input type="text" id="api_token" placeholder="Token" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <input type="text" id="api_secret" placeholder="Secret" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <button type="button" id="btn_fetch_devices" onclick="fetchDevicesFromAPI()" class="bg-blue-600/80 hover:bg-blue-600 text-white py-2 rounded tracking-widest text-sm border border-blue-400/50 w-full sm:w-64 transition-colors focus:outline-none">
                    <?php echo ($lastToken && $lastSecret) ? '新しいアカウントで検索' : 'デバイス一覧を検索する'; ?>
                </button>
                <p id="fetch_status" class="text-xs hidden"></p>
            </div>

            <!-- Step 2: 取得したデバイスを選択して追加 -->
            <form method="POST" id="add_device_form" class="hidden flex-col gap-4 border-t border-white/20 pt-4 mt-2">
                <input type="hidden" name="action" value="add_device">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="token" id="hidden_token">
                <input type="hidden" name="secret" id="hidden_secret">

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] text-gray-400">登録するデバイスを選択</label>
                    <select id="device_selector" onchange="onDeviceSelected()" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-full focus:outline-none focus:border-white/50">
                        <option value="">選択してください</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] text-gray-400">デバイス名 (自由に変更可能)</label>
                    <input type="text" name="device_name" id="input_device_name" placeholder="デバイス名" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-full focus:outline-none focus:border-white/50">
                </div>
                
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] text-gray-400">Device ID (自動入力)</label>
                    <input type="text" name="device_id" id="input_device_id" placeholder="Device ID" required readonly class="bg-black/50 border border-white/10 text-gray-400 rounded px-4 py-2 text-sm w-full cursor-not-allowed focus:outline-none">
                </div>
                
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] text-gray-400">アイコン / カラー</label>
                    <div class="flex gap-2">
                        <input type="text" name="icon" value="🔌" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-1/4 text-center focus:outline-none focus:border-white/50">
                        <select name="color" class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-3/4 focus:outline-none focus:border-white/50">
                            <option value="text-gray-100">ホワイト</option>
                            <option value="text-red-400">レッド</option>
                            <option value="text-blue-400">ブルー</option>
                            <option value="text-green-400">グリーン</option>
                            <option value="text-yellow-400">イエロー</option>
                            <option value="text-purple-400">パープル</option>
                            <option value="text-pink-400">ピンク</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex items-center gap-4 mt-2">
                    <button type="submit" class="bg-white/10 hover:bg-white/20 text-white px-6 py-2 rounded tracking-widest text-sm border border-white/30 transition-colors w-full sm:w-auto">ADD DEVICE</button>
                    <button type="button" onclick="resetFetchForm()" class="text-xs text-gray-400 hover:text-white transition-colors">やり直す</button>
                </div>
            </form>

            <div class="text-right mt-2" id="manual_input_link">
                <button type="button" onclick="showManualInput()" class="text-[10px] text-gray-500 hover:text-gray-300 underline tracking-wider focus:outline-none">自動取得できない場合は手動で入力する</button>
            </div>
            
            <!-- 手動入力用フォーム (初期非表示) -->
            <form method="POST" id="manual_add_device_form" class="hidden flex-col gap-4 border-t border-white/20 pt-4 mt-2">
                <p class="text-xs text-gray-400">手動でデバイス情報を入力して登録します。</p>
                <input type="hidden" name="action" value="add_device">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="text" name="device_name" placeholder="デバイス名 (例: 自宅のプラグ)" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <input type="text" name="device_id" placeholder="Device ID (例: F85B1B276B1A)" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <input type="text" name="token" placeholder="Token" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <input type="text" name="secret" placeholder="Secret" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <div class="flex gap-2">
                    <input type="text" name="icon" value="🔌" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-1/4 text-center focus:outline-none focus:border-white/50">
                    <select name="color" class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white w-3/4 focus:outline-none focus:border-white/50">
                        <option value="text-gray-100">ホワイト</option>
                        <option value="text-red-400">レッド</option>
                        <option value="text-blue-400">ブルー</option>
                        <option value="text-green-400">グリーン</option>
                        <option value="text-yellow-400">イエロー</option>
                        <option value="text-purple-400">パープル</option>
                        <option value="text-pink-400">ピンク</option>
                    </select>
                </div>
                
                <div class="flex items-center gap-4 mt-2">
                    <button type="submit" class="bg-white/10 hover:bg-white/20 text-white px-6 py-2 rounded tracking-widest text-sm border border-white/30 transition-colors w-full sm:w-auto">ADD DEVICE (手動)</button>
                    <button type="button" onclick="resetFetchForm()" class="text-xs text-gray-400 hover:text-white transition-colors">自動検索に戻る</button>
                </div>
            </form>
        </div>

        <!-- メールアドレス変更フォーム -->
        <h2 class="text-xl mb-4 mt-12 tracking-wider">Change Email</h2>
        <form method="POST" class="bg-black/30 border border-white/10 p-6 rounded-xl backdrop-blur-sm flex flex-col gap-4">
            <input type="hidden" name="action" value="change_email">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <p class="text-sm text-gray-400 mb-2">現在登録されているメールアドレス: <span class="text-white font-medium"><?php echo htmlspecialchars($_SESSION['email']); ?></span></p>
            <div class="relative w-full">
                <input type="email" name="new_email" placeholder="新しいメールアドレス" required class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50">
            </div>
            <div class="relative w-full">
                <input type="password" name="password" id="email_change_password" placeholder="現在のパスワード" required class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white pr-10 focus:outline-none focus:border-white/50">
                <button type="button" onclick="togglePasswordVisibility('email_change_password', 'eye-icon-email-change')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                    <svg id="eye-icon-email-change" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
            <button type="submit" class="mt-2 bg-white/10 hover:bg-white/20 text-white py-2 rounded tracking-widest text-sm border border-white/30 w-48 self-start">CHANGE EMAIL</button>
        </form>

        <!-- パスワード変更フォーム -->
        <h2 class="text-xl mb-4 mt-12 tracking-wider">Change Password</h2>
        <form method="POST" class="bg-black/30 border border-white/10 p-6 rounded-xl backdrop-blur-sm flex flex-col gap-4">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <div class="relative w-full">
                <input type="password" name="current_password" id="current_password" placeholder="現在のパスワード" required class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white pr-10 focus:outline-none focus:border-white/50">
                <button type="button" onclick="togglePasswordVisibility('current_password', 'eye-icon-current')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                    <svg id="eye-icon-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
            <div class="relative w-full">
                <input type="password" name="new_password" id="new_password" placeholder="新しいパスワード" required class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white pr-10 focus:outline-none focus:border-white/50">
                <button type="button" onclick="togglePasswordVisibility('new_password', 'eye-icon-new')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                    <svg id="eye-icon-new" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
            <div class="relative w-full">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="新しいパスワードの確認" required class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white pr-10 focus:outline-none focus:border-white/50">
                <button type="button" onclick="togglePasswordVisibility('confirm_password', 'eye-icon-confirm')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                    <svg id="eye-icon-confirm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
            <button type="submit" class="mt-2 bg-white/10 hover:bg-white/20 text-white py-2 rounded tracking-widest text-sm border border-white/30 w-48 self-start">CHANGE PASSWORD</button>
        </form>

        <!-- アカウント削除フォーム -->
        <h2 class="text-xl mb-4 mt-12 tracking-wider text-red-400">Delete Account</h2>
        <form method="POST" class="bg-red-900/20 border border-red-500/30 p-6 rounded-xl backdrop-blur-sm" onsubmit="return confirm('本当によろしいですか？このアカウントと関連するすべてのデータが完全に削除されます。この操作は元に戻せません。');">
            <input type="hidden" name="action" value="delete_account">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <p class="text-sm text-red-200 mb-4">この操作は元に戻せません。アカウントを削除すると、登録されているすべてのデバイス情報が完全に失われます。</p>
            <div class="flex flex-col sm:flex-row gap-4 items-center">
                <div class="relative w-full">
                    <input type="password" name="password" id="delete_password" placeholder="確認のためパスワードを入力" required class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white placeholder:text-gray-400 focus:outline-none focus:border-red-400/80 pr-10">
                    <button type="button" onclick="togglePasswordVisibility('delete_password', 'eye-icon-delete')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                        <svg id="eye-icon-delete" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
                <button type="submit" class="bg-red-600/80 hover:bg-red-600 text-white py-2 rounded tracking-widest text-sm border border-red-400/50 w-full sm:w-64 shrink-0">DELETE MY ACCOUNT</button>
            </div>
        </form>
        
        <div class="mt-12 mb-8 text-center">
            <a href="index.php" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-2.5 rounded-full tracking-[0.2em] text-xs transition-all duration-300 inline-block">BACK TO HOME</a>
        </div>
    </div>

    <script>
        // Levelパスワードのコピー機能
        function copyLevelPassword(button, password) {
            navigator.clipboard.writeText(password).then(function() {
                const copyIcon = button.querySelector('.copy-icon');
                const checkIcon = button.querySelector('.check-icon');
                copyIcon.classList.add('hidden');
                checkIcon.classList.remove('hidden');
                setTimeout(function() {
                    checkIcon.classList.add('hidden');
                    copyIcon.classList.remove('hidden');
                }, 1500);
            });
        }

        function toggleEditForm(formId) {
            const form = document.getElementById(formId);
            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
            } else {
                form.classList.add('hidden');
            }
        }

        function toggleMyDevice(deviceId, isTurnOn, statusElementId) {
            const statusText = document.getElementById(statusElementId);
            const command = isTurnOn ? 'turnOn' : 'turnOff';
            
            statusText.textContent = 'Processing...';
            statusText.className = 'mt-3 text-right text-[10px] text-yellow-400';

            fetch('user_switchbot_api.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $csrfToken; ?>'
                },
                body: JSON.stringify({ device_id: deviceId, action: command })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    statusText.textContent = isTurnOn ? 'Power is ON' : 'Power is OFF';
                    statusText.className = 'mt-3 text-right text-[10px] text-green-400';
                } else {
                    throw new Error(data.error || '通信に失敗しました');
                }
            })
            .catch(err => {
                statusText.textContent = err.message;
                statusText.className = 'mt-3 text-right text-[10px] text-red-400';
                document.getElementById('toggle_' + statusElementId.split('_')[1]).checked = !isTurnOn;
            });
        }

        // --- デバイス自動取得機能 ---
        let fetchedDevices = [];

        function fetchDevicesWithSavedCredentials(savedToken, savedSecret) {
            document.getElementById('api_token').value = savedToken;
            document.getElementById('api_secret').value = savedSecret;
            fetchDevicesFromAPI();
        }

        function fetchDevicesFromAPI() {
            const token = document.getElementById('api_token').value.trim();
            const secret = document.getElementById('api_secret').value.trim();
            const statusEl = document.getElementById('fetch_status');
            const btn = document.getElementById('btn_fetch_devices');

            if (!token || !secret) {
                statusEl.textContent = 'TokenとSecretを入力してください。';
                statusEl.className = 'text-xs text-red-400';
                statusEl.classList.remove('hidden');
                return;
            }

            statusEl.classList.add('hidden');
            btn.textContent = '検索中...';
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');

            fetch('fetch_switchbot_devices.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $csrfToken; ?>'
                },
                body: JSON.stringify({ token: token, secret: secret })
            })
            .then(res => res.json())
            .then(data => {
                btn.textContent = 'デバイス一覧を検索する';
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');

                if (data.success) {
                    fetchedDevices = data.devices;
                    const selector = document.getElementById('device_selector');
                    selector.innerHTML = '<option value="">選択してください</option>';
                    
                    if (fetchedDevices.length === 0) {
                        statusEl.textContent = '登録されているプラグ（Plug Mini等）が見つかりませんでした。';
                        statusEl.className = 'text-xs text-yellow-400';
                        statusEl.classList.remove('hidden');
                        return;
                    }

                    fetchedDevices.forEach(device => {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.textContent = `${device.deviceName} (${device.deviceType})`;
                        selector.appendChild(option);
                    });

                    // UIの切り替え
                    document.getElementById('fetch_devices_section').classList.add('hidden');
                    document.getElementById('manual_input_link').classList.add('hidden');
                    
                    const addForm = document.getElementById('add_device_form');
                    addForm.classList.remove('hidden');
                    addForm.classList.add('flex');
                    
                    // Hiddenフィールドにセット
                    document.getElementById('hidden_token').value = token;
                    document.getElementById('hidden_secret').value = secret;
                    
                    // 入力欄リセット
                    document.getElementById('input_device_name').value = '';
                    document.getElementById('input_device_id').value = '';
                } else {
                    statusEl.textContent = 'エラー: ' + data.error;
                    statusEl.className = 'text-xs text-red-400';
                    statusEl.classList.remove('hidden');
                }
            })
            .catch(err => {
                btn.textContent = 'デバイス一覧を検索する';
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                statusEl.textContent = '通信エラーが発生しました。';
                statusEl.className = 'text-xs text-red-400';
                statusEl.classList.remove('hidden');
            });
        }

        function onDeviceSelected() {
            const selectedId = document.getElementById('device_selector').value;
            const deviceNameInput = document.getElementById('input_device_name');
            const deviceIdInput = document.getElementById('input_device_id');

            if (selectedId) {
                const device = fetchedDevices.find(d => d.deviceId === selectedId);
                if (device) {
                    deviceNameInput.value = device.deviceName;
                    deviceIdInput.value = device.deviceId;
                }
            } else {
                deviceNameInput.value = '';
                deviceIdInput.value = '';
            }
        }

        function showManualInput() {
            document.getElementById('fetch_devices_section').classList.add('hidden');
            document.getElementById('manual_input_link').classList.add('hidden');
            document.getElementById('add_device_form').classList.add('hidden');
            document.getElementById('add_device_form').classList.remove('flex');
            
            const manualForm = document.getElementById('manual_add_device_form');
            manualForm.classList.remove('hidden');
            manualForm.classList.add('flex');
        }

        function resetFetchForm() {
            document.getElementById('fetch_devices_section').classList.remove('hidden');
            document.getElementById('manual_input_link').classList.remove('hidden');
            
            document.getElementById('add_device_form').classList.add('hidden');
            document.getElementById('add_device_form').classList.remove('flex');
            
            document.getElementById('manual_add_device_form').classList.add('hidden');
            document.getElementById('manual_add_device_form').classList.remove('flex');
            
            document.getElementById('fetch_status').classList.add('hidden');
        }
    </script>

    <script>
        // DeviceIDと内部IDのマッピング
        const deviceMap = {
            <?php foreach($devices as $d): ?>
            '<?php echo htmlspecialchars($d['device_id'], ENT_QUOTES); ?>': '<?php echo $d['id']; ?>',
            <?php endforeach; ?>
        };

        function checkDevicesStatus() {
            // タブが非アクティブ（裏側）の場合はAPIリクエストを行わない（制限対策）
            if (document.hidden) return;

            fetch('user_switchbot_status.php')
            .then(response => response.json())
            .then(data => {
                if (!data.statuses) return;
                
                for (const [deviceId, currentPower] of Object.entries(data.statuses)) {
                    const dbId = deviceMap[deviceId];
                    if (!dbId) continue;
                    
                    const statusText = document.getElementById('status_' + dbId);
                    const card = document.getElementById('device_card_' + dbId);
                    const toggle = document.getElementById('toggle_' + dbId);
                    
                    if (currentPower.startsWith('error: ')) {
                        if (statusText && statusText.textContent !== 'Processing...') {
                            statusText.innerHTML = '<span class="flex items-center justify-end gap-1 text-red-400"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>' + currentPower.substring(7) + '</span>';
                        }
                        if (card) {
                            card.classList.add('border-red-500/50', 'bg-red-900/10');
                            card.classList.remove('border-white/10', 'bg-black/30');
                        }
                        if (toggle) {
                            toggle.disabled = true;
                            toggle.classList.add('cursor-not-allowed', 'opacity-50');
                        }
                    } else {
                        // エラーから復旧した（直前がエラー表示だった）場合に緑色のバッジを表示
                        if (statusText && statusText.innerHTML.includes('text-red-400')) {
                             statusText.innerHTML = '<span class="inline-block bg-green-500/20 text-green-400 border border-green-500/50 px-2 py-0.5 rounded tracking-widest transition-all shadow-[0_0_10px_rgba(34,197,94,0.2)]">ONLINE</span>';
                             setTimeout(() => {
                                 if (statusText.innerHTML.includes('ONLINE')) {
                                     statusText.textContent = 'Ready';
                                     statusText.className = 'mt-3 text-right text-[10px] text-gray-500';
                                 }
                             }, 3000);
                        }
                        if (card) {
                            card.classList.remove('border-red-500/50', 'bg-red-900/10');
                            card.classList.add('border-white/10', 'bg-black/30');
                        }
                        if (toggle) {
                            toggle.disabled = false;
                            toggle.classList.remove('cursor-not-allowed', 'opacity-50');
                            if (statusText.textContent !== 'Processing...') {
                                toggle.checked = (currentPower === 'on');
                            }
                        }
                    }
                }
            })
            .catch(error => console.error('Status Check Error:', error));
        }

        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                // 目のアイコン（斜線なし）に変更
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
            } else {
                input.type = 'password';
                // 目のアイコン（斜線あり）に変更
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />';
            }
        }

        const canvas = document.getElementById('waveCanvas');
        const ctx = canvas.getContext('2d');
        let width, height, time = 0;

        // 初期ロード時と10秒ごとにステータスをチェック
        window.addEventListener('DOMContentLoaded', () => {
            checkDevicesStatus();
            setInterval(checkDevicesStatus, 2000); // 2秒ごとに更新して連動を早める
            
            // 画面を開いている間のセッション完全維持（5分ごと）
            setInterval(() => { fetch('keep_alive.php').catch(() => {}); }, 300000);
        });

        function resize() {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
        }
        window.addEventListener('resize', resize);
        resize();

        function drawWaves() {
            ctx.clearRect(0, 0, width, height);
            const waves = [
                { amplitude: 150, frequency: 0.002, speed: 0.015, color: 'rgba(255, 255, 255, 0.05)' },
                { amplitude: 100, frequency: 0.004, speed: 0.02,  color: 'rgba(100, 150, 255, 0.15)' },
                { amplitude: 60,  frequency: 0.006, speed: 0.03,  color: 'rgba(255, 255, 255, 0.03)' }
            ];
            waves.forEach(wave => {
                ctx.beginPath();
                ctx.strokeStyle = wave.color;
                ctx.lineWidth = 1;
                for (let x = 0; x <= width; x += 4) {
                    const envelope = Math.sin(x * 0.001 + time * 0.01) * 0.8 + 0.2;
                    const y = height / 2 + Math.sin(x * wave.frequency + time * wave.speed) * wave.amplitude * envelope;
                    if (x === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                }
                ctx.stroke();
            });
            time += 1;
            requestAnimationFrame(drawWaves);
        }
        drawWaves();
    </script>
</body>
</html>