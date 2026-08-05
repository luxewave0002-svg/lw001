<?php
require_once 'db.php';

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: mobile_login.php");
    exit;
}

// ログイン必須（未ログインならログインページへ）
requireLogin($pdo, 'mobile_login.php');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - LUXE WAVE</title>
    <link rel="icon" href="favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@200;300&family=Noto+Sans+JP:wght@200;300&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans JP', sans-serif;
            font-weight: 300;
        }
        .brand-font {
            font-family: 'Montserrat', sans-serif;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black text-white min-h-screen flex flex-col items-center justify-center p-6 relative z-0 overflow-hidden">

    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <div class="text-center bg-black/40 p-8 md:p-10 rounded-2xl border border-white/20 backdrop-blur-md shadow-2xl w-full max-w-sm relative z-10">
        <h1 class="brand-font text-3xl font-extralight tracking-[0.2em] mb-10">LUXE WAVE</h1>

        <div class="grid grid-cols-2 gap-3">
            <?php foreach ([1, 2, 3, 4] as $lvl): ?>
            <a href="mobile_level.php?level=<?php echo $lvl; ?>" class="bg-white/5 hover:bg-white/10 border border-white/20 text-gray-200 hover:text-white py-4 rounded-lg tracking-widest text-sm transition-all text-center">Level.<?php echo $lvl; ?></a>
            <?php endforeach; ?>
        </div>

        <a href="mobile.php" class="text-xs text-gray-500 underline underline-offset-4 tracking-widest mt-10 inline-block">MENUに戻る</a>

        <?php if (isset($_SESSION['user_id'])): ?>
        <br>
        <a href="?logout=1" class="text-xs text-gray-500 underline underline-offset-4 tracking-widest mt-4 inline-block">LOGOUT</a>
        <?php endif; ?>
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
                ctx.beginPath(); ctx.strokeStyle = wave.color; ctx.lineWidth = 1;
                for (let x = 0; x <= width; x += 4) {
                    const envelope = Math.sin(x * 0.001 + time * 0.01) * 0.8 + 0.2;
                    const y = height / 2 + Math.sin(x * wave.frequency + time * wave.speed) * wave.amplitude * envelope;
                    if (x === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                }
                ctx.stroke();
            });
            time += 1; requestAnimationFrame(drawWaves);
        }
        drawWaves();
    </script>
</body>
</html>
