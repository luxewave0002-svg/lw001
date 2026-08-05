<?php
require_once 'db.php';

$csrfToken = generateCsrfToken();
$message = '';
$message_class = '';
$new_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die('CSRF token validation failed.');
    }

    $email = $_POST['email'] ?? '';

    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $new_password = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
            $newHash = password_hash($new_password, PASSWORD_DEFAULT);

            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($updateStmt->execute([$newHash, $user['id']])) {
                $message = 'パスワードをリセットしました。';
                $message_class = 'success';
                writeLog($pdo, $user['id'], 'password_reset', 'ユーザーがパスワードを再発行しました(Mobile)。');
            } else {
                $message = 'パスワードのリセットに失敗しました。';
                $message_class = 'error';
            }
        } else {
            $message = '入力されたメールアドレスは登録されていません。';
            $message_class = 'error';
        }
    } else {
        $message = 'メールアドレスを入力してください。';
        $message_class = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>パスワード再発行 - Mobile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style> body { font-family: sans-serif; font-weight: 300; background-color: #050505; } </style>
</head>
<body class="text-white min-h-screen flex flex-col p-6 items-center justify-center">
    <div class="w-full max-w-sm">
        <h2 class="text-2xl font-light mb-8 tracking-widest text-center uppercase">Reset Password</h2>
        
        <?php if($new_password): ?>
            <div class="flex flex-col items-center justify-center py-4 gap-5">
                <p class="text-green-400 text-sm text-center tracking-widest"><?php echo htmlspecialchars($message); ?></p>
                <div class="bg-white/10 border border-green-500/50 p-5 rounded-xl w-full text-center">
                    <p class="text-xs text-gray-400 mb-2">新しい仮パスワード</p>
                    <p class="text-2xl font-mono text-white tracking-widest select-all"><?php echo htmlspecialchars($new_password); ?></p>
                </div>
                <p class="text-[10px] text-yellow-400/80 tracking-wider text-center mt-2 leading-relaxed">※ このパスワードをコピーしてログインし、<br>ダッシュボードから任意のパスワードに再設定してください。</p>
                <a href="mobile_login.php" class="mt-4 bg-white/20 hover:bg-white/30 text-white py-3.5 rounded-full tracking-widest text-sm transition-all border border-white/30 font-medium text-center w-full">LOGIN 画面へ</a>
            </div>
        <?php else: ?>
            <?php if($message): ?> <p class="text-red-400 text-xs mb-4 text-center"><?php echo htmlspecialchars($message); ?></p> <?php endif; ?>
            <p class="text-xs text-gray-400 mb-6 text-center leading-relaxed tracking-wider">登録しているメールアドレスを入力してください。<br>新しいパスワードを発行します。</p>
            <form method="POST" class="flex flex-col gap-5">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="email" name="email" placeholder="Email" required class="bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-base text-white outline-none focus:border-white/50">
                <button type="submit" class="mt-2 bg-white/20 hover:bg-white/30 text-white py-3.5 rounded-full tracking-widest text-sm transition-all border border-white/30 font-medium">RESET PASSWORD</button>
            </form>
            <div class="mt-8 text-center flex flex-col gap-6"><a href="mobile_login.php" class="text-xs text-gray-500 underline underline-offset-4 tracking-widest">ログイン画面に戻る</a></div>
        <?php endif; ?>
    </div>
</body>
</html>