<?php
require_once 'db.php';

// Apple Watch版 Levelページ（スマホ版 mobile_level.php と同じ判定・同じ toggle_level.php を使う小型版）
$level = $_GET['level'] ?? '';
if (!filter_var($level, FILTER_VALIDATE_INT, array("options" => array("min_range"=>1, "max_range"=>10)))) {
    header("Location: w_h.php");
    exit;
}
$level = (int)$level;

$csrfToken = generateCsrfToken();
$levelPasswordError = '';

// Levelパスワードの照合（Limitedレベルはダッシュボードでの別途CODE入力方式のため対象外）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_level_password' && !isLimitedLevel($level)) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        // 不正/期限切れトークンはエラー文言を出さず、通常のLevel画面（未解除状態）に戻す
        header("Location: apple_watch_level.php?level=" . $level);
        exit;
    }

    $inputPassword = $_POST['level_password'] ?? '';

    if (!isset($_SESSION['user_id'])) {
        $levelPasswordError = 'Login required.';
    } else {
        $correctPassword = getLevelPassword($pdo, $_SESSION['user_id'], $level);

        if ($correctPassword !== null && hash_equals((string)$correctPassword, $inputPassword)) {
            $fingerprint = levelUnlockFingerprint($correctPassword);
            $_SESSION['unlocked_levels'][$level] = $fingerprint;
            setcookie('level_unlock_' . $level, $fingerprint, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            $levelPasswordError = 'Wrong password.';
        }
    }
}

// このLevelを閲覧できるか（Limitedはダッシュボードでの CODE 入力、通常Levelは管理者発行パスワード）
$isLimited = isLimitedLevel($level);
if ($isLimited) {
    $isLocked = !isLimitedLevelUnlocked($pdo, $_SESSION['user_id'] ?? null, $level);
} else {
    $isLocked = !isLevelUnlocked($pdo, $_SESSION['user_id'] ?? null, $level);
}

// 「技術発生」の現在の状態（サーバー側DBが正）
$activationStartedAt = null;
if (!$isLocked && isset($_SESSION['user_id'])) {
    $activationStartedAt = getLevelActivation($pdo, $_SESSION['user_id'], $level);
}

