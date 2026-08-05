<?php
require_once 'db.php';

$error = '';
$register_success = false;
$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die('CSRF token validation failed. 不正なリクエストです。');
    }

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $inviteCode = trim($_POST['invite_code'] ?? '');

    if ($email && $password && $inviteCode) {
        $inviteStmt = $pdo->prepare("SELECT id FROM invite_codes WHERE code = ? AND used_by_user_id IS NULL AND revoked_at IS NULL");
        $inviteStmt->execute([$inviteCode]);
        $inviteRow = $inviteStmt->fetch();

        if (!$inviteRow) {
            $error = '招待コードが無効か、既に使用されています。';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT); // パスワードを暗号化
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                $stmt->execute([$email, $hash]);
                $newUserId = $pdo->lastInsertId();

                // 使用した招待コードを消費済みにする
                $pdo->prepare("UPDATE invite_codes SET used_by_user_id = ?, used_at = ? WHERE id = ?")
                    ->execute([$newUserId, date('Y-m-d H:i:s'), $inviteRow['id']]);

                // 新規ユーザー自身にも招待コードを1つ発行する
                $pdo->prepare("INSERT INTO invite_codes (code, issued_to_user_id, created_at) VALUES (?, ?, ?)")
                    ->execute([generateInviteCode(), $newUserId, date('Y-m-d H:i:s')]);

                $pdo->commit();

                // 登録後、自動でログイン状態にしてダッシュボードへ
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['email'] = $email;
                $register_success = true;
            } catch (PDOException $e) {
                $pdo->rollBack();
                // 23000 は「一意性制約違反（UNIQUE）」つまり重複のエラーコードです
                if ($e->getCode() == 23000) {
                    $error = 'このメールアドレスは既に登録されています。';
                } else {
                    $error = 'データベースエラー: ' . $e->getMessage();
                }
            }
        }
    } else {
        $error = 'メールアドレス・パスワード・招待コードをすべて入力してください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>会員登録 - LUXE WAVE</title>
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

    <div class="bg-black/40 p-6 sm:p-8 rounded-xl border shadow-2xl w-full max-w-sm mx-4 sm:mx-0 backdrop-blur-md transition-all duration-500 <?php echo $error ? 'animate-shake border-red-500/50' : ($register_success ? 'border-green-500/50 shadow-[0_0_20px_rgba(34,197,94,0.3)]' : 'border-white/20'); ?>">
        <h2 class="text-2xl font-light mb-6 tracking-widest text-center"><?php echo $register_success ? 'WELCOME' : 'ACCOUNT REGISTER'; ?></h2>
        
        <?php if($register_success): ?>
            <div class="flex flex-col items-center justify-center py-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-green-400 mb-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-green-400 text-sm text-center tracking-widest">アカウントを作成しました</p>
            </div>
        <?php else: ?>
            <?php if($error): ?>
                <p class="text-red-400 text-xs mb-4 text-center"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="email" name="email" placeholder="Email Address" required
                    class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50">

                <input type="text" name="invite_code" placeholder="Invite Code" required
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
                
                <button type="submit" class="mt-4 bg-white/10 hover:bg-white/20 text-white py-2 rounded tracking-widest text-sm transition-all border border-white/30">REGISTER</button>
            </form>
            <div class="mt-6 text-center">
                <a href="login.php" class="text-xs text-gray-400 hover:text-white transition-colors">すでにアカウントをお持ちの方はこちら</a>
                <br><br>
                <a href="index.php" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-2 rounded-full tracking-[0.2em] text-xs transition-all duration-300 inline-block">BACK TO HOME</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
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

        <?php if ($register_success): ?>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                document.body.classList.add('animate-fade-out');
                setTimeout(() => {
                    window.location.href = '<?php echo isMobile() ? 'mobile.php' : 'index.php'; ?>';
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
</body>
</html>