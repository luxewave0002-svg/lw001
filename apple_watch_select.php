<?php
require_once 'db.php';

// ログイン必須（別端末でログインされていれば自動ログアウト）
requireLogin($pdo, 'apple_watch_login.php');

// ログアウト処理
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: apple_watch_login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Select - Watch</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #000;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 10px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .container {
            width: 100%;
            max-width: 260px;
            text-align: center;
        }
        h1 {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }
        a.btn {
            display: block;
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #1a1a1a;
            color: #fff;
            border: 1px solid #333;
            border-radius: 8px;
            font-size: 13px;
            text-decoration: none;
            box-sizing: border-box;
        }
        a.btn:active {
            background-color: #007aff;
        }
        .link {
            color: #007aff;
            text-decoration: none;
            font-size: 11px;
            margin-top: 15px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>SELECT PAGE</h1>
        <a href="apple_watch_home.php" class="btn">TEST PAGE</a>
        <a href="apple_watch_smart_plugs.php" class="btn">SMART PLUG</a>
        <a href="?logout=1" class="link">Logout</a>
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
</body>
</html>
