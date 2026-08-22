<?php
require_once 'db.php';
require_once 'config.php';

$error = '';
$message = '';
$message_class = '';
$admin_login_success = false;

$csrfToken = generateCsrfToken();

// 管理者設定の取得
$stmt = $pdo->query("SELECT key, value FROM settings WHERE key IN ('admin_email', 'admin_password')");
$adminSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$currentAdminEmail = $adminSettings['admin_email'] ?? 'luxewave.0002@gmail.com';
$currentAdminPasswordHash = $adminSettings['admin_password'] ?? null;

// 追加の管理者アカウント一覧
$adminAccounts = $pdo->query("SELECT id, email, created_at FROM admin_accounts ORDER BY created_at")->fetchAll();

// 管理者ログアウト処理
if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    unset($_SESSION['is_master_admin']);
    unset($_SESSION['admin_email']);
    header("Location: admin.php");
    exit;
}

// 管理者ログイン処理
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            die('CSRF token validation failed. 不正なリクエストです。');
        }
        
        $email = $_POST['admin_email'] ?? '';
        $password = $_POST['admin_password'] ?? '';
        $adminLoginIdentifier = 'admin:' . strtolower($email);

        if (tooManyFailedAttempts($pdo, $adminLoginIdentifier)) {
            $error = 'ログイン試行回数が多いため、しばらく時間をおいてから再度お試しください。';
        } elseif ($email === $currentAdminEmail) {
            // マスター管理者（settingsテーブルのアドレス）
            $isPasswordCorrect = false;
            if ($currentAdminPasswordHash) {
                if (password_verify($password, $currentAdminPasswordHash)) {
                    $isPasswordCorrect = true;
                } elseif ($password === 'luxewave2025') {
                    // 初期パスワードの救済措置 (DBにハッシュがない場合や、リセット直後など)
                    $isPasswordCorrect = true;
                }
            } else {
                if ($password === 'luxewave2025') {
                    $isPasswordCorrect = true;
                }
            }

            if ($isPasswordCorrect) {
                clearFailedAttempts($pdo, $adminLoginIdentifier);
                $_SESSION['is_admin'] = true;
                $_SESSION['is_master_admin'] = true;
                $_SESSION['admin_email'] = $currentAdminEmail;
                $admin_login_success = true;
            } else {
                recordFailedAttempt($pdo, $adminLoginIdentifier);
                $error = 'パスワードが間違っています。';
            }
        } else {
            // 追加の管理者アカウント
            $stmt = $pdo->prepare("SELECT email, password FROM admin_accounts WHERE email = ?");
            $stmt->execute([$email]);
            $subAdmin = $stmt->fetch();

            if ($subAdmin && password_verify($password, $subAdmin['password'])) {
                clearFailedAttempts($pdo, $adminLoginIdentifier);
                $_SESSION['is_admin'] = true;
                $_SESSION['is_master_admin'] = false;
                $_SESSION['admin_email'] = $subAdmin['email'];
                $admin_login_success = true;
            } elseif ($subAdmin) {
                recordFailedAttempt($pdo, $adminLoginIdentifier);
                $error = 'パスワードが間違っています。';
            } else {
                recordFailedAttempt($pdo, $adminLoginIdentifier);
                $error = '許可されていない管理者アドレスです。';
            }
        }
    }
}

$users = [];
$logs = [];
// マスター管理者のみが管理者アカウント・マスター認証情報を変更できる
// （既存セッションにフラグがない場合は、従来マスターしかログインできなかったためマスター扱い）
$isMasterAdmin = ($_SESSION['is_master_admin'] ?? true) === true;

