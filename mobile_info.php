<?php
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Info - LUXE WAVE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@200;300&family=Noto+Sans+JP:wght@200;300&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans JP', sans-serif; font-weight: 300; }
        .brand-font { font-family: 'Montserrat', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black text-white min-h-screen flex flex-col items-center justify-center p-6 relative z-0 overflow-hidden">
    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <div class="text-center bg-black/40 p-8 rounded-2xl border border-white/20 backdrop-blur-md shadow-2xl w-full max-w-sm relative z-10">
        <h2 class="text-2xl font-light mb-8 border-b border-white/30 pb-4 tracking-wider text-white">Info</h2>

        <div class="flex flex-col gap-4 text-left">
            <a href="https://note.com/luxewave" target="_blank" rel="noopener noreferrer"
               class="flex items-center justify-between bg-white/5 hover:bg-white/10 border border-white/20 rounded-xl px-5 py-4 transition-all">
                <span class="tracking-[0.2em] text-sm text-gray-200">note</span>
                <span class="flex items-center gap-1.5 text-[10px] tracking-widest text-gray-400">
                    note.com/luxewave
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                </span>
            </a>

            <div class="flex items-center justify-between bg-black/20 border border-white/10 rounded-xl px-5 py-4">
                <span class="tracking-[0.2em] text-sm text-gray-300">SNS</span>
                <span class="text-[10px] tracking-widest text-gray-400">coming soon...</span>
            </div>

            <div class="flex items-center justify-between bg-black/20 border border-white/10 rounded-xl px-5 py-4">
                <span class="tracking-[0.2em] text-sm text-gray-300">独自資料</span>
                <span class="text-[10px] tracking-widest text-gray-400">coming soon...</span>
            </div>
        </div>

        <div class="flex flex-col gap-5 mt-10">
            <a href="mobile.php" class="bg-white/5 hover:bg-white/10 border border-white/20 text-gray-200 hover:text-white py-3.5 rounded-full tracking-widest text-sm transition-all">BACK TO HOME</a>
        </div>
    </div>

    <script>
        const canvas = document.getElementById('waveCanvas');
        const ctx = canvas.getContext('2d');
        let width, height, time = 0;
        function resize() { width = canvas.width = window.innerWidth; height = canvas.height = window.innerHeight; }
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
    <script>
        // --- スリープ・切断対策（ショート・ポーリング） ---
        function keepAlive() {
            fetch("keep_alive.php")
                .then(function(response) {
                    if (!response.ok) { console.error("Keep-alive error"); return; }
                    return response.json();
                })
                .then(function(data) {
                    if (data && data.loggedIn === false) {
                        window.location.href = "mobile_login.php";
                    }
                })
                .catch(function(error) { console.error("通信維持エラー:", error); });
        }
        setInterval(keepAlive, 5000);
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
