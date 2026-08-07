<?php
require_once 'db.php';

// ログイン必須（別端末でログインされていれば自動ログアウト）
requireLogin($pdo, 'login.php');

// ログアウト処理
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ページを選択 - LUXE WAVE</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black min-h-screen flex items-center justify-center text-white relative z-0 overflow-hidden">

    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <div class="bg-black/40 p-6 sm:p-8 rounded-xl border border-white/20 shadow-2xl w-full max-w-sm mx-4 sm:mx-0 backdrop-blur-md text-center">
        <h2 class="text-2xl font-light mb-2 tracking-widest">WELCOME</h2>
        <p class="text-xs text-gray-400 mb-8 tracking-wider"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>

        <div class="flex flex-col gap-4">
            <a href="index.php" class="bg-white/10 hover:bg-white/20 text-white py-3 rounded tracking-widest text-sm transition-all border border-white/30">
                TEST PAGE
            </a>
            <a href="dashboard.php" class="bg-white/10 hover:bg-white/20 text-white py-3 rounded tracking-widest text-sm transition-all border border-white/30">
                SMART PLUG
            </a>
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
