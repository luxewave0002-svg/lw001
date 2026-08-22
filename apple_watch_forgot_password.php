<?php
require_once 'db.php';

$csrfToken = generateCsrfToken();
$message = '';
$message_class = '';
$new_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die('CSRF token validation failed. Invalid request.');
    }

    $email = $_POST['email'] ?? '';

    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $new_password = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
            $newHash = password_hash($new_password, PASSWORD_DEFAULT);

            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($updateStmt->execute([$newHash, $user['id']])) {
                $message = 'Password reset. New password below.';
                $message_class = 'success';
                writeLog($pdo, $user['id'], 'password_reset', 'User reset password (Apple Watch).');
            } else {
                $message = 'Failed to reset password.';
                $message_class = 'error';
            }
        } else {
            $message = 'Email not registered.';
            $message_class = 'error';
        }
    } else {
        $message = 'Please enter your email.';
        $message_class = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Forgot Password - Watch</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #000;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 10px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .container {
            width: 100%;
            max-width: 200px;
            text-align: center;
        }
        h1 {
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 300;
            letter-spacing: 1px;
        }
        input[type="email"] {
            width: calc(100% - 20px);
            padding: 8px 10px;
            margin-bottom: 10px;
            border: 1px solid #333;
            border-radius: 5px;
            background-color: #1a1a1a;
            color: #fff;
            font-size: 14px;
            -webkit-appearance: none;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007aff;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            -webkit-appearance: none;
        }
        button:hover {
            background-color: #005bb5;
        }
        .error {
            color: #ff3b30;
            font-size: 12px;
            margin-bottom: 10px;
        }
        .success {
            color: #34c759;
            font-size: 12px;
            margin-bottom: 10px;
        }
        .new-password-box {
            background-color: #1a1a1a;
            border: 1px solid #34c759;
            padding: 10px;
            border-radius: 8px;
            margin-top: 15px;
            word-break: break-all;
        }
        .new-password-label {
            font-size: 10px;
            color: #888;
            margin-bottom: 5px;
        }
        .new-password-value {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .note {
            font-size: 10px;
            color: #888;
            margin-top: 10px;
        }
        .link {
            color: #007aff;
            text-decoration: none;
            font-size: 12px;
            margin-top: 15px;
            display: block;
        }
    </style>
</head>
<body>
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

    <div class="container">
        <h1>RESET PASSWORD</h1>
        <?php if ($new_password): ?>
            <p class="success"><?php echo htmlspecialchars($message); ?></p>
            <div class="new-password-box">
                <p class="new-password-label">New Temporary Password:</p>
                <p class="new-password-value"><?php echo htmlspecialchars($new_password); ?></p>
            </div>
            <p class="note">Please copy this password, log in, and change it in your dashboard.</p>
            <a href="apple_watch_login.php" class="link">Go to Login</a>
        <?php else: ?>
            <?php if ($message): ?>
                <p class="error"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <p class="note">Enter your registered email to reset your password.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="email" name="email" placeholder="Email" required>
                <button type="submit">Reset Password</button>
            </form>
            <a href="apple_watch_login.php" class="link">Back to Login</a>
        <?php endif; ?>
    </div>
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