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
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
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
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $isSecure ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'luxewave.jp';
    $verifyUrl = $scheme . $host . '/verify_email.php?token=' . urlencode($token);

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
function isLevelUnlocked($pdo, $userId, $level) {
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
        return false;
    }

    // 再発行でパスワードが変わった場合も、以前の解除状態は無効にする
    $fingerprint = $_SESSION['unlocked_levels'][$level] ?? '';
    if (!is_string($fingerprint) || !hash_equals(levelUnlockFingerprint($currentPassword), $fingerprint)) {
        unset($_SESSION['unlocked_levels'][$level]);
        return false;
    }

    return true;
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