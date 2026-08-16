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
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LUXE WAVE">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#000000">
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black text-white min-h-screen flex flex-col items-center justify-center p-6 relative z-0 overflow-hidden">
<!-- インアプリブラウザ（LINE/Facebook/Instagram等）検知バナー -->
<div id="lw-inapp-banner" class="hidden fixed top-0 left-0 right-0 z-[9999] bg-black/95 border-b border-white/20 text-white text-xs sm:text-sm px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-2 backdrop-blur-md">
    <span class="tracking-wide leading-relaxed">アプリ内ブラウザで表示しています。正しく動作しない場合は右上「…」等のメニューから「ブラウザで開く」を選択してください。</span>
    <div class="flex items-center gap-2 shrink-0">
        <button type="button" onclick="lwCopyCurrentUrl(this)" class="border border-white/30 hover:bg-white/10 px-3 py-1 rounded-full tracking-wider text-xs transition-colors">URLをコピー</button>
        <button type="button" onclick="document.getElementById('lw-inapp-banner').style.display='none'" class="text-gray-400 hover:text-white px-2 text-lg leading-none">&times;</button>
    </div>
</div>
<script>
    // PWAをアイコンから新規起動した時だけ、直前に見ていたページへ自動的に戻す
    // （同じ起動セッション内で意図的にHOMEへ戻ってきた場合は邪魔しない）
    (function() {
        var isStandalone = window.navigator.standalone === true ||
            (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
        var isFreshLaunch = !sessionStorage.getItem('lw_session_active');
        sessionStorage.setItem('lw_session_active', '1');

        if (isStandalone && isFreshLaunch) {
            var lastPage = localStorage.getItem('lw_last_page');
            if (lastPage && lastPage.indexOf('mobile.php') === -1) {
                window.location.replace(lastPage);
            }
        }
    })();
</script>
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
                <a href="?logout=1" onclick="[1,2,3,4].forEach(function(l){sessionStorage.removeItem('lw_level_on_since_'+l);})" class="text-xs text-gray-500 underline underline-offset-4 tracking-widest mt-1 inline-block">LOGOUT</a>
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