$imagePath = getImagePath((string)$level);
$title = $isLimited ? getLimitedLevelLabel($level) : 'Level.' . $level;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($title); ?> - Watch</title>
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
        h1 { font-size: 16px; font-weight: 300; letter-spacing: 1px; margin: 0; }
        .nav-link { color: #007aff; text-decoration: none; font-size: 12px; }
        .box { width: 100%; max-width: 200px; text-align: center; }
        .msg { font-size: 12px; color: #ccc; margin: 0 0 12px; line-height: 1.5; }
        .error { color: #ff3b30; font-size: 12px; margin-bottom: 8px; }
        input[type="password"] {
            width: calc(100% - 20px);
            padding: 8px 10px;
            margin-bottom: 10px;
            border: 1px solid #333;
            border-radius: 5px;
            background-color: #1a1a1a;
            color: #fff;
            font-size: 14px;
            -webkit-appearance: none;
        }
        .btn {
            display: block;
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            background-color: #007aff;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            text-decoration: none;
            -webkit-appearance: none;
        }
        .row { display: flex; align-items: center; justify-content: center; gap: 10px; }
        .toggle-switch { position: relative; display: inline-block; width: 40px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #555; -webkit-transition: .4s; transition: .4s; border-radius: 24px;
        }
        .slider:before {
            position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px;
            background-color: white; -webkit-transition: .4s; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: #007aff; }
        input:checked + .slider:before { -webkit-transform: translateX(16px); transform: translateX(16px); }
        #status { font-size: 13px; letter-spacing: 1px; color: #888; }
        #status.on { color: #34c759; }
        #on-timer { font-size: 11px; color: #888; margin-top: 6px; font-variant-numeric: tabular-nums; }
        .media-container {
            margin: 12px auto 0; width: 100%; max-width: 120px; overflow: hidden; border-radius: 5px; background-color: #000;
        }
        .media-container img, .media-container video { width: 100%; height: auto; display: block; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <a href="w_h.php" class="nav-link">Back</a>
    </div>

    <div class="box">
    <?php if ($isLocked): ?>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <p class="msg">Login required.</p>
            <a href="apple_watch_login.php" class="btn">LOGIN</a>
        <?php elseif ($isLimited): ?>
            <p class="msg">Enter the CODE in the dashboard (on your phone or PC) to unlock this Level.</p>
        <?php else: ?>
            <?php if ($levelPasswordError): ?>
                <p class="error"><?php echo htmlspecialchars($levelPasswordError); ?></p>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="verify_level_password">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="password" name="level_password" placeholder="Password" required>
                <button type="submit" class="btn">UNLOCK</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <div class="row">
            <label class="toggle-switch">
                <input type="checkbox" id="toggleLevel" autocomplete="off" <?php echo $activationStartedAt ? 'checked' : ''; ?> onchange="handleToggleChange(this.checked)">
                <span class="slider"></span>
            </label>
            <span id="status" class="<?php echo $activationStartedAt ? 'on' : ''; ?>"><?php echo $activationStartedAt ? 'ON' : 'OFF'; ?></span>
        </div>
        <div id="on-timer" class="<?php echo $activationStartedAt ? '' : 'hidden'; ?>">00:00:00</div>

        <div id="level-media" class="media-container <?php echo $activationStartedAt ? '' : 'hidden'; ?>">
            <?php if (isVideoFile($imagePath)): ?>
                <video src="<?php echo htmlspecialchars($imagePath); ?>" loop muted playsinline preload="auto"></video>
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($title); ?>">
            <?php endif; ?>
        </div>
    <?php endif; ?>
    </div>

<?php if (!$isLocked && isset($_SESSION['user_id'])): ?>
    <script>
        // 「技術発生」の状態はサーバー側(DB)で管理。ON/OFFは toggle_level.php（スマホ版と同じ）へPOSTし、成功した場合のみ画面に反映する。
        (function() {
            var level = <?php echo (int)$level; ?>;
            var csrfToken = <?php echo json_encode($csrfToken); ?>;
            var toggle = document.getElementById('toggleLevel');
            var statusEl = document.getElementById('status');
            var timerEl = document.getElementById('on-timer');
            var mediaBox = document.getElementById('level-media');
            var video = mediaBox.querySelector('video');
            var startedAtMs = <?php echo $activationStartedAt ? (strtotime($activationStartedAt) * 1000) : 'null'; ?>;
            var timer = null;

            function pad(n) { return String(n).padStart(2, '0'); }
            function tick() {
                if (!startedAtMs) return;
                var s = Math.max(0, Math.floor((Date.now() - startedAtMs) / 1000));
                timerEl.textContent = pad(Math.floor(s / 3600)) + ':' + pad(Math.floor((s % 3600) / 60)) + ':' + pad(s % 60);
            }
            function render(isOn) {
                toggle.checked = isOn;
                statusEl.textContent = isOn ? 'ON' : 'OFF';
                statusEl.className = isOn ? 'on' : '';
                mediaBox.classList.toggle('hidden', !isOn);
                timerEl.classList.toggle('hidden', !isOn);
                if (video) {
                    if (isOn) { var p = video.play(); if (p && p.catch) p.catch(function() {}); }
                    else { video.pause(); video.currentTime = 0; }
                }
                if (timer) { clearInterval(timer); timer = null; }
                if (isOn) { tick(); timer = setInterval(tick, 1000); }
            }
            function post(action) {
                var body = new URLSearchParams({ action: action, level: String(level), csrf_token: csrfToken });
                return fetch('toggle_level.php', { method: 'POST', body: body }).then(function(res) { return res.json(); });
            }

            window.handleToggleChange = function(isChecked) {
                toggle.disabled = true;
                post(isChecked ? 'start' : 'stop').then(function(data) {
                    toggle.disabled = false;
                    if (data.error) { render(!isChecked); return; }   // 失敗時は元の状態へ戻す
                    startedAtMs = data.startedAtMs;
                    render(!!startedAtMs);
                }).catch(function() {
                    toggle.disabled = false;
                    render(!isChecked);
                });
            };

            // 画面に戻ったとき、サーバーの最新状態と同期する（24h自動終了などの反映）
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState !== 'visible') return;
                post('status').then(function(data) {
                    if (data.error) return;
                    startedAtMs = data.startedAtMs;
                    render(!!startedAtMs);
                }).catch(function() {});
            });

            render(!!startedAtMs);
            setInterval(function() { fetch('keep_alive.php').catch(function() {}); }, 300000);
        })();
    </script>
<?php endif; ?>
</body>
</html>
