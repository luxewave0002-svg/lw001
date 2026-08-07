<?php
require_once 'db.php';

// スマホ判定
if (empty($_SESSION['force_pc']) && isMobile()) {
    header("Location: mobile_forgot_password.php");
    exit;
}

$csrfToken = generateCsrfToken();
$message = '';
$message_class = '';
$new_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die('CSRF token validation failed.');
    }

    $email = $_POST['email'] ?? '';

    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // ランダムな8文字のパスワードを生成
            $new_password = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
            $newHash = password_hash($new_password, PASSWORD_DEFAULT);

            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($updateStmt->execute([$newHash, $user['id']])) {
                $message = 'パスワードをリセットしました。';
                $message_class = 'success';
                writeLog($pdo, $user['id'], 'password_reset', 'ユーザーがパスワードを再発行しました。');
            } else {
                $message = 'パスワードのリセットに失敗しました。';
                $message_class = 'error';
            }
        } else {
            $message = '入力されたメールアドレスは登録されていません。';
            $message_class = 'error';
        }
    } else {
        $message = 'メールアドレスを入力してください。';
        $message_class = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>パスワードのリセット - LUXE WAVE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes shake { 0%, 100% { transform: translateX(0); } 10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); } 20%, 40%, 60%, 80% { transform: translateX(5px); } }
        .animate-shake { animation: shake 0.5s ease-in-out; }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black min-h-screen flex items-center justify-center text-white relative z-0 overflow-hidden">
    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <div class="bg-black/40 p-6 sm:p-8 rounded-xl border shadow-2xl w-full max-w-sm mx-4 sm:mx-0 backdrop-blur-md transition-all duration-500 <?php echo ($message_class === 'error') ? 'animate-shake border-red-500/50' : 'border-white/20'; ?>">
        <h2 class="text-2xl font-light mb-6 tracking-widest text-center uppercase">Reset Password</h2>
        
        <?php if($new_password): ?>
            <div class="flex flex-col items-center justify-center py-4 gap-4">
                <p class="text-green-400 text-sm text-center tracking-widest"><?php echo htmlspecialchars($message); ?></p>
                <div class="bg-white/10 border border-green-500/50 p-4 rounded-lg w-full text-center">
                    <p class="text-xs text-gray-300 mb-2">新しい仮パスワード</p>
                    <p class="text-xl font-mono text-white tracking-widest select-all"><?php echo htmlspecialchars($new_password); ?></p>
                </div>
                <p class="text-[10px] text-yellow-400/80 tracking-wider text-center mt-2 leading-relaxed">※ このパスワードをコピーし、ログイン後にダッシュボードから任意のパスワードに再設定してください。</p>
                <a href="login.php" class="mt-4 bg-white/10 hover:bg-white/20 text-white py-2 px-8 rounded tracking-widest text-sm transition-all border border-white/30 text-center w-full">LOGIN 画面へ</a>
            </div>
        <?php else: ?>
            <?php if($message): ?>
                <p class="text-red-400 text-xs mb-4 text-center"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <p class="text-xs text-gray-300 mb-6 text-center leading-relaxed tracking-wider">
                登録しているメールアドレスを入力してください。<br>新しいパスワードを発行します。
            </p>

            <form method="POST" class="flex flex-col gap-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="email" name="email" placeholder="Email Address" required class="bg-white/5 border border-white/20 rounded px-4 py-2 text-sm text-white focus:outline-none focus:border-white/50">
                <button type="submit" class="mt-4 bg-white/10 hover:bg-white/20 text-white py-2 rounded tracking-widest text-sm transition-all border border-white/30">RESET PASSWORD</button>
            </form>
            <div class="mt-6 text-center flex flex-col gap-3">
                <a href="login.php" class="text-xs text-gray-400 hover:text-white transition-colors tracking-wider">ログイン画面に戻る</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const canvas = document.getElementById('waveCanvas'); const ctx = canvas.getContext('2d'); let width, height, time = 0;
        function resize() { width = canvas.width = window.innerWidth; height = canvas.height = window.innerHeight; }
        window.addEventListener('resize', resize); resize();
        function drawWaves() {
            ctx.clearRect(0, 0, width, height); const waves = [{ amplitude: 150, frequency: 0.002, speed: 0.015, color: 'rgba(255, 255, 255, 0.05)' }, { amplitude: 100, frequency: 0.004, speed: 0.02,  color: 'rgba(100, 150, 255, 0.15)' }, { amplitude: 60,  frequency: 0.006, speed: 0.03,  color: 'rgba(255, 255, 255, 0.03)' }];
            waves.forEach(wave => { ctx.beginPath(); ctx.strokeStyle = wave.color; ctx.lineWidth = 1; for (let x = 0; x <= width; x += 4) { const envelope = Math.sin(x * 0.001 + time * 0.01) * 0.8 + 0.2; const y = height / 2 + Math.sin(x * wave.frequency + time * wave.speed) * wave.amplitude * envelope; if (x === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y); } ctx.stroke(); }); time += 1; requestAnimationFrame(drawWaves);
        } drawWaves();
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