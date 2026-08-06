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
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black min-h-screen flex items-center justify-center text-white p-6">
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
</body>
</html>
