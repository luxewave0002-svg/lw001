<?php
require_once 'db.php';

// ログイン必須（別端末でログインされていれば自動ログアウト）
requireLogin($pdo, 'mobile_login.php');

// ログアウト処理
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: mobile_login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ページを選択 - LUXE WAVE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Noto Sans JP', sans-serif; font-weight: 300; background-color: #050505; }
    </style>
</head>
<body class="text-white min-h-screen flex flex-col p-6 items-center justify-center">

    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <div class="w-full max-w-sm text-center bg-black/40 p-8 rounded-2xl border border-white/20 backdrop-blur-md shadow-2xl relative z-10">
        <h2 class="text-2xl font-light mb-2 tracking-widest">WELCOME</h2>
        <p class="text-xs text-gray-400 mb-8 tracking-wider"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>

        <div class="flex flex-col gap-4">
            <a href="mobile.php" class="bg-white/5 hover:bg-white/10 border border-white/20 text-gray-200 hover:text-white py-3.5 rounded-full tracking-widest text-sm transition-all">TEST PAGE</a>
            <a href="mobile_dashboard.php" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white py-3.5 rounded-full tracking-widest text-sm transition-all shadow-lg">SMART PLUG</a>
        </div>

        <a href="?logout=1" class="block mt-8 text-[10px] text-gray-500 hover:text-white transition-colors tracking-wider">LOGOUT</a>
    </div>

    <script>
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
