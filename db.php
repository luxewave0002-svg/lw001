<?php
// セッションファイルを専用ディレクトリに保存し、他サイトの影響で消されるのを防ぐ
$session_dir = __DIR__ . '/sessions';
if (!file_exists($session_dir)) mkdir($session_dir, 0777, true);
session_save_path($session_dir);

// 同じドメインの他アプリ（luxewave.jp/switchbot など）が素の session_start() で
// PHPSESSID（path=/・有効期限なし）を発行し、このアプリのセッションCookieを上書きしてしまうため、
// 専用のセッション名を使って完全に分離する
$app_session_name = 'LUXEWAVE_SESSID';
session_name($app_session_name);

// HTTPS通信かどうかを判定
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// 絶対にログアウトさせないためのセッション設定 (10年間)
ini_set('session.gc_maxlifetime', 315360000);
ini_set('session.use_strict_mode', 1);
session_set_cookie_params([
    'lifetime' => 315360000, 'path' => '/', 'secure' => $isSecure, 'httponly' => true, 'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) {
    // 旧セッション名(PHPSESSID)でログイン中だったユーザーをそのまま引き継ぐ（移行用）
    // 保存先に実体のあるIDのみ引き継ぐので、外部から任意のIDを持ち込まれることはない
    if (empty($_COOKIE[$app_session_name]) && !empty($_COOKIE['PHPSESSID'])
        && preg_match('/^[A-Za-z0-9,\-]{22,128}$/', $_COOKIE['PHPSESSID'])
        && file_exists($session_dir . '/sess_' . $_COOKIE['PHPSESSID'])) {
        session_id($_COOKIE['PHPSESSID']);
    }
    session_start();
}

// データベース接続設定 (SQLiteを使用するため、特別なサーバー構築は不要です)
$db_file = __DIR__ . '/luxe_wave.sqlite';

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 古いテーブル構造（emailカラムがない状態）を検知して自動的に再構築する処理
    $stmt = $pdo->query("PRAGMA table_info(users)");
    $columns = $stmt->fetchAll();
    $hasEmail = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'email') $hasEmail = true;
    }
    // ユーザーテーブルが存在するのにemailカラムがない場合は、全テーブルを削除して作り直す
    if (!$hasEmail && count($columns) > 0) {
        $pdo->exec("DROP TABLE IF EXISTS logs");
        $pdo->exec("DROP TABLE IF EXISTS devices");
        $pdo->exec("DROP TABLE IF EXISTS users");
    }

    // デバイステーブルに sort_order カラムがない場合は自動追加する
    $stmt = $pdo->query("PRAGMA table_info(devices)");
    $columns = $stmt->fetchAll();
    $hasSortOrder = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'sort_order') $hasSortOrder = true;
    }
    if (!$hasSortOrder && count($columns) > 0) {
        $pdo->exec("ALTER TABLE devices ADD COLUMN sort_order INTEGER DEFAULT 0");
    }

    // デバイステーブルに icon, color カラムがない場合は自動追加する
    $hasIcon = false;
    $hasColor = false;
    $hasLevel = false;
    $hasLevelUpdatedAt = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'icon') $hasIcon = true;
        if ($col['name'] === 'color') $hasColor = true;
        if ($col['name'] === 'level') $hasLevel = true;
        if ($col['name'] === 'level_updated_at') $hasLevelUpdatedAt = true;
    }
    if (!$hasIcon && count($columns) > 0) {
        $pdo->exec("ALTER TABLE devices ADD COLUMN icon TEXT DEFAULT '🔌'");
    }
    if (!$hasColor && count($columns) > 0) {
        $pdo->exec("ALTER TABLE devices ADD COLUMN color TEXT DEFAULT 'text-gray-100'");
    }
    if (!$hasLevel && count($columns) > 0) {
        $pdo->exec("ALTER TABLE devices ADD COLUMN level INTEGER DEFAULT 1");
    }
    if (!$hasLevelUpdatedAt && count($columns) > 0) {
        $pdo->exec("ALTER TABLE devices ADD COLUMN level_updated_at DATETIME");
    }

    // ユーザーテーブルの作成
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL
        )
    ");

    // メール認証用カラムがなければ追加する（既存ユーザーは既定で「未認証」扱いにはしない＝認証済み扱いにして影響を出さない）
    $stmt = $pdo->query("PRAGMA table_info(users)");
    $userColumns = $stmt->fetchAll();
    $hasEmailVerified = false;
    $hasVerifyToken = false;
    $hasVerifyTokenExpires = false;
    foreach ($userColumns as $col) {
        if ($col['name'] === 'email_verified') $hasEmailVerified = true;
        if ($col['name'] === 'verify_token') $hasVerifyToken = true;
        if ($col['name'] === 'verify_token_expires_at') $hasVerifyTokenExpires = true;
    }
    if (!$hasEmailVerified) {
        // 既存ユーザーは移行前から使えていたアカウントなので、影響が出ないよう既定値は「認証済み(1)」にする
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified INTEGER NOT NULL DEFAULT 1");
    }
    if (!$hasVerifyToken) {
        $pdo->exec("ALTER TABLE users ADD COLUMN verify_token TEXT");
    }
    if (!$hasVerifyTokenExpires) {
        $pdo->exec("ALTER TABLE users ADD COLUMN verify_token_expires_at DATETIME");
    }

    // 単一端末ログイン制御用カラム（新しい端末でログインするたびに数値を増やし、古い端末を強制ログアウトさせる）
    $hasSessionVersion = false;
    foreach ($userColumns as $col) {
        if ($col['name'] === 'session_version') $hasSessionVersion = true;
    }
    if (!$hasSessionVersion) {
        $pdo->exec("ALTER TABLE users ADD COLUMN session_version INTEGER NOT NULL DEFAULT 0");
    }

    // 管理者が任意で設定できるニックネーム用カラム
    $hasNickname = false;
    foreach ($userColumns as $col) {
        if ($col['name'] === 'nickname') $hasNickname = true;
    }
    if (!$hasNickname) {
        $pdo->exec("ALTER TABLE users ADD COLUMN nickname TEXT");
    }

    // デバイステーブルの作成（ユーザーと紐付けます）
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            device_name TEXT NOT NULL,
            device_id TEXT NOT NULL,
            token TEXT NOT NULL,
            secret TEXT NOT NULL,
            icon TEXT DEFAULT '🔌',
            color TEXT DEFAULT 'text-gray-100',
            level INTEGER DEFAULT 1,
            level_updated_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // 操作履歴テーブルの作成
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            details TEXT,
            created_at DATETIME NOT NULL,
            ip_address TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // logsテーブルに ip_address カラムがない場合は自動追加する
    $stmt = $pdo->query("PRAGMA table_info(logs)");
    $columns = $stmt->fetchAll();
    $hasIpAddress = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'ip_address') $hasIpAddress = true;
    }
    if (!$hasIpAddress && count($columns) > 0) {
        $pdo->exec("ALTER TABLE logs ADD COLUMN ip_address TEXT");
    }

    // 設定テーブルの作成
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )
    ");

    // 初期管理者パスワードの設定（存在しない場合）
    $stmt = $pdo->query("SELECT value FROM settings WHERE key = 'admin_password'");
    if (!$stmt->fetch()) {
        $initialAdminPassword = 'luxewave2025';
        $hashedPassword = password_hash($initialAdminPassword, PASSWORD_DEFAULT);
        $insertStmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES ('admin_password', ?)");
        $insertStmt->execute([$hashedPassword]);
    }

    // 追加の管理者アカウント（settingsのadmin_email/admin_passwordはマスター管理者として別管理）
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            created_at DATETIME NOT NULL
        )
    ");

    // Home画面のLevel別パスワード（登録アドレス1つにつき1個発行）のテーブル
    // 削除しても行は残し、password を NULL・revoked_at に日時を入れて「削除済み（要再発行）」として記録する
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS level_passwords (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            level INTEGER NOT NULL,
            password TEXT,
            created_at DATETIME NOT NULL,
            revoked_at DATETIME,
            UNIQUE(user_id, level),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // 旧構造（password NOT NULL・revoked_at なし）の場合はテーブルを作り直す
    $stmt = $pdo->query("PRAGMA table_info(level_passwords)");
    $columns = $stmt->fetchAll();
    $hasRevokedAt = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'revoked_at') $hasRevokedAt = true;
    }
    if (!$hasRevokedAt && count($columns) > 0) {
        $pdo->exec("
            CREATE TABLE level_passwords_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                level INTEGER NOT NULL,
                password TEXT,
                created_at DATETIME NOT NULL,
                revoked_at DATETIME,
                UNIQUE(user_id, level),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        $pdo->exec("INSERT INTO level_passwords_new (id, user_id, level, password, created_at) SELECT id, user_id, level, password, created_at FROM level_passwords");
        $pdo->exec("DROP TABLE level_passwords");
        $pdo->exec("ALTER TABLE level_passwords_new RENAME TO level_passwords");
    }

    // Level.1のパスワードを一度も発行されていないユーザーに自動発行する（削除済みの記録がある場合は再発行しない）
    $stmt = $pdo->query("SELECT id FROM users WHERE id NOT IN (SELECT user_id FROM level_passwords WHERE level = 1)");
    $usersWithoutLevel1 = $stmt->fetchAll();
    if ($usersWithoutLevel1) {
        $insertLevelPw = $pdo->prepare("INSERT INTO level_passwords (user_id, level, password, created_at) VALUES (?, 1, ?, ?)");
        foreach ($usersWithoutLevel1 as $u) {
            $insertLevelPw->execute([$u['id'], generateLevelPassword(), date('Y-m-d H:i:s')]);
        }
    }

    // 招待コードのテーブル（新規登録には既存ユーザーが発行したコードが必要）
    // 再発行しても行は消さず、古いコードは revoked_at を立てて履歴として残す
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invite_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            issued_to_user_id INTEGER,
            used_by_user_id INTEGER,
            created_at DATETIME NOT NULL,
            used_at DATETIME,
            revoked_at DATETIME,
            FOREIGN KEY (issued_to_user_id) REFERENCES users(id),
            FOREIGN KEY (used_by_user_id) REFERENCES users(id)
        )
    ");

    // invite_codes に revoked_at カラムがない場合は自動追加する
    $stmt = $pdo->query("PRAGMA table_info(invite_codes)");
    $columns = $stmt->fetchAll();
    $hasInviteRevokedAt = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'revoked_at') $hasInviteRevokedAt = true;
    }
    if (!$hasInviteRevokedAt && count($columns) > 0) {
        $pdo->exec("ALTER TABLE invite_codes ADD COLUMN revoked_at DATETIME");
    }

    // 招待コードを持たないユーザーに1つ自動発行する
    $stmt = $pdo->query("SELECT id FROM users WHERE id NOT IN (SELECT issued_to_user_id FROM invite_codes WHERE issued_to_user_id IS NOT NULL)");
    $usersWithoutInvite = $stmt->fetchAll();
    if ($usersWithoutInvite) {
        $insertInvite = $pdo->prepare("INSERT INTO invite_codes (code, issued_to_user_id, created_at) VALUES (?, ?, ?)");
        foreach ($usersWithoutInvite as $u) {
            $insertInvite->execute([generateInviteCode(), $u['id'], date('Y-m-d H:i:s')]);
        }
    }
    // 永続ログイン用トークンテーブル（ブラウザを閉じても・バックグラウンドでセッションが切れても自動再ログインするため）
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            selector TEXT UNIQUE NOT NULL,
            token_hash TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // ログイン失敗回数の記録テーブル（ブルートフォース対策）
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier TEXT NOT NULL,
            created_at DATETIME NOT NULL
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_identifier ON login_attempts(identifier, created_at)");

    // 「技術発生」の状態をサーバー側で管理するためのテーブル（クライアント側の状態消失に依存しないようにするため）
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS level_activation (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            level INTEGER NOT NULL,
            started_at DATETIME,
            UNIQUE(user_id, level)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS level_activation_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            level INTEGER NOT NULL,
            started_at DATETIME NOT NULL,
            ended_at DATETIME NOT NULL,
            ended_reason TEXT NOT NULL DEFAULT 'manual'
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_level_activation_history_user_level ON level_activation_history(user_id, level, started_at)");

    // Limitedレベル（CODE入力で出現する特別レベル）のアクセス権テーブル
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_limited_access (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            level INTEGER NOT NULL,
            unlocked_at DATETIME NOT NULL,
            UNIQUE(user_id, level)
        )
    ");
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// 永続ログイン（Remember Me）: セッションが切れていてもCookieが有効なら自動的に再ログインする
if (!isset($_SESSION['user_id']) && !empty($_COOKIE['lw_remember'])) {
    $parts = explode(':', $_COOKIE['lw_remember'], 2);
    if (count($parts) === 2) {
        [$selector, $validator] = $parts;
        $stmt = $pdo->prepare("SELECT rt.id, rt.user_id, rt.token_hash, rt.expires_at, u.email FROM remember_tokens rt JOIN users u ON u.id = rt.user_id WHERE rt.selector = ?");
        $stmt->execute([$selector]);
        $rememberRow = $stmt->fetch();

        if ($rememberRow && strtotime($rememberRow['expires_at']) > time() && hash_equals($rememberRow['token_hash'], hash('sha256', $validator))) {
            $_SESSION['user_id'] = $rememberRow['user_id'];
            $_SESSION['email'] = $rememberRow['email'];
            // 単一端末ログイン制御：現在の世代番号をそのまま引き継ぐ（他端末をキックするわけではない）
            $verStmt = $pdo->prepare("SELECT session_version FROM users WHERE id = ?");
            $verStmt->execute([$rememberRow['user_id']]);
            $_SESSION['session_version'] = (int)$verStmt->fetchColumn();
            // 使用済みトークンはローテーション（盗用対策として毎回新しい値に差し替える）
            $pdo->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$rememberRow['id']]);
            issueRememberCookie($pdo, $rememberRow['user_id']);
        } else {
            // 無効・期限切れのCookieは破棄する
            setcookie('lw_remember', '', time() - 3600, '/', '', $isSecure, true);
        }
    }
}

