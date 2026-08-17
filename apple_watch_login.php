<?php
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: w_h.php"); // ログイン済みならWatchのHOMEへ
    exit;
}

$error = '';
$login_success = false;
$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die('CSRF token validation failed. Invalid request.'); // Simplified error for watch
    }

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            writeLog($pdo, $user['id'], 'login', 'User logged in (Apple Watch).');
            $login_success = true;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - Watch</title>
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
            font-size: 14px; /* Slightly larger for readability */
        }
        .container {
            width: 100%;
            max-width: 200px; /* Constrain for watch screen */
            text-align: center;
        }
        h1 {
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 300;
            letter-spacing: 1px;
        }
        input[type="email"],
        input[type="password"] {
            width: calc(100% - 20px);
            padding: 8px 10px;
            margin-bottom: 10px;
            border: 1px solid #333;
            border-radius: 5px;
            background-color: #1a1a1a;
            color: #fff;
            font-size: 14px;
            -webkit-appearance: none; /* For iOS styling */
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007aff; /* Apple blue */
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
            color: #ff3b30; /* Apple red */
            font-size: 12px;
            margin-bottom: 10px;
        }
        .success {
            color: #34c759; /* Apple green */
            font-size: 12px;
            margin-bottom: 10px;
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
        <h1>LOGIN</h1>
        <?php if ($login_success): ?>
            <p class="success">Login successful!</p>
            <script>
                setTimeout(() => {
                    window.location.href = 'w_h.php'; // ログイン後はWatchのHOMEへ
                }, 1000);
            </script>
        <?php else: ?>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
            <a href="apple_watch_register.php" class="link">Register</a>
            <a href="apple_watch_forgot_password.php" class="link">Forgot Password?</a>
        <?php endif; ?>
    </div></body>
</html>