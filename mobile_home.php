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

// 自分に発行されているLevelパスワード一覧を取得
$myLevelPwStmt = $pdo->prepare("SELECT level, password FROM level_passwords WHERE user_id = ? AND revoked_at IS NULL AND password IS NOT NULL ORDER BY level ASC");
$myLevelPwStmt->execute([$_SESSION['user_id']]);
$myLevelPasswords = $myLevelPwStmt->fetchAll();
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

        <?php if (!empty($myLevelPasswords)): ?>
        <div class="mt-8 pt-6 border-t border-white/10">
            <button type="button" id="lw-toggle-passwords" onclick="lwTogglePasswordList()" class="text-xs text-gray-400 hover:text-white tracking-widest border border-white/20 rounded-full px-5 py-2 transition-colors">PASSWORD</button>
            <div id="lw-password-list" class="hidden mt-5 flex flex-col gap-2 text-left">
                <?php foreach ($myLevelPasswords as $lvlPw): ?>
                    <div class="flex items-center justify-between gap-2 bg-black/30 border border-white/10 px-3 py-2 rounded-lg">
                        <span class="text-xs text-gray-400 shrink-0">Level.<?php echo (int)$lvlPw['level']; ?></span>
                        <span class="font-mono text-white text-sm truncate"><?php echo htmlspecialchars($lvlPw['password']); ?></span>
                        <button type="button" onclick="lwCopyLevelPassword(this, '<?php echo htmlspecialchars($lvlPw['password'], ENT_QUOTES); ?>')" aria-label="パスワードをコピー" class="text-gray-400 hover:text-white transition-colors p-1 shrink-0 focus:outline-none">
                            <svg class="copy-icon w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                            </svg>
                            <svg class="check-icon w-4 h-4 hidden text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
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
        function lwTogglePasswordList() {
            const list = document.getElementById('lw-password-list');
            const btn = document.getElementById('lw-toggle-passwords');
            const isHidden = list.classList.contains('hidden');
            list.classList.toggle('hidden');
            btn.textContent = isHidden ? 'HIDE' : 'PASSWORD';
        }
        function lwCopyLevelPassword(button, password) {
            navigator.clipboard.writeText(password).then(function() {
                const copyIcon = button.querySelector('.copy-icon');
                const checkIcon = button.querySelector('.check-icon');
                copyIcon.classList.add('hidden');
                checkIcon.classList.remove('hidden');
                setTimeout(function() {
                    checkIcon.classList.add('hidden');
                    copyIcon.classList.remove('hidden');
                }, 1500);
            });
        }
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
</body>
</html>
