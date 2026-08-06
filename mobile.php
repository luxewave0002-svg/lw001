<?php
require_once 'db.php';

// ログアウト処理
if (isset($_GET['logout'])) {
    logoutUser($pdo);
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
    <title>LUXE WAVE - Mobile</title>
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
        
        <div class="flex flex-col gap-5">
            <a href="mobile_home.php" class="bg-white/5 hover:bg-white/10 border border-white/20 text-gray-200 hover:text-white py-3.5 rounded-full tracking-widest text-sm transition-all">L/W</a>
            <a href="mobile_about.php" class="bg-white/5 hover:bg-white/10 border border-white/20 text-gray-200 hover:text-white py-3.5 rounded-full tracking-widest text-sm transition-all">ABOUT</a>
            <a href="mobile_info.php" class="bg-white/5 hover:bg-white/10 border border-white/20 text-gray-200 hover:text-white py-3.5 rounded-full tracking-widest text-sm transition-all">INFO</a>
            <a href="smart_plugs.php" class="bg-white/5 hover:bg-white/10 border border-white/20 text-gray-200 hover:text-white py-3.5 rounded-full tracking-widest text-sm transition-all">SMART PLUGS</a>

            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white py-3.5 rounded-full tracking-widest text-sm transition-all shadow-lg">DASHBOARD</a>
                <a href="?logout=1" onclick="[1,2,3,4].forEach(function(l){localStorage.removeItem('lw_level_on_since_'+l);})" class="text-xs text-gray-500 underline underline-offset-4 tracking-widest mt-1 inline-block">LOGOUT</a>
            <?php else: ?>
                <a href="mobile_login.php" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white py-3.5 rounded-full tracking-widest text-sm transition-all shadow-lg">LOGIN</a>
            <?php endif; ?>
            
            <!-- index.phpにパラメータを渡してPC版を強制表示させる -->
            <a href="w_h.php" class="text-[10px] text-gray-500 hover:text-gray-300 mt-6 transition-colors tracking-widest underline underline-offset-4">Apple Watch版ホームを表示する</a>
            <a href="apple_watch_smart_plugs.php" class="text-[10px] text-gray-500 hover:text-gray-300 mt-6 transition-colors tracking-widest underline underline-offset-4">Apple Watch版を表示する</a>
            <a href="index.php?force_pc=1" class="text-[10px] text-gray-500 hover:text-gray-300 mt-6 transition-colors tracking-widest underline underline-offset-4">PC版サイトを表示する</a>
        </div>
    </div>

    <div class="fixed bottom-4 right-4 z-50">
        <a href="admin.php" class="text-[10px] text-white/50 hover:text-white transition-colors tracking-widest uppercase bg-black/30 px-2 py-1 rounded-md backdrop-blur-sm border border-white/10">Admin</a>
    </div>

    <script>
        // 背景のアニメーション
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
        setInterval(keepAlive, 30000);
    </script>
</body>
</html>