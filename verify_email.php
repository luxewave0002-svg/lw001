<?php
require_once 'db.php';

$status = 'error'; // error | success | expired
$message = '無効な認証リンクです。';
$justLoggedIn = false;

$token = $_GET['token'] ?? '';

if ($token) {
    $stmt = $pdo->prepare("SELECT id, email, email_verified, verify_token_expires_at FROM users WHERE verify_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $status = 'error';
        $message = '無効な認証リンクです。リンクの有効期限が切れているか、既に認証済みの可能性があります。';
    } elseif ((int)$user['email_verified'] === 1) {
        $status = 'success';
        $message = 'このメールアドレスは既に認証済みです。';
    } elseif (strtotime($user['verify_token_expires_at']) < time()) {
        $status = 'expired';
        $message = '認証リンクの有効期限が切れています。お手数ですが再度登録手続きを行うか、サポートまでご連絡ください。';
    } else {
        $pdo->prepare("UPDATE users SET email_verified = 1, verify_token = NULL, verify_token_expires_at = NULL WHERE id = ?")
            ->execute([$user['id']]);
        writeLog($pdo, $user['id'], 'email_verified', 'メールアドレスの認証が完了しました。');

        // 認証完了と同時にそのままログイン状態にする
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        registerNewLoginDevice($pdo, $user['id']);
        issueRememberCookie($pdo, $user['id']);
        writeLog($pdo, $user['id'], 'login', 'メール認証完了に伴う自動ログイン。');

        $status = 'success';
        $justLoggedIn = true;
        $message = 'メールアドレスの認証が完了しました。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メール認証 - LUXE WAVE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LUXE WAVE">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#000000">
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black min-h-screen flex items-center justify-center text-white p-6">
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

    <div class="bg-black/40 p-8 rounded-xl border border-white/20 shadow-2xl w-full max-w-sm backdrop-blur-md text-center">
        <?php if ($status === 'success'): ?>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-green-400 mb-6 mx-auto">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-red-400 mb-6 mx-auto">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        <?php endif; ?>
        <h2 class="text-xl font-light mb-4 tracking-widest"><?php echo $status === 'success' ? 'VERIFIED' : 'エラー'; ?></h2>
        <p class="text-gray-300 text-sm leading-relaxed mb-8"><?php echo htmlspecialchars($message); ?></p>
        <?php if ($justLoggedIn): ?>
            <a href="<?php echo isMobile() ? 'mobile.php' : 'index.php'; ?>" class="bg-white/10 hover:bg-white/20 text-white px-8 py-2 rounded-full tracking-[0.2em] text-xs transition-all duration-300 inline-block border border-white/30">メインページへ</a>
        <?php else: ?>
            <a href="login.php" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-2 rounded-full tracking-[0.2em] text-xs transition-all duration-300 inline-block">ログイン画面へ</a>
        <?php endif; ?>
    </div>
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