// 他の端末で新しくログインされていないか、毎回のアクセスで確認する（単一端末ログイン制御）
enforceSingleDeviceLogin($pdo);

// 永続ログイン用Cookieを発行する（1年間有効。バックグラウンドでセッションが切れても自動再ログインするために使う）
function issueRememberCookie($pdo, $userId) {
    global $isSecure;
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 31536000); // 1年

    $pdo->prepare("INSERT INTO remember_tokens (user_id, selector, token_hash, created_at, expires_at) VALUES (?, ?, ?, ?, ?)")
        ->execute([$userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s'), $expiresAt]);

    setcookie('lw_remember', $selector . ':' . $validator, [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// 「技術発生」の現在の状態を取得する（started_atがあればON中、nullならOFF）
function getLevelActivation($pdo, $userId, $level) {
    $stmt = $pdo->prepare("SELECT started_at FROM level_activation WHERE user_id = ? AND level = ?");
    $stmt->execute([$userId, $level]);
    $row = $stmt->fetch();
    return $row ? $row['started_at'] : null;
}

// 「技術発生」をONにする（既にON中なら何もしない＝多重POSTでも安全）
function startLevelActivation($pdo, $userId, $level) {
    $existing = getLevelActivation($pdo, $userId, $level);
    if ($existing !== null && $existing !== false) {
        return $existing;
    }
    $startedAt = date('Y-m-d H:i:s');
    $pdo->prepare("
        INSERT INTO level_activation (user_id, level, started_at) VALUES (?, ?, ?)
        ON CONFLICT(user_id, level) DO UPDATE SET started_at = excluded.started_at
    ")->execute([$userId, $level, $startedAt]);
    return $startedAt;
}

// 「技術発生」をOFFにし、履歴に確定記録として残す
function stopLevelActivation($pdo, $userId, $level, $reason = 'manual') {
    $startedAt = getLevelActivation($pdo, $userId, $level);
    if ($startedAt === null || $startedAt === false) {
        return false;
    }
    $endedAt = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO level_activation_history (user_id, level, started_at, ended_at, ended_reason) VALUES (?, ?, ?, ?, ?)")
        ->execute([$userId, $level, $startedAt, $endedAt, $reason]);
    $pdo->prepare("UPDATE level_activation SET started_at = NULL WHERE user_id = ? AND level = ?")
        ->execute([$userId, $level]);
    return true;
}

// 直近の履歴を取得する（新しい順）
function getLevelActivationHistory($pdo, $userId, $level, $limit = 15) {
    $stmt = $pdo->prepare("SELECT started_at, ended_at, ended_reason FROM level_activation_history WHERE user_id = ? AND level = ? ORDER BY started_at DESC LIMIT ?");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $level, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Limitedレベル（CODE入力で出現する特別レベル）の設定
// level番号を内部的に流用する: 5 = Limited.1、6 = Limited.2
define('LIMITED_LEVELS', [5 => 'Limited.1', 6 => 'Limited.2']);

// そのLevel番号がLimitedレベルかどうか
function isLimitedLevel($level) {
    return array_key_exists((int)$level, LIMITED_LEVELS);
}

// Limitedレベルの表示名を取得
function getLimitedLevelLabel($level) {
    return LIMITED_LEVELS[(int)$level] ?? ('Level.' . $level);
}

// ユーザーがそのLimitedレベルを解除済みか確認する
function isLimitedLevelUnlocked($pdo, $userId, $level) {
    if (!$userId) return false;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_limited_access WHERE user_id = ? AND level = ?");
    $stmt->execute([$userId, $level]);
    return (int)$stmt->fetchColumn() > 0;
}

// ユーザーがログイン中にアクセス可能なLimitedレベル一覧を取得する
function getUnlockedLimitedLevels($pdo, $userId) {
    if (!$userId) return [];
    $stmt = $pdo->prepare("SELECT level FROM user_limited_access WHERE user_id = ?");
    $stmt->execute([$userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// CODEを照合し、一致すれば該当Limitedレベルを解除する。戻り値は解除したlevel番号、失敗時はfalse
function redeemLimitedCode($pdo, $userId, $inputCode) {
    $inputCode = trim($inputCode);
    if ($inputCode === '') return false;

    foreach (array_keys(LIMITED_LEVELS) as $level) {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute(['limited_code_' . $level]);
        $row = $stmt->fetch();
        if ($row && $row['value'] !== '' && hash_equals($row['value'], $inputCode)) {
            $pdo->prepare("
                INSERT INTO user_limited_access (user_id, level, unlocked_at) VALUES (?, ?, datetime('now'))
                ON CONFLICT(user_id, level) DO NOTHING
            ")->execute([$userId, $level]);
            return $level;
        }
    }
    return false;
}

// ログ記録用ヘルパー関数
function writeLog($pdo, $userId, $action, $details) {
    $now = date('Y-m-d H:i:s'); // サーバーの時刻
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }

    $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, details, created_at, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $details, $now, $ipAddress]);
}

// ログイン必須ページ用のガード関数（未ログインなら指定ページへリダイレクト）
function requireLogin($pdo, $redirectTo = 'login.php') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: $redirectTo");
        exit;
    }
}

// ログアウト処理（セッションに加えて、永続ログイン用Cookie・DBトークンも確実に破棄する）
function logoutUser($pdo) {
    global $isSecure;

    if (!empty($_COOKIE['lw_remember'])) {
        $parts = explode(':', $_COOKIE['lw_remember'], 2);
        if (count($parts) === 2) {
            $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?")->execute([$parts[0]]);
        }
    }
    setcookie('lw_remember', '', time() - 3600, '/', '', $isSecure, true);

    // 解除済みLevelのCookieも破棄する
    foreach ($_COOKIE as $cookieName => $cookieValue) {
        if (strpos($cookieName, 'level_unlock_') === 0) {
            setcookie($cookieName, '', time() - 3600, '/', '', $isSecure, true);
        }
    }

    session_destroy();
}

// ブルートフォース対策：直近の失敗回数が上限を超えていないか確認する
function tooManyFailedAttempts($pdo, $identifier, $maxAttempts = 5, $windowMinutes = 15) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at > datetime('now', ?)");
    $stmt->execute([$identifier, '-' . (int)$windowMinutes . ' minutes']);
    return (int)$stmt->fetchColumn() >= $maxAttempts;
}

// ログイン失敗を記録する
function recordFailedAttempt($pdo, $identifier) {
    $pdo->prepare("INSERT INTO login_attempts (identifier, created_at) VALUES (?, datetime('now'))")
        ->execute([$identifier]);
}

// ログイン成功時に、それまでの失敗記録をクリアする
function clearFailedAttempts($pdo, $identifier) {
    $pdo->prepare("DELETE FROM login_attempts WHERE identifier = ?")->execute([$identifier]);
}

// 新しい端末でのログインを「最新」として記録し、他の端末を次回アクセス時に強制ログアウトさせる
function registerNewLoginDevice($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?");
    $stmt->execute([$userId]);

    $newVersion = (int)$pdo->query("SELECT session_version FROM users WHERE id = " . (int)$userId)->fetchColumn();
    $_SESSION['session_version'] = $newVersion;

    // 他端末の永続ログインCookie（remember_tokens）は全て無効化する。今回発行する分だけ残す
    $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);
}

// 現在のセッションが最新端末のものか確認する。他端末で新しくログインされていれば強制ログアウトする
function enforceSingleDeviceLogin($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    $stmt = $pdo->prepare("SELECT session_version FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentVersion = $stmt->fetchColumn();

    if ($currentVersion === false || (int)($_SESSION['session_version'] ?? -1) !== (int)$currentVersion) {
        logoutUser($pdo);
    }
}

// メール認証トークンを生成し、DBに保存する（有効期限24時間）
function generateVerifyToken($pdo, $userId) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);
    $stmt = $pdo->prepare("UPDATE users SET verify_token = ?, verify_token_expires_at = ? WHERE id = ?");
    $stmt->execute([$token, $expiresAt, $userId]);
    return $token;
}

// 認証メールを送信する（サーバー標準のmail()関数を利用。追加のSMTP設定は不要）
function sendVerificationEmail($email, $token) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $isSecure ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'luxewave.jp';

    // 現在の設置ディレクトリ（例: /test）を自動検出する。本番ルートに移動しても自動で正しいURLになる
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/register.php'), '/');
    $verifyUrl = $scheme . $host . $basePath . '/verify_email.php?token=' . urlencode($token);

    $subject = '【LUXE WAVE】メールアドレスの確認';
    $body = "LUXE WAVEにご登録いただきありがとうございます。\n\n"
          . "以下のリンクをクリックして、メールアドレスの確認を完了してください。\n"
          . "（このリンクの有効期限は24時間です）\n\n"
          . $verifyUrl . "\n\n"
          . "心当たりがない場合は、このメールを破棄してください。\n\n"
          . "LUXE WAVE";

    $headers = "From: LUXE WAVE <admin@luxewave.jp>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($email, mb_encode_mimeheader($subject, 'UTF-8'), $body, $headers);
}

// 仮パスワード再発行メールを送信する
function sendTempPasswordEmail($email, $tempPassword) {
    $subject = '【LUXE WAVE】パスワード再発行のお知らせ';
    $body = "パスワードの再発行リクエストを受け付けました。\n\n"
          . "以下が新しい仮パスワードです。ログイン後、お手数ですが速やかにパスワードの変更をお願いいたします。\n\n"
          . "仮パスワード: " . $tempPassword . "\n\n"
          . "心当たりがない場合は、このメールを破棄してください。第三者にパスワードが渡った可能性がある場合は、"
          . "ログイン後すぐにパスワードを変更してください。\n\n"
          . "LUXE WAVE";

    $headers = "From: LUXE WAVE <admin@luxewave.jp>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($email, mb_encode_mimeheader($subject, 'UTF-8'), $body, $headers);
}

// Levelパスワード（アクセスコード）生成関数
function generateLevelPassword() {
    return substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
}

// セッションに保存する解除済みの印（パスワードそのものは保存しない）
function levelUnlockFingerprint($password) {
    return hash('sha256', (string)$password);
}

// 有効なLevelパスワード（削除済みを除く）を取得する関数。無ければ null を返す
function getLevelPassword($pdo, $userId, $level) {
    if (empty($userId)) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT password FROM level_passwords WHERE user_id = ? AND level = ? AND revoked_at IS NULL");
    $stmt->execute([$userId, (int)$level]);
    $password = $stmt->fetchColumn();

    return ($password === false || $password === null || $password === '') ? null : $password;
}

// そのユーザーがLevelを閲覧できるかを判定する関数
// 解除状態は毎回DBのパスワードと照合するため、管理画面で削除・再発行されると自動的に再ロックされる
// セッションが切れていても、Cookie（level_unlock_N）が有効なら解除状態を維持する
function isLevelUnlocked($pdo, $userId, $level) {
    global $isSecure;
    $level = (int)$level;

    // 発行記録（削除済みを含む）が1件も無いLevelは、これまで通りロックしない
    $gateStmt = $pdo->prepare("SELECT COUNT(*) FROM level_passwords WHERE level = ?");
    $gateStmt->execute([$level]);
    if ((int)$gateStmt->fetchColumn() === 0) {
        return true;
    }

    // パスワードが未発行・削除済みなら閲覧不可（＝再度パスワード入力画面を出す）
    $currentPassword = getLevelPassword($pdo, $userId, $level);
    if ($currentPassword === null) {
        unset($_SESSION['unlocked_levels'][$level]);
        setcookie('level_unlock_' . $level, '', time() - 3600, '/', '', $isSecure, true);
        return false;
    }

    $expectedFingerprint = levelUnlockFingerprint($currentPassword);
    $cookieName = 'level_unlock_' . $level;

    // セッションに解除記録があればそれを優先
    $sessionFingerprint = $_SESSION['unlocked_levels'][$level] ?? '';
    if (is_string($sessionFingerprint) && $sessionFingerprint !== '' && hash_equals($expectedFingerprint, $sessionFingerprint)) {
        return true;
    }

    // セッションが切れていても、Cookieが一致すれば解除状態とみなし、セッションにも復元する
    $cookieFingerprint = $_COOKIE[$cookieName] ?? '';
    if (is_string($cookieFingerprint) && $cookieFingerprint !== '' && hash_equals($expectedFingerprint, $cookieFingerprint)) {
        $_SESSION['unlocked_levels'][$level] = $expectedFingerprint;
        return true;
    }

    unset($_SESSION['unlocked_levels'][$level]);
    return false;
}

// 招待コード生成関数
function generateInviteCode() {
    return substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10);
}

// CSRFトークン生成関数
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRFトークン検証関数
function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

// スマホ判定関数
function isMobile() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/iPhone|Android.*Mobile|Windows Phone|BlackBerry/i', $userAgent);
}

// 各Levelページに表示する画像のパスを取得する関数
function getImagePath($testId) {
    // アップロードされたファイルがあるか探す
    $files = glob("upload_test{$testId}.*");
    if ($files && count($files) > 0) {
        // 画像のキャッシュ（古い画像が表示される現象）を防ぐために時間を付与
        return $files[0] . '?t=' . filemtime($files[0]);
    }
    // アップロードされていない場合は初期のサンプル画像・GIFを表示
    $isEven = ((int)$testId % 2 === 0);
    return $isEven ? './S__16498701.gif' : './LW005.jpg';
}

// ファイルが動画かどうかを判定する関数
function isVideoFile($path) {
    $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION));
    return in_array($ext, ['mp4', 'mov', 'webm']);
}
?>