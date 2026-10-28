<?php
require_once 'db.php';

// Apple Watch版 Level一覧（スマホ版 mobile_home.php と同じ表示条件：Level.1〜4 ＋ CODE入力で解除済みのLimited）
if (isset($_GET['logout'])) {
    logoutUser($pdo);
    header("Location: apple_watch_login.php");
    exit;
}

// ログイン必須（未ログインならログインページへ）
requireLogin($pdo, 'apple_watch_login.php');

$unlockedLimitedLevels = getUnlockedLimitedLevels($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Home - Watch</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #000;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 10px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .header {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        h1 { font-size: 18px; font-weight: 300; letter-spacing: 1px; margin: 0; }
        .nav-link { color: #007aff; text-decoration: none; font-size: 12px; margin-left: 10px; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; width: 100%; max-width: 200px; }
        .grid a {
            display: block;
            text-align: center;
            padding: 12px 4px;
            background-color: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            letter-spacing: 1px;
        }
        .other-links { margin-top: 18px; width: 100%; text-align: center; }
        .other-links a { display: block; margin-bottom: 8px; color: #007aff; text-decoration: none; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Home</h1>
        <div>
            <a href="?logout=1" class="nav-link">Logout</a>
        </div>
    </div>

    <div class="grid">
        <?php foreach ([1, 2, 3, 4] as $lvl): ?>
            <a href="apple_watch_level.php?level=<?php echo $lvl; ?>">Level.<?php echo $lvl; ?></a>
        <?php endforeach; ?>
        <?php foreach ($unlockedLimitedLevels as $lvl): ?>
            <a href="apple_watch_level.php?level=<?php echo (int)$lvl; ?>"><?php echo htmlspecialchars(getLimitedLevelLabel($lvl)); ?></a>
        <?php endforeach; ?>
    </div>

    <div class="other-links">
        <a href="apple_watch_smart_plugs.php">Smart Plugs</a>
    </div>

    <script>
        // Keep session alive
        setInterval(function() { fetch('keep_alive.php').catch(function() {}); }, 300000);
    </script>
</body>
</html>
