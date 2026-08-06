<?php
require_once 'db.php';

// PC版表示の強制フラグをセッションに保存（PC版を見るボタンを押した時用）
if (isset($_GET['force_pc'])) {
    if ($_GET['force_pc'] == '1') {
        $_SESSION['force_pc'] = true;
    } elseif ($_GET['force_pc'] == '0') {
        unset($_SESSION['force_pc']);
    }
}

if (empty($_SESSION['force_pc']) && isMobile()) {
    header("Location: mobile_login.php");
    exit;
}

$error = '';
$success_message = '';
$login_success = false;
if (isset($_GET['deleted']) && $_GET['deleted'] === 'true') {
    $success_message = 'アカウントは正常に削除されました。';
}

$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die('CSRF token validation failed. 不正なリクエストです。');
    }

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ((int)$user['email_verified'] !== 1) {
                $error = 'メールアドレスの確認が完了していません。ご登録時に届いたメール内のリンクをクリックしてください。';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                issueRememberCookie($pdo, $user['id']);
                writeLog($pdo, $user['id'], 'login', 'ユーザーがログインしました。');
                $login_success = true;
            }
        } else {
            $error = 'メールアドレスまたはパスワードが間違っています。';
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
    <title>ログイン - LUXE WAVE</title>
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
<body class="bg-gradient-to-br from-black via-blue-900 to-black min-h-screen flex items-center justify-center text-white relative z-0 overflow-hidden">

    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <div class="bg-black/40 p-6 sm:p-8 rounded-xl border shadow-2xl w-full max-w-sm mx-4 sm:mx-0 backdrop-blur-md transition-all duration-500 <?php echo $error ? 'animate-shake border-red-500/50' : ($login_success ? 'border-green-500/50 shadow-[0_0_20px_rgba(34,197,94,0.3)]' : 'border-white/20'); ?>">
        <h2 class="text-2xl font-light mb-6 tracking-widest text-center"><?php echo $login_success ? 'WELCOME' : 'LOGIN'; ?></h2>
        
        <?php if($login_success): ?>
            <div class="flex flex-col items-center justify-center py-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-green-400 mb-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-green-400 text-sm text-center tracking-widest">認証に成功しました</p>
            </div>
        <?php else: ?>
            <?php if($success_message): ?>
                <p class="text-green-400 text-xs mb-4 text-center"><?php echo htmlspecialchars($success_message); ?></p>
            <?php endif; ?>

            <?php if($error): ?>
                <p class="text-red-400 text-xs mb-4 text-center"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="email" name="email" placeholder="Email Address" required 
                    class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50">
                
                <div class="relative w-full">
                    <input type="password" name="password" id="password" placeholder="Password" required 
                        class="w-full bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50 pr-10">
                    <button type="button" onclick="togglePasswordVisibility('password', 'eye-icon-password')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                        <svg id="eye-icon-password" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
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

                <button type="submit" class="mt-4 bg-white/10 hover:bg-white/20 text-white py-2 rounded tracking-widest text-sm transition-all border border-white/30">LOGIN</button>
            </form>
            <div class="mt-6 text-center flex flex-col gap-3">
                <a href="register.php" class="text-xs text-gray-400 hover:text-white transition-colors">アカウント新規作成</a>
                <a href="forgot_password.php" class="text-[10px] text-gray-500 hover:text-white transition-colors tracking-wider">パスワードを忘れた方はこちら</a>
                <a href="index.php" class="mt-4 border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-2 rounded-full tracking-[0.2em] text-xs transition-all duration-300 inline-block w-max mx-auto">BACK TO HOME</a>
                <?php if (isMobile()): ?>
                <a href="login.php?force_pc=0" class="text-[10px] text-gray-600 hover:text-gray-300 transition-colors tracking-wider underline underline-offset-4">スマホ版に戻る</a>
                <?php endif; ?>
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

        <?php if ($login_success): ?>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                document.body.classList.add('animate-fade-out');
                setTimeout(() => {
                    window.location.href = 'index.php'; // ログイン後はHOMEへ
                }, 800); // フェードアウト後にリダイレクト
            }, 800); // 成功メッセージを0.8秒表示
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
</body>
</html>