if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {

    // POSTリクエスト時のCSRF検証
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            die('CSRF token validation failed. 不正なリクエストです。');
        }
    }

    // 管理者アカウント追加処理（マスターのみ）
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin_account') {
        $newEmail = trim($_POST['new_account_email'] ?? '');
        $newPassword = $_POST['new_account_password'] ?? '';

        if (!$isMasterAdmin) {
            $message = "管理者アカウントの追加はマスター管理者のみ可能です。";
            $message_class = 'error';
        } elseif (!$newEmail || !$newPassword) {
            $message = "すべてのフィールドを入力してください。";
            $message_class = 'error';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $message = "メールアドレスの形式が正しくありません。";
            $message_class = 'error';
        } elseif (strlen($newPassword) < 8) {
            $message = "パスワードは8文字以上で設定してください。";
            $message_class = 'error';
        } elseif ($newEmail === $currentAdminEmail) {
            $message = "そのアドレスはマスター管理者として登録済みです。";
            $message_class = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO admin_accounts (email, password, created_at) VALUES (?, ?, ?)");
                $stmt->execute([$newEmail, password_hash($newPassword, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
                $message = "管理者アカウント（{$newEmail}）を追加しました。";
                $message_class = 'success';
                $adminAccounts = $pdo->query("SELECT id, email, created_at FROM admin_accounts ORDER BY created_at")->fetchAll();
            } catch (PDOException $e) {
                $message = "そのアドレスは既に登録されています。";
                $message_class = 'error';
            }
        }
    }

    // 管理者アカウント削除処理（マスターのみ）
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_admin_account') {
        $deleteAccountId = $_POST['account_id'] ?? '';

        if (!$isMasterAdmin) {
            $message = "管理者アカウントの削除はマスター管理者のみ可能です。";
            $message_class = 'error';
        } elseif ($deleteAccountId) {
            try {
                $pdo->prepare("DELETE FROM admin_accounts WHERE id = ?")->execute([$deleteAccountId]);
                $message = "管理者アカウントを削除しました。";
                $message_class = 'success';
                $adminAccounts = $pdo->query("SELECT id, email, created_at FROM admin_accounts ORDER BY created_at")->fetchAll();
            } catch (Exception $e) {
                $message = "管理者アカウントの削除中にエラーが発生しました。";
                $message_class = 'error';
            }
        }
    }

    // 管理者メールアドレス変更処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_admin_email') {
        $newAdminEmail = $_POST['new_admin_email'] ?? '';
        $password = $_POST['admin_password_confirm'] ?? '';

        if (!$isMasterAdmin) {
            $message = "マスター管理者のアドレス変更はマスター管理者のみ可能です。";
            $message_class = 'error';
        } elseif ($newAdminEmail && $password) {
            $isPasswordCorrect = false;
            if ($currentAdminPasswordHash) {
                if (password_verify($password, $currentAdminPasswordHash)) $isPasswordCorrect = true;
                if (!$isPasswordCorrect && ($password === 'luxewave2025')) $isPasswordCorrect = true;
            } else {
                if ($password === 'luxewave2025') $isPasswordCorrect = true;
            }

            if ($isPasswordCorrect) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key = 'admin_email'");
                    if ($stmt->fetchColumn()) {
                        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'admin_email'");
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES ('admin_email', ?)");
                    }
                    $stmt->execute([$newAdminEmail]);
                    $currentAdminEmail = $newAdminEmail;
                    $message = "管理者のメールアドレスを更新しました。";
                    $message_class = 'success';
                } catch (Exception $e) {
                    $message = "メールアドレスの更新中にエラーが発生しました。";
                    $message_class = 'error';
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

    // 管理者パスワード変更処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_admin_password') {
        $currentPassword = $_POST['current_admin_password'] ?? '';
        $newPassword = $_POST['new_admin_password'] ?? '';
        $confirmPassword = $_POST['confirm_admin_password'] ?? '';

        if (!$isMasterAdmin) {
            $message = "マスター管理者のパスワード変更はマスター管理者のみ可能です。";
            $message_class = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = "新しいパスワードが一致しません。";
            $message_class = 'error';
        } elseif ($currentPassword && $newPassword) {
            $isPasswordCorrect = false;
            if ($currentAdminPasswordHash) {
                if (password_verify($currentPassword, $currentAdminPasswordHash)) $isPasswordCorrect = true;
                if (!$isPasswordCorrect && ($currentPassword === 'luxewave2025')) $isPasswordCorrect = true;
            } else {
                if ($currentPassword === 'luxewave2025') $isPasswordCorrect = true;
            }

            if ($isPasswordCorrect) {
                try {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key = 'admin_password'");
                    if ($stmt->fetchColumn()) {
                        $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'admin_password'");
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES ('admin_password', ?)");
                    }
                    $stmt->execute([$newHash]);
                    $currentAdminPasswordHash = $newHash;
                    $message = "管理者のパスワードを更新しました。";
                    $message_class = 'success';
                } catch (Exception $e) {
                    $message = "パスワードの更新中にエラーが発生しました。";
                    $message_class = 'error';
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

    // デバイスレベル更新処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_device_level') {
        $updateDeviceId = $_POST['device_id'] ?? '';
        $newLevel = $_POST['level'] ?? 1;

        if ($updateDeviceId) {
            try {
                $now = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("UPDATE devices SET level = ?, level_updated_at = ? WHERE id = ?");
                $stmt->execute([$newLevel, $now, $updateDeviceId]);
                $message = "デバイス (ID: {$updateDeviceId}) のレベルを更新しました。";
                $message_class = 'success';
            } catch (Exception $e) {
                $message = "レベルの更新中にエラーが発生しました。";
                $message_class = 'error';
            }
        }
    }

    // ユーザーのニックネーム更新処理（管理者が任意で設定する内部メモ用の呼び名）
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_nickname') {
        $nicknameUserId = $_POST['user_id'] ?? '';
        $newNickname = trim($_POST['nickname'] ?? '');

        if ($nicknameUserId) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET nickname = ? WHERE id = ?");
                $stmt->execute([$newNickname !== '' ? $newNickname : null, $nicknameUserId]);
                $message = "ユーザー (ID: {$nicknameUserId}) のニックネームを更新しました。";
                $message_class = 'success';
            } catch (Exception $e) {
                $message = "ニックネームの更新中にエラーが発生しました。";
                $message_class = 'error';
            }
        }
    }

    // ユーザー削除処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $deleteUserId = $_POST['delete_user_id'] ?? '';
        if ($deleteUserId) {
            $pdo->beginTransaction();
            try {
                // 紐づくデバイスを先に削除
                $stmt = $pdo->prepare("DELETE FROM devices WHERE user_id = ?");
                $stmt->execute([$deleteUserId]);

                // ユーザー自身を削除
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$deleteUserId]);

                $pdo->commit();
                $message = "ユーザー (ID: {$deleteUserId}) を削除しました。";
                $message_class = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "ユーザーの削除中にエラーが発生しました。";
                $message_class = 'error';
            }
        }
    }

    // パスワード強制リセット処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
        $resetUserId = $_POST['reset_user_id'] ?? '';
        if ($resetUserId) {
            // ランダムな8文字の新しいパスワードを生成
            $newPassword = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$newHash, $resetUserId]);
                $message = "ユーザー (ID: {$resetUserId}) のパスワードをリセットしました。新しいパスワード: 「 {$newPassword} 」";
                $message_class = 'success';
            } catch (Exception $e) {
                $message = "パスワードのリセット中にエラーが発生しました。";
                $message_class = 'error';
            }
        }
    }

    // Levelパスワードの発行/再発行処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue_level_password') {
        $issueUserId = $_POST['issue_user_id'] ?? '';
        $issueLevel = $_POST['issue_level'] ?? '';

        if ($issueUserId && filter_var($issueLevel, FILTER_VALIDATE_INT, array("options" => array("min_range"=>1, "max_range"=>10)))) {
            try {
                $newLevelPassword = generateLevelPassword();
                $now = date('Y-m-d H:i:s');
                // 既存の記録（削除済みを含む）があれば上書きし、無ければ新規発行
                $updateStmt = $pdo->prepare("UPDATE level_passwords SET password = ?, created_at = ?, revoked_at = NULL WHERE user_id = ? AND level = ?");
                $updateStmt->execute([$newLevelPassword, $now, $issueUserId, $issueLevel]);
                if ($updateStmt->rowCount() === 0) {
                    $stmt = $pdo->prepare("INSERT INTO level_passwords (user_id, level, password, created_at) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$issueUserId, $issueLevel, $newLevelPassword, $now]);
                }
                $message = "Level.{$issueLevel} のパスワードを発行しました: 「 {$newLevelPassword} 」";
                $message_class = 'success';
            } catch (Exception $e) {
                $message = "パスワードの発行中にエラーが発生しました。";
                $message_class = 'error';
            }
        }
    }

    // Levelパスワードの削除処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_level_password') {
        $deleteUserId = $_POST['delete_user_id'] ?? '';
        $deleteLevel = $_POST['delete_level'] ?? '';

        if ($deleteUserId && filter_var($deleteLevel, FILTER_VALIDATE_INT, array("options" => array("min_range"=>1, "max_range"=>10)))) {
            $now = date('Y-m-d H:i:s');
            // 行は残したままパスワードだけ消す（削除済みの記録としてDBに残し、閲覧時は再度パスワードを求める）
            $revokeStmt = $pdo->prepare("UPDATE level_passwords SET password = NULL, revoked_at = ? WHERE user_id = ? AND level = ?");
            $revokeStmt->execute([$now, $deleteUserId, $deleteLevel]);
            if ($revokeStmt->rowCount() === 0) {
                $insertStmt = $pdo->prepare("INSERT INTO level_passwords (user_id, level, password, created_at, revoked_at) VALUES (?, ?, NULL, ?, ?)");
                $insertStmt->execute([$deleteUserId, $deleteLevel, $now, $now]);
            }
            $message = "Level.{$deleteLevel} のパスワードを削除しました。次回アクセス時は再度パスワードの入力を求めます。";
            $message_class = 'success';
        }
    }

    // Levelページのメディア アップロード/削除処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['upload_level_media', 'delete_level_media'])) {
        $levelId = $_POST['level_id'] ?? '';

        if (!filter_var($levelId, FILTER_VALIDATE_INT, array("options" => array("min_range"=>1, "max_range"=>10)))) {
            $message = "不正なLevelです。";
            $message_class = 'error';
        } elseif ($_POST['action'] === 'delete_level_media') {
            $old_files = glob("upload_test{$levelId}.*");
            $deleted = false;
            if ($old_files) {
                foreach ($old_files as $f) {
                    unlink($f);
                    $deleted = true;
                }
            }
            if ($deleted) {
                $message = "Level.{$levelId} のメディアを削除し、初期状態に戻しました。";
                $message_class = 'success';
                writeLog($pdo, null, 'delete_media', "Level.{$levelId} のメディアを削除しました。");
            } else {
                $message = "削除するメディアがありません（初期状態です）。";
                $message_class = 'error';
            }
        } else {
            // アップロード
            if (isset($_FILES['level_image']) && $_FILES['level_image']['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['level_image']['tmp_name'];
                $fileName = $_FILES['level_image']['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/quicktime', 'video/webm'];

                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'webm']) && in_array($mimeType, $allowedMimeTypes)) {
                    $old_files = glob("upload_test{$levelId}.*");
                    if ($old_files) {
                        foreach ($old_files as $f) {
                            unlink($f);
                        }
                    }

                    $destination = "upload_test{$levelId}.{$ext}";
                    if (move_uploaded_file($tmpName, $destination)) {
                        $message = "Level.{$levelId} のメディアを更新しました！";
                        $message_class = 'success';
                        writeLog($pdo, null, 'upload_media', "Level.{$levelId} にメディア({$fileName})をアップロードしました。");
                    } else {
                        $message = "保存に失敗しました。サーバーの権限を確認してください。";
                        $message_class = 'error';
                    }
                } else {
                    $message = "JPG, PNG, GIF または MP4, MOV, WEBM形式がアップロード可能です。";
                    $message_class = 'error';
                }
            } else {
                $uploadError = $_FILES['level_image']['error'] ?? UPLOAD_ERR_NO_FILE;
                if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
                    $maxFile = ini_get('upload_max_filesize');
                    $message = "ファイルサイズが大きすぎます（上限: {$maxFile}）。より小さな画像を選択してください。";
                } else {
                    $message = "画像ファイルを選択してからUPLOADを押してください。";
                }
                $message_class = 'error';
            }
        }
    }

    // CSVエクスポート処理
    if (isset($_GET['export_csv']) && $_GET['export_csv'] == '1') {
        $stmt = $pdo->query("
            SELECT logs.created_at, users.email, logs.action, logs.details, logs.ip_address 
            FROM logs 
            LEFT JOIN users ON logs.user_id = users.id 
            ORDER BY logs.created_at DESC
        ");
        $exportLogs = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="activity_logs_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        // Excelでの文字化け防止用BOMを出力
        fputs($output, "\xEF\xBB\xBF");

        fputcsv($output, ['Time', 'User', 'Action', 'Details', 'IP Address']);
        foreach ($exportLogs as $log) {
            $email = $log['email'] ? $log['email'] : 'Guest / System';
            fputcsv($output, [$log['created_at'], $email, $log['action'], $log['details'], $log['ip_address']]);
        }
        fclose($output);
        exit;
    }

    // 全ユーザー数の取得（合計人数）
    $totalUsersStmt = $pdo->query("SELECT COUNT(*) FROM users");
    $totalUsersCount = $totalUsersStmt->fetchColumn();

    // 全デバイス数の取得（合計登録数）
    $totalDevicesStmt = $pdo->query("SELECT COUNT(*) FROM devices");
    $totalDevicesCount = $totalDevicesStmt->fetchColumn();

    // 検索クエリの取得
    $searchQuery = $_GET['search'] ?? '';

    // 全ユーザーを取得
    if ($searchQuery !== '') {
        $stmt = $pdo->prepare("SELECT id, email, nickname FROM users WHERE email LIKE ? OR id = ? ORDER BY id ASC");
        $stmt->execute(['%' . $searchQuery . '%', $searchQuery]);
    } else {
        $stmt = $pdo->query("SELECT id, email, nickname FROM users ORDER BY id ASC");
    }
    $users = $stmt->fetchAll();

    // 各ユーザーのデバイス情報を取得して配列にまとめる
    foreach ($users as &$user) {
        $devStmt = $pdo->prepare("SELECT id, device_name, device_id, token, level, level_updated_at FROM devices WHERE user_id = ? ORDER BY sort_order ASC, id ASC");
        $devStmt->execute([$user['id']]);
        $user['devices'] = $devStmt->fetchAll();
        $user['device_count'] = count($user['devices']);

        // 最終ログイン日時を取得
        $loginStmt = $pdo->prepare("SELECT created_at FROM logs WHERE user_id = ? AND action = 'login' ORDER BY created_at DESC LIMIT 1");
        $loginStmt->execute([$user['id']]);
        $lastLogin = $loginStmt->fetchColumn();
        $user['last_login'] = $lastLogin ? $lastLogin : '-';

        // Home画面のLevelパスワード（削除済みの記録も含めて取得）
        $lvlPwStmt = $pdo->prepare("SELECT level, password, revoked_at FROM level_passwords WHERE user_id = ?");
        $lvlPwStmt->execute([$user['id']]);
        $user['level_passwords'] = [];
        $user['level_revoked_at'] = [];
        foreach ($lvlPwStmt->fetchAll() as $lvlPwRow) {
            $lvlPwLevel = (int)$lvlPwRow['level'];
            if ($lvlPwRow['revoked_at'] !== null || $lvlPwRow['password'] === null || $lvlPwRow['password'] === '') {
                $user['level_revoked_at'][$lvlPwLevel] = $lvlPwRow['revoked_at'];
            } else {
                $user['level_passwords'][$lvlPwLevel] = $lvlPwRow['password'];
            }
        }

        // このユーザーの招待コードを取得（有効なものを優先し、無ければ最新の1件）
        $inviteStmt = $pdo->prepare("SELECT code, used_by_user_id, revoked_at FROM invite_codes WHERE issued_to_user_id = ? ORDER BY CASE WHEN used_by_user_id IS NULL AND revoked_at IS NULL THEN 0 ELSE 1 END, id DESC LIMIT 1");
        $inviteStmt->execute([$user['id']]);
        $user['invite_code'] = $inviteStmt->fetch();

        // 発行履歴（使用済み・無効化を含む）の全件
        $inviteHistStmt = $pdo->prepare("SELECT code, created_at, used_at, used_by_user_id, revoked_at FROM invite_codes WHERE issued_to_user_id = ? ORDER BY id DESC");
        $inviteHistStmt->execute([$user['id']]);
        $user['invite_history'] = $inviteHistStmt->fetchAll();
    }
    unset($user);

    // 最新の操作ログを取得 (最大50件)
    $logSearchQuery = $_GET['log_search'] ?? '';
    if ($logSearchQuery !== '') {
        $logStmt = $pdo->prepare("
            SELECT logs.*, users.email 
            FROM logs 
            LEFT JOIN users ON logs.user_id = users.id 
            WHERE logs.action LIKE ? OR logs.details LIKE ? OR users.email LIKE ? OR logs.ip_address LIKE ?
            ORDER BY logs.created_at DESC LIMIT 100
        ");
        $likeQuery = '%' . $logSearchQuery . '%';
        $logStmt->execute([$likeQuery, $likeQuery, $likeQuery, $likeQuery]);
    } else {
        $logStmt = $pdo->query("
            SELECT logs.*, users.email 
            FROM logs 
            LEFT JOIN users ON logs.user_id = users.id 
            ORDER BY logs.created_at DESC LIMIT 50
        ");
    }
    $logs = $logStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - LUXE WAVE</title>
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
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        .animate-shake {
            animation: shake 0.5s ease-in-out;
        }
        @keyframes fadeOutScale {
            0% { opacity: 1; transform: scale(1); filter: blur(0); }
            100% { opacity: 0; transform: scale(1.05); filter: blur(8px); }
        }
        .animate-fade-out {
            animation: fadeOutScale 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black min-h-screen text-white font-light relative z-0 overflow-x-hidden">
<!-- インアプリブラウザ（LINE/Facebook/Instagram等）検知バナー -->
<div id="lw-inapp-banner" class="hidden fixed top-0 left-0 right-0 z-[9999] bg-black/95 border-b border-white/20 text-white text-xs sm:text-sm px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-2 backdrop-blur-md">
    <span class="tracking-wide leading-relaxed">アプリ内ブラウザで表示しています。正しく動作しない場合は右上「…」等のメニューから「ブラウザで開く」を選択してください。</span>
    <div class="flex items-center gap-2 shrink-0">
        <button type="button" onclick="lwCopyCurrentUrl(this)" class="border border-white/30 hover:bg-white/10 px-3 py-1 rounded-full tracking-wider text-xs transition-colors">URLをコピー</button>
        <button type="button" onclick="document.getElementById('lw-inapp-banner').style.display='none'" class="text-gray-400 hover:text-white px-2 text-lg leading-none">&times;</button>
    </div>
</div>
<script>
    (function() {
        var ua = navigator.userAgent || '';
        var isInApp = /FBAN|FBAV|Instagram|Line\//i.test(ua) || (/; wv\)/i.test(ua) && /Version\//i.test(ua));
        if (isInApp) {
            var banner = document.getElementById('lw-inapp-banner');
            if (banner) banner.classList.remove('hidden');
        }
    })();
    function lwCopyCurrentUrl(btn) {
        navigator.clipboard.writeText(window.location.href).then(function() {
            var original = btn.textContent;
            btn.textContent = 'コピーしました';
            setTimeout(function() { btn.textContent = original; }, 1500);
        }).catch(function() {});
    }
</script>


    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <div class="max-w-4xl mx-auto p-6">
        <?php if ((!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) || $admin_login_success): ?>
            <!-- 管理者ログインフォーム -->
            <div class="flex items-center justify-center min-h-[80vh]">
                <div class="bg-black/40 p-6 sm:p-8 rounded-xl border shadow-2xl w-full max-w-sm mx-4 sm:mx-0 backdrop-blur-md transition-all duration-500 <?php echo $error ? 'animate-shake border-red-500/50' : ($admin_login_success ? 'border-green-500/50 shadow-[0_0_20px_rgba(34,197,94,0.3)]' : 'border-white/20'); ?>">
                    <h2 class="text-2xl font-light mb-6 tracking-widest text-center"><?php echo $admin_login_success ? 'WELCOME' : 'ADMIN LOGIN'; ?></h2>
                    
                    <?php if($admin_login_success): ?>
                        <div class="flex flex-col items-center justify-center py-4">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-green-400 mb-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-green-400 text-sm text-center tracking-widest">管理者として認証しました</p>
                        </div>
                    <?php else: ?>
                        <?php if($error): ?>
                            <p class="text-red-400 text-xs mb-4 text-center"><?php echo htmlspecialchars($error); ?></p>
                        <?php endif; ?>
                        <form method="POST" class="flex flex-col gap-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="email" name="admin_email" placeholder="Admin Email" required 
                                class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50">
                            
                            <div class="relative w-full">
                                <input type="password" name="admin_password" id="admin_password" placeholder="Admin Password" required class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50 pr-10">
                                <button type="button" onclick="togglePasswordVisibility('admin_password', 'eye-icon-admin')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                                    <svg id="eye-icon-admin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>

                            <div class="flex flex-col gap-3">
                                <label class="flex items-center gap-2 text-xs text-gray-400 select-none cursor-pointer">
                                    <input type="checkbox" id="rememberAdminEmail" class="sr-only">
                                    <span class="w-5 h-5 shrink-0 rounded border border-white/30 bg-white/5 flex items-center justify-center transition-colors" id="rememberAdminEmailBox">
                                        <svg id="rememberAdminEmailCheckIcon" class="w-3.5 h-3.5 text-black hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.415l-7.5 7.5a1 1 0 01-1.415 0l-3.5-3.5a1 1 0 111.415-1.414L8.5 12.086l6.79-6.795a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                    メールアドレスを保存する
                                </label>
                                <label class="flex items-center gap-2 text-xs text-gray-400 select-none cursor-pointer">
                                    <input type="checkbox" id="rememberAdminPassword" class="sr-only">
                                    <span class="w-5 h-5 shrink-0 rounded border border-white/30 bg-white/5 flex items-center justify-center transition-colors" id="rememberAdminPasswordBox">
                                        <svg id="rememberAdminPasswordCheckIcon" class="w-3.5 h-3.5 text-black hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.415l-7.5 7.5a1 1 0 01-1.415 0l-3.5-3.5a1 1 0 111.415-1.414L8.5 12.086l6.79-6.795a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                    パスワードを保存する
                                </label>
                            </div>

                            <button type="submit" class="mt-4 bg-white/10 hover:bg-white/20 text-white py-2 rounded tracking-widest text-sm transition-all border border-white/30">LOGIN</button>
                        </form>
                        <div class="mt-6 text-center">
                            <a href="index.php" class="text-xs text-gray-400 hover:text-white transition-colors">Homeに戻る</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- 管理者ダッシュボード -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 sm:mb-10 border-b border-white/20 pb-4 mt-8 gap-4 sm:gap-0">
                <div>
                    <h1 class="text-2xl tracking-widest uppercase">Admin Dashboard</h1>
                    <?php if (!empty($_SESSION['admin_email'])): ?>
                        <p class="text-xs text-gray-400 mt-1 break-all"><?php echo htmlspecialchars($_SESSION['admin_email']); ?><?php echo $isMasterAdmin ? '（マスター）' : ''; ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                    <a href="index.php" class="text-xs border border-white/30 px-3 py-1 rounded hover:bg-white/10 transition">L/W</a>
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

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-4 gap-4">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl tracking-wider">User List</h2>
                    <span class="text-xs bg-white/10 text-gray-300 px-2.5 py-1 rounded-full tracking-widest">Total Users: <?php echo $totalUsersCount; ?></span>
                    <span class="text-xs bg-white/10 text-gray-300 px-2.5 py-1 rounded-full tracking-widest">Total Devices: <?php echo $totalDevicesCount; ?></span>
                </div>
                
                <!-- 検索フォーム -->
                <form method="GET" class="flex items-center gap-2 w-full sm:w-auto">
                    <?php if($logSearchQuery !== ''): ?>
                        <input type="hidden" name="log_search" value="<?php echo htmlspecialchars($logSearchQuery); ?>">
                    <?php endif; ?>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Email または ID で検索" class="bg-white/5 border border-white/20 rounded px-3 py-1.5 text-sm text-white focus:outline-none focus:border-white/50 w-full sm:w-64">
                    <button type="submit" class="bg-white/10 hover:bg-white/20 text-white px-4 py-1.5 rounded text-sm transition-colors border border-white/30 tracking-wider">SEARCH</button>
                    <?php if($searchQuery !== ''): ?>
                        <a href="admin.php<?php echo $logSearchQuery !== '' ? '?log_search=' . urlencode($logSearchQuery) : ''; ?>" class="text-xs text-gray-400 hover:text-white transition-colors ml-2 whitespace-nowrap">クリア</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="bg-black/30 border border-white/10 rounded-xl backdrop-blur-sm overflow-x-auto w-full">
                <table class="w-full text-left border-collapse min-w-[600px]">
                    <thead>
                        <tr class="bg-white/10 border-b border-white/10 text-sm tracking-wider">
                            <th class="p-4 font-medium text-gray-300">ID</th>
                            <th class="p-4 font-medium text-gray-300">Email</th>
                            <th class="p-4 font-medium text-gray-300">Nickname</th>
                            <th class="p-4 font-medium text-gray-300">Registered Devices</th>
                            <th class="p-4 font-medium text-gray-300">Last Login</th>
                            <th class="p-4 font-medium text-gray-300 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="p-4 text-gray-400">#<?php echo htmlspecialchars($user['id']); ?></td>
                                <td class="p-4 text-gray-200"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="p-4">
                                    <form method="POST" class="flex items-center gap-1.5">
                                        <input type="hidden" name="action" value="update_nickname">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="text" name="nickname" value="<?php echo htmlspecialchars($user['nickname'] ?? ''); ?>" placeholder="（任意）" class="bg-white/5 border border-white/20 rounded px-2 py-1 text-xs text-white w-28 focus:outline-none focus:border-white/50">
                                        <button type="submit" class="text-xs text-gray-400 hover:text-white border border-white/20 rounded px-2 py-1 transition-colors whitespace-nowrap">保存</button>
                                    </form>
                                </td>
                                <td class="p-4 text-gray-400"><?php echo htmlspecialchars($user['device_count']); ?> Devices</td>
                                <td class="p-4 text-gray-500 text-xs whitespace-nowrap"><?php echo htmlspecialchars($user['last_login']); ?></td>
                                <td class="p-4 text-right whitespace-nowrap">
                                    <form method="POST" onsubmit="return confirm('このユーザーのパスワードをリセットしますか？\n新しいパスワードが自動生成されます。');" class="inline-block mr-2">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="reset_user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="text-xs text-yellow-500/70 hover:text-yellow-400 hover:bg-yellow-500/10 border border-yellow-500/30 px-3 py-1 rounded transition-colors tracking-widest">RESET PW</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('本当にこのユーザーと関連するすべてのデバイス情報を削除しますか？\nこの操作は元に戻せません。');" class="inline-block">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="text-xs text-red-500/70 hover:text-red-400 hover:bg-red-500/10 border border-red-500/30 px-3 py-1 rounded transition-colors tracking-widest">DELETE</button>
                                    </form>
                                </td>
                            </tr>
                            <?php if (!empty($user['devices'])): ?>
                            <tr class="border-b border-white/10 bg-black/20">
                                <td colspan="5" class="p-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pl-4 border-l-2 border-white/20 ml-2">
                                        <?php foreach ($user['devices'] as $device): ?>
                                            <div class="bg-white/5 p-3 rounded-lg border border-white/10 text-sm">
                                                <div class="text-gray-200 font-medium mb-1"><?php echo htmlspecialchars($device['device_name']); ?></div>
                                                <div class="text-gray-400 text-xs font-mono">ID: <?php echo htmlspecialchars($device['device_id']); ?></div>
                                                <div class="text-gray-500 text-[10px] font-mono mt-1 truncate" title="<?php echo htmlspecialchars($device['token']); ?>">
                                                    Token: <?php echo htmlspecialchars(substr($device['token'], 0, 15) . '...'); ?>
                                                </div>
                                                <form method="POST" class="mt-2 flex items-center gap-2">
                                                    <input type="hidden" name="action" value="update_device_level">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                    <label class="text-gray-400 text-[10px]">Level:</label>
                                                    <input type="number" name="level" value="<?php echo htmlspecialchars($device['level'] ?? 1); ?>" class="bg-black/50 border border-white/20 text-white rounded px-2 py-1 text-xs w-16 focus:outline-none focus:border-white/50" min="1">
                                                    <button type="submit" class="text-[10px] bg-white/10 hover:bg-white/20 border border-white/20 px-2 py-1 rounded transition-colors text-gray-300 hover:text-white tracking-wider">UPDATE</button>
                                                </form>
                                                <?php if (!empty($device['level_updated_at'])): ?>
                                                <div class="text-gray-500 text-[10px] font-mono mt-1">
                                                    Updated: <?php echo htmlspecialchars(date('Y.m.d H:i', strtotime($device['level_updated_at']))); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr class="border-b border-white/10 bg-black/20">
                                <td colspan="5" class="p-4">
                                    <div class="flex flex-wrap gap-3 pl-4 border-l-2 border-white/20 ml-2">
                                        <?php foreach ([1, 2, 3, 4] as $lvl): ?>
                                            <div class="bg-white/5 p-3 rounded-lg border border-white/10 text-xs flex items-center gap-2">
                                                <span class="text-gray-400 tracking-wider">Level.<?php echo $lvl; ?></span>
                                                <?php if (isset($user['level_passwords'][$lvl])): ?>
                                                    <span class="font-mono text-gray-200 bg-black/40 px-2 py-1 rounded"><?php echo htmlspecialchars($user['level_passwords'][$lvl]); ?></span>
                                                <?php elseif (array_key_exists($lvl, $user['level_revoked_at'])): ?>
                                                    <span class="text-red-300/80" title="<?php echo htmlspecialchars((string)$user['level_revoked_at'][$lvl]); ?>">削除済み（要再発行）</span>
                                                <?php else: ?>
                                                    <span class="text-gray-600">未発行</span>
                                                <?php endif; ?>
                                                <form method="POST" onsubmit="return confirm('Level.<?php echo $lvl; ?> のパスワードを<?php echo isset($user['level_passwords'][$lvl]) ? '再発行' : '発行'; ?>しますか？');">
                                                    <input type="hidden" name="action" value="issue_level_password">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="issue_user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="issue_level" value="<?php echo $lvl; ?>">
                                                    <button type="submit" class="text-[10px] bg-white/10 hover:bg-white/20 border border-white/20 px-2 py-1 rounded transition-colors text-gray-300 hover:text-white tracking-wider"><?php echo isset($user['level_passwords'][$lvl]) ? '再発行' : '発行する'; ?></button>
                                                </form>
                                                <?php if (isset($user['level_passwords'][$lvl])): ?>
                                                <form method="POST" onsubmit="return confirm('Level.<?php echo $lvl; ?> のパスワードを削除しますか？');">
                                                    <input type="hidden" name="action" value="delete_level_password">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="delete_level" value="<?php echo $lvl; ?>">
                                                    <button type="submit" class="text-[10px] bg-red-900/30 hover:bg-red-800/50 border border-red-900/50 px-2 py-1 rounded transition-colors text-red-200 tracking-wider">削除</button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr class="border-b border-white/10 bg-black/20">
                                <td colspan="5" class="p-4">
                                    <div class="pl-4 border-l-2 border-white/20 ml-2 text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-400 tracking-wider">招待コード</span>
                                            <?php if ($user['invite_code']): ?>
                                                <span class="font-mono text-gray-200 bg-black/40 px-2 py-1 rounded"><?php echo htmlspecialchars($user['invite_code']['code']); ?></span>
                                                <?php if ($user['invite_code']['used_by_user_id']): ?>
                                                    <span class="text-gray-600">（使用済み）</span>
                                                <?php elseif ($user['invite_code']['revoked_at']): ?>
                                                    <span class="text-red-300/70">（無効・再発行済み）</span>
                                                <?php else: ?>
                                                    <span class="text-green-500/70">（未使用）</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-600">未発行</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (count($user['invite_history']) > 1): ?>
                                            <details class="mt-2">
                                                <summary class="text-gray-500 cursor-pointer hover:text-gray-300">発行履歴（<?php echo count($user['invite_history']); ?>件）</summary>
                                                <div class="mt-2 flex flex-col gap-1">
                                                    <?php foreach ($user['invite_history'] as $inviteRow): ?>
                                                        <div class="flex flex-wrap items-center gap-2 bg-black/20 px-2 py-1 rounded">
                                                            <span class="font-mono <?php echo ($inviteRow['used_by_user_id'] || $inviteRow['revoked_at']) ? 'text-gray-500 line-through' : 'text-gray-200'; ?>"><?php echo htmlspecialchars($inviteRow['code']); ?></span>
                                                            <?php if ($inviteRow['used_by_user_id']): ?>
                                                                <span class="text-gray-600">使用済み（user_id: <?php echo (int)$inviteRow['used_by_user_id']; ?> / <?php echo htmlspecialchars((string)$inviteRow['used_at']); ?>）</span>
                                                            <?php elseif ($inviteRow['revoked_at']): ?>
                                                                <span class="text-red-300/70">無効化（<?php echo htmlspecialchars((string)$inviteRow['revoked_at']); ?>）</span>
                                                            <?php else: ?>
                                                                <span class="text-green-500/70">有効</span>
                                                            <?php endif; ?>
                                                            <span class="text-gray-600 ml-auto">発行 <?php echo htmlspecialchars((string)$inviteRow['created_at']); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" class="p-4 text-center text-sm text-gray-500">ユーザーは登録されていません。</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 操作ログセクション -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-4 mt-12 gap-4">
                <h2 class="text-xl tracking-wider">Activity Logs</h2>
                
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 w-full sm:w-auto">
                    <!-- ログ検索フォーム -->
                    <form method="GET" class="flex items-center gap-2 w-full sm:w-auto">
                        <?php if($searchQuery !== ''): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <?php endif; ?>
                        <input type="text" name="log_search" value="<?php echo htmlspecialchars($logSearchQuery); ?>" placeholder="Action, Details, Email, IP" class="bg-white/5 border border-white/20 rounded px-3 py-1.5 text-sm text-white focus:outline-none focus:border-white/50 w-full sm:w-64">
                        <button type="submit" class="bg-white/10 hover:bg-white/20 text-white px-4 py-1.5 rounded text-sm transition-colors border border-white/30 tracking-wider">SEARCH</button>
                        <?php if($logSearchQuery !== ''): ?>
                            <a href="admin.php<?php echo $searchQuery !== '' ? '?search=' . urlencode($searchQuery) : ''; ?>" class="text-xs text-gray-400 hover:text-white transition-colors ml-2 whitespace-nowrap">クリア</a>
                        <?php endif; ?>
                    </form>
                    
                    <a href="?export_csv=1" class="text-xs border border-white/30 px-3 py-1.5 rounded hover:bg-white/10 transition tracking-widest text-gray-300 hover:text-white whitespace-nowrap w-full sm:w-auto text-center">CSV EXPORT</a>
                </div>
            </div>
            <div class="bg-black/30 border border-white/10 rounded-xl backdrop-blur-sm overflow-hidden mb-12 w-full">
                <div class="max-h-96 overflow-y-auto overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse text-sm min-w-[600px]">
                        <thead>
                            <tr class="bg-white/10 border-b border-white/10 tracking-wider sticky top-0 backdrop-blur-md">
                                <th class="p-4 font-medium text-gray-300">Time</th>
                                <th class="p-4 font-medium text-gray-300">User</th>
                                <th class="p-4 font-medium text-gray-300">Action</th>
                                <th class="p-4 font-medium text-gray-300">Details</th>
                                <th class="p-4 font-medium text-gray-300">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                    <td class="p-4 text-gray-400 whitespace-nowrap"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                    <td class="p-4">
                                        <?php if ($log['email']): ?>
                                            <span class="text-blue-300 font-medium"><?php echo htmlspecialchars($log['email']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-500 italic">Guest / System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-gray-300">
                                        <span class="bg-white/10 px-2 py-1 rounded text-[10px] tracking-widest uppercase"><?php echo htmlspecialchars($log['action']); ?></span>
                                    </td>
                                    <td class="p-4 text-gray-400 break-all text-xs"><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td class="p-4 text-gray-500 font-mono text-[10px] whitespace-nowrap"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="p-4 text-center text-sm text-gray-500">ログはまだありません。</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Levelページ メディア管理セクション -->
            <div class="flex flex-col justify-between items-start mb-4 mt-12 gap-4">
                <h2 class="text-xl tracking-wider">Level Media</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
                <?php foreach ([1, 2, 3, 4] as $i): ?>
                    <?php $levelImagePath = getImagePath((string)$i); ?>
                    <div class="bg-black/30 border border-white/10 rounded-xl backdrop-blur-sm p-6 w-full">
                        <h3 class="text-lg tracking-wider mb-4">Level.<?php echo $i; ?></h3>

                        <div class="overflow-hidden rounded shadow-2xl bg-black flex justify-center items-center py-6 mb-4">
                            <?php if (isVideoFile($levelImagePath)): ?>
                                <video src="<?php echo htmlspecialchars($levelImagePath); ?>" controls class="w-full max-w-xs h-auto"></video>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($levelImagePath); ?>" alt="Level.<?php echo $i; ?> メディア" class="w-full max-w-xs h-auto object-contain">
                            <?php endif; ?>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="flex flex-wrap gap-3 items-center">
                            <input type="hidden" name="level_id" value="<?php echo $i; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="file" name="level_image" accept="image/*,video/*" class="text-xs text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-light file:bg-white/10 file:text-white hover:file:bg-white/20 w-full sm:w-auto cursor-pointer focus:outline-none">
                            <div class="flex gap-2 w-full sm:w-auto shrink-0">
                                <button type="submit" name="action" value="upload_level_media" class="bg-white/10 hover:bg-white/20 text-white text-sm px-4 py-2 rounded transition-colors w-full sm:w-auto tracking-widest font-light">UPLOAD</button>
                                <button type="submit" name="action" value="delete_level_media" class="bg-red-900/30 hover:bg-red-800/50 text-red-200 border border-red-900/50 text-sm px-4 py-2 rounded transition-colors w-full sm:w-auto tracking-widest font-light" onclick="return confirm('Level.<?php echo $i; ?> のメディアを削除して初期状態に戻しますか？');">DELETE</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($isMasterAdmin): ?>
            <!-- 管理者設定セクション -->
            <div class="flex flex-col justify-between items-start mb-4 mt-12 gap-4">
                <h2 class="text-xl tracking-wider">Admin Settings</h2>
            </div>

            <!-- 管理者アカウントの追加・削除 -->
            <div class="bg-black/30 border border-white/10 rounded-xl backdrop-blur-sm p-6 w-full mb-6">
                <h3 class="text-lg tracking-wider mb-2">Admin Accounts</h3>
                <p class="text-xs text-gray-400 mb-4">このADMINページにログインできるアドレスを追加できます。追加したアカウントは管理者設定（このセクション）以外の全機能が使えます。</p>

                <div class="flex flex-col gap-2 mb-6">
                    <!-- マスター管理者 -->
                    <div class="flex flex-wrap justify-between items-center gap-2 bg-white/5 border border-white/10 rounded px-4 py-2">
                        <span class="text-sm text-white break-all"><?php echo htmlspecialchars($currentAdminEmail); ?></span>
                        <span class="text-[10px] tracking-widest text-gray-400 border border-white/20 rounded px-2 py-0.5 shrink-0">MASTER</span>
                    </div>

                    <?php foreach ($adminAccounts as $account): ?>
                        <div class="flex flex-wrap justify-between items-center gap-2 bg-white/5 border border-white/10 rounded px-4 py-2">
                            <span class="text-sm text-white break-all"><?php echo htmlspecialchars($account['email']); ?></span>
                            <form method="POST" class="shrink-0" onsubmit="return confirm('<?php echo htmlspecialchars($account['email'], ENT_QUOTES); ?> の管理者アクセスを削除しますか？');">
                                <input type="hidden" name="action" value="delete_admin_account">
                                <input type="hidden" name="account_id" value="<?php echo (int)$account['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <button type="submit" class="text-[10px] bg-red-900/30 hover:bg-red-800/50 text-red-200 border border-red-900/50 px-2 py-1 rounded transition-colors tracking-wider">削除</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST" class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <input type="hidden" name="action" value="add_admin_account">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="email" name="new_account_email" placeholder="追加する管理者アドレス" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50 flex-1">

                    <div class="relative flex-1">
                        <input type="password" name="new_account_password" id="new_account_password" placeholder="パスワード（8文字以上）" required minlength="8" class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50 pr-10">
                        <button type="button" onclick="togglePasswordVisibility('new_account_password', 'eye-icon-new-account')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                            <svg id="eye-icon-new-account" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                        </button>
                    </div>

                    <button type="submit" class="bg-white/10 hover:bg-white/20 text-white py-2 px-6 rounded tracking-widest text-sm border border-white/30 shrink-0">ADD</button>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
                <!-- 管理者メールアドレス変更 -->
                <div class="bg-black/30 border border-white/10 rounded-xl backdrop-blur-sm p-6 w-full">
                    <h3 class="text-lg tracking-wider mb-4">Change Admin Email</h3>
                    <form method="POST" class="flex flex-col gap-4">
                        <input type="hidden" name="action" value="change_admin_email">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        
                        <p class="text-sm text-gray-400 mb-2">現在のメールアドレス: <br><span class="text-white font-medium"><?php echo htmlspecialchars($currentAdminEmail); ?></span></p>
                        
                        <input type="email" name="new_admin_email" placeholder="新しい管理者メールアドレス" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50 w-full">
                        
                        <div class="relative w-full">
                            <input type="password" name="admin_password_confirm" id="admin_password_confirm" placeholder="現在の管理者パスワード" required class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50 pr-10">
                            <button type="button" onclick="togglePasswordVisibility('admin_password_confirm', 'eye-icon-admin-confirm')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                                <svg id="eye-icon-admin-confirm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                        
                        <button type="submit" class="mt-2 bg-white/10 hover:bg-white/20 text-white py-2 rounded tracking-widest text-sm border border-white/30 w-full sm:w-48 self-start">CHANGE EMAIL</button>
                    </form>
                </div>

                <!-- 管理者パスワード変更 -->
                <div class="bg-black/30 border border-white/10 rounded-xl backdrop-blur-sm p-6 w-full">
                    <h3 class="text-lg tracking-wider mb-4">Change Admin Password</h3>
                    <form method="POST" class="flex flex-col gap-4">
                        <input type="hidden" name="action" value="change_admin_password">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        
                        <div class="relative w-full">
                            <input type="password" name="current_admin_password" id="current_admin_password" placeholder="現在の管理者パスワード" required class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50 pr-10">
                            <button type="button" onclick="togglePasswordVisibility('current_admin_password', 'eye-icon-admin-current')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                                <svg id="eye-icon-admin-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                        
                        <div class="relative w-full">
                            <input type="password" name="new_admin_password" id="new_admin_password" placeholder="新しいパスワード" required class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50 pr-10">
                            <button type="button" onclick="togglePasswordVisibility('new_admin_password', 'eye-icon-admin-new')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                                <svg id="eye-icon-admin-new" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>

                        <div class="relative w-full">
                            <input type="password" name="confirm_admin_password" id="confirm_admin_password" placeholder="新しいパスワードの確認" required class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50 pr-10">
                            <button type="button" onclick="togglePasswordVisibility('confirm_admin_password', 'eye-icon-admin-new-confirm')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                                <svg id="eye-icon-admin-new-confirm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                        
                        <button type="submit" class="mt-2 bg-white/10 hover:bg-white/20 text-white py-2 rounded tracking-widest text-sm border border-white/30 w-full sm:w-48 self-start">CHANGE PASSWORD</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="mt-8 mb-8 text-center">
                <a href="index.php" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-2.5 rounded-full tracking-[0.2em] text-xs transition-all duration-300 inline-block">BACK TO HOME</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Adminログイン情報の保存・自動入力（ブラウザのlocalStorageにのみ保存。サーバーには送信しません）
        (function() {
            const emailInput = document.querySelector('input[name="admin_email"]');
            const passwordInput = document.getElementById('admin_password');
            const rememberEmail = document.getElementById('rememberAdminEmail');
            const rememberEmailBox = document.getElementById('rememberAdminEmailBox');
            const rememberEmailCheckIcon = document.getElementById('rememberAdminEmailCheckIcon');
            const rememberPassword = document.getElementById('rememberAdminPassword');
            const rememberPasswordBox = document.getElementById('rememberAdminPasswordBox');
            const rememberPasswordCheckIcon = document.getElementById('rememberAdminPasswordCheckIcon');
            const loginForm = emailInput ? emailInput.closest('form') : null;
            if (!emailInput || !passwordInput || !rememberEmail || !rememberPassword || !loginForm) return;

            function syncVisual(checkbox, box, icon) {
                if (checkbox.checked) {
                    box.classList.add('bg-white', 'border-white');
                    icon.classList.remove('hidden');
                } else {
                    box.classList.remove('bg-white', 'border-white');
                    icon.classList.add('hidden');
                }
            }
            rememberEmail.addEventListener('change', () => syncVisual(rememberEmail, rememberEmailBox, rememberEmailCheckIcon));
            rememberPassword.addEventListener('change', () => syncVisual(rememberPassword, rememberPasswordBox, rememberPasswordCheckIcon));

            const savedEmail = localStorage.getItem('lw_saved_admin_email');
            const savedPassword = localStorage.getItem('lw_saved_admin_password');
            if (savedEmail !== null) {
                emailInput.value = savedEmail;
                rememberEmail.checked = true;
            }
            if (savedPassword !== null) {
                passwordInput.value = savedPassword;
                rememberPassword.checked = true;
            }
            syncVisual(rememberEmail, rememberEmailBox, rememberEmailCheckIcon);
            syncVisual(rememberPassword, rememberPasswordBox, rememberPasswordCheckIcon);

            loginForm.addEventListener('submit', function() {
                if (rememberEmail.checked) {
                    localStorage.setItem('lw_saved_admin_email', emailInput.value);
                } else {
                    localStorage.removeItem('lw_saved_admin_email');
                }
                if (rememberPassword.checked) {
                    localStorage.setItem('lw_saved_admin_password', passwordInput.value);
                } else {
                    localStorage.removeItem('lw_saved_admin_password');
                }
            });
        })();

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

        <?php if ($admin_login_success): ?>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                document.body.classList.add('animate-fade-out');
                setTimeout(() => {
                    window.location.href = 'admin.php'; // ダッシュボードへ
                }, 800);
            }, 800);
        });
        <?php endif; ?>

        const canvas = document.getElementById('waveCanvas');
        const ctx = canvas.getContext('2d');
        let width, height, time = 0;

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
<!-- バックグラウンド・画面ロック延命用サイレント音声（隠し要素。muted指定はしない＝無音の中身を再生することで背景オーディオ扱いにする） -->
<audio id="lw-bg-keepalive" src="bg-keepalive.m4a" loop playsinline preload="auto" style="position:fixed;width:1px;height:1px;opacity:0;pointer-events:none;left:-9999px;top:-9999px;"></audio>
<script>
    (function() {
        var a = document.getElementById('lw-bg-keepalive');
        if (!a) return;
        a.volume = 1.0;
        function tryPlay() { a.play().catch(function() {}); }
        tryPlay();
        document.addEventListener('click', tryPlay, { once: true });
        document.addEventListener('touchstart', tryPlay, { once: true });
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') tryPlay();
        });
    })();
</script>
</body>
</html>