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
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="apple-touch-icon.png?v=2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LUXE WAVE">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#000000">

<script>
    // PWAが新規起動（またはiOSによる独自の履歴復元・bfcache復元）で開かれた際、
    // 直前に見ていたページと違う場合は自動的にそちらへ戻す
    function lwCheckAndRestorePage() {
        var isStandalone = window.navigator.standalone === true ||
            (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
        var isFreshLaunch = !sessionStorage.getItem('lw_session_active');
        sessionStorage.setItem('lw_session_active', '1');
        var currentPath = location.pathname + location.search;

        if (isStandalone && isFreshLaunch) {
            var lastPage = localStorage.getItem('lw_last_page');
            if (lastPage && lastPage !== currentPath) {
                localStorage.setItem('lw_just_restored', '1');
                window.location.replace(lastPage);
                return;
            }
        }
        localStorage.setItem('lw_last_page', currentPath);
    }
    lwCheckAndRestorePage();

    // 直前に自動復元が発生していた場合、画面上部に一時的な通知を表示する
    function lwShowRestoredToastIfNeeded() {
        if (localStorage.getItem('lw_just_restored') === '1') {
            localStorage.removeItem('lw_just_restored');
            var existing = document.getElementById('lw-restored-toast');
            if (existing) existing.remove();
            var toast = document.createElement('div');
            toast.id = 'lw-restored-toast';
            toast.textContent = '前回の続きのページに自動で戻しました';
            toast.style.cssText = 'position:fixed;top:12px;left:50%;transform:translateX(-50%);' +
                'background:rgba(0,0,0,0.85);color:#fff;font-size:12px;letter-spacing:0.05em;' +
                'padding:10px 18px;border-radius:999px;border:1px solid rgba(255,255,255,0.2);' +
                'z-index:99999;backdrop-filter:blur(6px);transition:opacity 0.5s;';
            document.body.appendChild(toast);
            setTimeout(function() {
                toast.style.opacity = '0';
                setTimeout(function() { toast.remove(); }, 500);
            }, 3000);
        }
    }
    window.addEventListener('DOMContentLoaded', lwShowRestoredToastIfNeeded);

    // bfcache（iOSがJSを再実行せずページを丸ごと復元する仕組み）からの復帰を検知し、
    // 通常のスクリプト実行が起きないケースにも対応する
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            lwCheckAndRestorePage();
            lwShowRestoredToastIfNeeded();
        }
    });
</script>
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
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('sw.js').catch(function(err) {
                console.error('SW registration failed:', err);
            });
        });
    }
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
