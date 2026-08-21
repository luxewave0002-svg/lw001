<?php
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: mobile_home.php"); // ログイン済みならHOMEへ
    exit;
}

$error = '';
$login_success = false;
$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die('CSRF token validation failed. 不正なリクエストです。');
    }

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $loginIdentifier = 'user:' . strtolower($email);

        if (tooManyFailedAttempts($pdo, $loginIdentifier)) {
            $error = 'ログイン試行回数が多いため、しばらく時間をおいてから再度お試しください。';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ((int)$user['email_verified'] !== 1) {
                    $error = 'メールアドレスの確認が完了していません。ご登録時に届いたメール内のリンクをクリックしてください。';
                } else {
                    clearFailedAttempts($pdo, $loginIdentifier);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    registerNewLoginDevice($pdo, $user['id']);
                    issueRememberCookie($pdo, $user['id']);
                    writeLog($pdo, $user['id'], 'login', 'ユーザーがログインしました (Mobile)。');
                    $login_success = true;
                }
            } else {
                recordFailedAttempt($pdo, $loginIdentifier);
                $error = 'メールアドレスまたはパスワードが間違っています。';
            }
        }
    } else {
        $error = '入力してください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ログイン - Mobile</title>
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
        body { font-family: sans-serif; font-weight: 300; background-color: #050505; }
        .fade-out { animation: fadeOut 0.8s forwards; }
        @keyframes fadeOut { to { opacity: 0; transform: scale(1.05); filter: blur(5px); } }
    </style>
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="apple-touch-icon.png?v=2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LUXE WAVE">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#000000">
</head>
<body class="text-white min-h-screen flex flex-col p-6 items-center justify-center">
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

    <div class="w-full max-w-sm">
        <h2 class="text-2xl font-light mb-8 tracking-widest text-center"><?php echo $login_success ? 'WELCOME' : 'LOGIN'; ?></h2>
        
        <?php if($login_success): ?>
            <div class="flex flex-col items-center justify-center py-10">
                <p class="text-green-400 text-lg tracking-widest text-center">LOGIN SUCCESS</p>
            </div>
            <script>setTimeout(() => { document.body.classList.add('fade-out'); setTimeout(() => { window.location.href = 'mobile_home.php'; }, 800); }, 1000);</script>
        <?php else: ?>
            <?php if($error): ?>
                <p class="text-red-400 text-xs mb-4 text-center"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST" class="flex flex-col gap-5">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="email" name="email" placeholder="Email" required class="bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-base text-white outline-none focus:border-white/50">
                <div class="relative w-full">
                    <input type="password" name="password" id="password" placeholder="Password" required class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-base text-white outline-none focus:border-white/50 pr-12">
                    <button type="button" onclick="togglePasswordVisibility('password', 'eye-icon-password')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                        <svg id="eye-icon-password" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>

                <div class="flex flex-col gap-3">
                    <label class="flex items-center gap-2 text-xs text-gray-400 select-none cursor-pointer">
                        <input type="checkbox" id="rememberEmail" class="sr-only">
                        <span class="w-5 h-5 shrink-0 rounded border border-white/30 bg-white/5 flex items-center justify-center transition-colors" id="rememberEmailBox">
                            <svg id="rememberEmailCheckIcon" class="w-3.5 h-3.5 text-black hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.415l-7.5 7.5a1 1 0 01-1.415 0l-3.5-3.5a1 1 0 111.415-1.414L8.5 12.086l6.79-6.795a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        メールアドレスを保存する
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-400 select-none cursor-pointer">
                        <input type="checkbox" id="rememberPassword" class="sr-only">
                        <span class="w-5 h-5 shrink-0 rounded border border-white/30 bg-white/5 flex items-center justify-center transition-colors" id="rememberPasswordBox">
                            <svg id="rememberPasswordCheckIcon" class="w-3.5 h-3.5 text-black hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.415l-7.5 7.5a1 1 0 01-1.415 0l-3.5-3.5a1 1 0 111.415-1.414L8.5 12.086l6.79-6.795a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        パスワードを保存する
                    </label>
                </div>

                <button type="submit" class="mt-4 bg-white/20 hover:bg-white/30 text-white py-3.5 rounded-full tracking-widest text-sm transition-all border border-white/30 font-medium">LOGIN</button>
            </form>
            <div class="mt-8 text-center flex flex-col gap-6">
                <a href="register.php" class="text-xs text-gray-400 hover:text-white transition-colors">アカウント新規作成</a>
                <a href="forgot_password.php" class="text-[10px] text-gray-500 hover:text-white transition-colors tracking-widest">パスワードを忘れた方はこちら</a>
                <a href="mobile.php" class="text-xs text-gray-500 underline underline-offset-4 tracking-widest">TOPに戻る</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // ログイン情報の保存・自動入力（ブラウザのlocalStorageにのみ保存。サーバーには送信しません）
        (function() {
            const emailInput = document.querySelector('input[name="email"]');
            const passwordInput = document.getElementById('password');
            const rememberEmail = document.getElementById('rememberEmail');
            const rememberEmailBox = document.getElementById('rememberEmailBox');
            const rememberEmailCheckIcon = document.getElementById('rememberEmailCheckIcon');
            const rememberPassword = document.getElementById('rememberPassword');
            const rememberPasswordBox = document.getElementById('rememberPasswordBox');
            const rememberPasswordCheckIcon = document.getElementById('rememberPasswordCheckIcon');
            const loginForm = document.querySelector('form');
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

            const savedEmail = localStorage.getItem('lw_saved_email');
            const savedPassword = localStorage.getItem('lw_saved_password');
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
                    localStorage.setItem('lw_saved_email', emailInput.value);
                } else {
                    localStorage.removeItem('lw_saved_email');
                }
                if (rememberPassword.checked) {
                    localStorage.setItem('lw_saved_password', passwordInput.value);
                } else {
                    localStorage.removeItem('lw_saved_password');
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
    </script>
    <script>
        // --- スリープ・切断対策（ショート・ポーリング） ---
        function keepAlive() {
            fetch("keep_alive.php")
                .then(function(response) { if (!response.ok) console.error("Keep-alive error"); })
                .catch(function(error) { console.error("通信維持エラー:", error); });
        }
        setInterval(keepAlive, 5000);
    </script>
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('sw.js').catch(function(err) {
                console.error('SW registration failed:', err);
            });
        });
    }
</script>
</body>
</html>