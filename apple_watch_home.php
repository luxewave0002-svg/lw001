<?php
require_once 'db.php';

// Logout handling for watch
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: apple_watch_login.php");
    exit;
}

// ログイン必須（未ログインならログインページへ）
requireLogin($pdo, 'apple_watch_login.php');

// getImagePath() / isVideoFile() は db.php で共通定義済み
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
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        h1 {
            font-size: 18px;
            font-weight: 300;
            letter-spacing: 1px;
            margin: 0;
        }
        .nav-link {
            color: #007aff;
            text-decoration: none;
            font-size: 12px;
            margin-left: 10px;
        }
        .test-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* Two columns for watch */
            gap: 10px;
            width: 100%;
            max-width: 200px; /* Constrain for watch screen */
        }
        .test-card {
            background-color: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 5px;
        }
        .test-card h2 {
            font-size: 16px;
            font-weight: 400;
            margin: 0;
        }
        .toggle-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-top: 5px;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #555;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            -webkit-transition: .4s;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #007aff;
        }
        input:focus + .slider {
            box-shadow: 0 0 1px #007aff;
        }
        input:checked + .slider:before {
            -webkit-transform: translateX(16px);
            -ms-transform: translateX(16px);
            transform: translateX(16px);
        }
        .media-container {
            margin-top: 10px;
            width: 100%;
            max-width: 100px; /* Smaller for watch */
            height: 100px;
            overflow: hidden;
            border-radius: 5px;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #000;
        }
        .media-container img, .media-container video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .status-text {
            font-size: 10px;
            margin-top: 5px;
            color: #888;
        }
        .status-text.on { color: #34c759; }
        .status-text.off { color: #ff3b30; }
        .hidden { display: none; }
        .other-links {
            margin-top: 20px;
            width: 100%;
            text-align: center;
        }
        .other-links a {
            display: block;
            margin-bottom: 8px;
            color: #007aff;
            text-decoration: none;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Home</h1>
        <div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="apple_watch_smart_plugs.php" class="nav-link">Plugs</a>
                <a href="?logout=1" class="nav-link">Logout</a>
            <?php else: ?>
                <a href="apple_watch_login.php" class="nav-link">Login</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="test-grid">
        <?php for($i = 1; $i <= 10; $i++): ?>
            <?php $imagePath = getImagePath((string)$i); ?>
            <div class="test-card">
                <h2>Test <?php echo $i; ?></h2>
                <div class="toggle-container">
                    <label class="toggle-switch">
                        <input type="checkbox" id="toggleTest<?php echo $i; ?>" onchange="toggleMedia('test<?php echo $i; ?>-media', 'status-test<?php echo $i; ?>', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
                <p id="status-test<?php echo $i; ?>" class="status-text off">OFF</p>
                <div id="test<?php echo $i; ?>-media" class="media-container hidden">
                    <?php if (isVideoFile($imagePath)): ?>
                        <video src="<?php echo htmlspecialchars($imagePath); ?>" muted loop playsinline></video>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Test <?php echo $i; ?> Media">
                    <?php endif; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <div class="other-links">
        <a href="apple_watch_smart_plugs.php">Smart Plugs</a>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="apple_watch_register.php">Register</a>
            <a href="apple_watch_forgot_password.php">Forgot Password?</a>
        <?php endif; ?>
    </div>

    <script>
        function toggleMedia(elementId, statusId, isChecked) {
            const targetElement = document.getElementById(elementId);
            const statusElement = document.getElementById(statusId);
            const mediaElement = targetElement.querySelector('img, video');

            if (isChecked) {
                targetElement.classList.remove('hidden');
                if (mediaElement && mediaElement.tagName === 'VIDEO') {
                    mediaElement.play();
                }
                statusElement.textContent = 'ON';
                statusElement.classList.remove('off');
                statusElement.classList.add('on');
            } else {
                if (mediaElement && mediaElement.tagName === 'VIDEO') {
                    mediaElement.pause();
                    mediaElement.currentTime = 0; // Reset video to start
                }
                targetElement.classList.add('hidden');
                statusElement.textContent = 'OFF';
                statusElement.classList.remove('on');
                statusElement.classList.add('off');
            }
        }

        // Keep session alive
        setInterval(() => { fetch('keep_alive.php').catch(() => {}); }, 300000);
    </script>
</body>
</html>