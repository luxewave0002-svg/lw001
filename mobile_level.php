<?php
require_once 'db.php';

$level = $_GET['level'] ?? '';
if (!filter_var($level, FILTER_VALIDATE_INT, array("options" => array("min_range"=>1, "max_range"=>10)))) {
    header("Location: mobile.php");
    exit;
}
$level = (int)$level;

$csrfToken = generateCsrfToken();
$levelPasswordError = '';

// Levelパスワードの照合処理（Limitedレベルはダッシュボードでの別途CODE入力方式のため対象外）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_level_password' && !isLimitedLevel($level)) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        // 不正/期限切れトークンの場合はエラー文言を出さず、通常のLevel画面（未解除状態）に戻す
        header("Location: mobile_level.php?level=" . $level);
        exit;
    }

    $inputPassword = $_POST['level_password'] ?? '';

    if (!isset($_SESSION['user_id'])) {
        $levelPasswordError = 'ログインが必要です。';
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
            $levelPasswordError = 'パスワードが間違っています。';
        }
    }
}

// このLevelを閲覧できるか
// Limitedレベル（5,6）はダッシュボードでのCODE入力による解除、通常Levelは管理者発行パスワードによる解除
$isLimited = isLimitedLevel($level);
if ($isLimited) {
    $isLocked = !isLimitedLevelUnlocked($pdo, $_SESSION['user_id'] ?? null, $level);
} else {
    $isLocked = !isLevelUnlocked($pdo, $_SESSION['user_id'] ?? null, $level);
}

// 「技術発生」の現在の状態と履歴をサーバー側から取得する
$activationStartedAt = null;
$activationHistory = [];
if (!$isLocked && isset($_SESSION['user_id'])) {
    $activationStartedAt = getLevelActivation($pdo, $_SESSION['user_id'], $level);
    $activationHistory = getLevelActivationHistory($pdo, $_SESSION['user_id'], $level);
}

$imagePath = getImagePath((string)$level);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Level.<?php echo $level; ?> - LUXE WAVE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Chromeの自動入力(鍵)アイコンと目のアイコンが重なって、枠からはみ出て見える現象を防ぐ */
        input::-webkit-credentials-auto-fill-button,
        input::-webkit-contacts-auto-fill-button {
            visibility: hidden;
            display: none !important;
            pointer-events: none;
            position: absolute;
            right: 0;
        }
        body {
            font-family: 'Noto Sans JP', sans-serif;
            font-weight: 300;
        }
        .brand-font {
            font-family: 'Montserrat', sans-serif;
        }
        .toggle-checkbox:checked { right: 0; border-color: #ffffff; }
        .toggle-checkbox:checked + .toggle-label { background-color: #ffffff; }
        .toggle-checkbox { right: 0; z-index: 1; border-color: #4b5563; transition: all 0.3s; }
        .toggle-label { width: 3rem; height: 1.5rem; background-color: #4b5563; border-radius: 9999px; transition: all 0.3s; }
        .toggle-dot { top: 0.125rem; left: 0.125rem; width: 1.25rem; height: 1.25rem; background-color: #1f2937; border-radius: 50%; transition: all 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .toggle-checkbox:checked ~ .toggle-dot { transform: translateX(1.5rem); background-color: #000000; }
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
<body class="bg-gradient-to-br from-black via-blue-900 to-black text-white min-h-screen flex flex-col items-center p-6 relative z-0 overflow-y-auto">
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

    <div class="text-center bg-black/40 p-8 md:p-10 rounded-2xl border border-white/20 backdrop-blur-md shadow-2xl w-full max-w-sm relative z-10 my-auto">
        <h1 class="brand-font text-2xl font-extralight tracking-[0.2em] mb-8"><?php echo $isLimited ? htmlspecialchars(getLimitedLevelLabel($level)) : 'Level.' . $level; ?></h1>

        <?php if ($isLocked): ?>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <p class="text-gray-300 text-sm mb-6">このLevelを見るにはログインが必要です。</p>
                <a href="mobile_login.php" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white py-3.5 rounded-full tracking-widest text-sm transition-all shadow-lg inline-block px-8">LOGIN</a>
            <?php elseif ($isLimited): ?>
                <p class="text-gray-300 text-sm mb-6">このLevelはダッシュボードでCODEを入力すると表示されます。</p>
                <a href="dashboard.php" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white py-3.5 rounded-full tracking-widest text-sm transition-all shadow-lg inline-block px-8">ダッシュボードへ</a>
            <?php else: ?>
                <?php if ($levelPasswordError): ?>
                    <p class="text-red-400 text-xs mb-4"><?php echo htmlspecialchars($levelPasswordError); ?></p>
                <?php endif; ?>
                <form method="POST" class="flex flex-col gap-4">
                    <input type="hidden" name="action" value="verify_level_password">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="relative">
                        <input type="password" name="level_password" placeholder="Password" required class="bg-black/50 border border-white/20 rounded pl-3 pr-12 py-2.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                        <button type="button" onclick="toggleLevelPassword(this)" aria-label="パスワードを表示" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-white transition-colors focus:outline-none">
                            <svg class="eye-open w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <svg class="eye-closed w-5 h-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243" />
                            </svg>
                        </button>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-gray-400 select-none cursor-pointer">
                        <input type="checkbox" id="rememberLevelPassword" class="sr-only">
                        <span class="w-5 h-5 shrink-0 rounded border border-white/30 bg-white/5 flex items-center justify-center transition-colors" id="rememberLevelPasswordBox">
                            <svg id="rememberLevelPasswordCheckIcon" class="w-3.5 h-3.5 text-black hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.415l-7.5 7.5a1 1 0 01-1.415 0l-3.5-3.5a1 1 0 111.415-1.414L8.5 12.086l6.79-6.795a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        パスワードを保存する
                    </label>
                    <button type="submit" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white py-3 rounded-full tracking-widest text-sm transition-all">UNLOCK</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="flex items-center justify-center mb-8 gap-4">
                <span class="font-light text-gray-200 tracking-wider text-sm">技術発生</span>
                <div class="relative inline-block w-12 h-6 align-middle select-none">
                    <input type="checkbox" name="toggleLevel" id="toggleLevel" autocomplete="off" <?php echo $activationStartedAt ? 'checked' : ''; ?> class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-transparent border-2 appearance-none cursor-pointer outline-none" onchange="handleToggleChange(this.checked)"/>
                    <label for="toggleLevel" class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer border border-white/30"></label>
                    <div class="toggle-dot absolute block w-5 h-5 rounded-full shadow inset-y-0 left-0 mt-0.5 ml-0.5 pointer-events-none"></div>
                </div>
                <span id="status-level" class="font-semibold tracking-widest <?php echo $activationStartedAt ? 'text-white' : 'text-gray-400'; ?> text-sm"><?php echo $activationStartedAt ? 'ON' : 'OFF'; ?></span>
                <span id="on-timer" class="<?php echo $activationStartedAt ? '' : 'hidden'; ?> text-xs text-gray-400 tracking-wider tabular-nums">(00:00:00)</span>
            </div>

            <div id="level-media" class="<?php echo $activationStartedAt ? '' : 'hidden'; ?> transition-all duration-500 <?php echo $activationStartedAt ? 'opacity-100' : 'opacity-0'; ?> mb-8">
                <div class="overflow-hidden rounded shadow-2xl bg-black flex justify-center items-center py-6">
                    <?php if (isVideoFile($imagePath)): ?>
                        <video src="<?php echo htmlspecialchars($imagePath); ?>" controls class="w-full max-w-xs h-auto opacity-90 hover:opacity-100 transition-opacity duration-500"></video>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Level.<?php echo $level; ?> メディア" class="w-full max-w-xs h-auto object-contain opacity-90 hover:opacity-100 transition-opacity duration-500">
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <a href="mobile.php" class="text-xs text-gray-500 underline underline-offset-4 tracking-widest mt-6 inline-block">TOPに戻る</a>

        <div class="mt-8 pt-6 border-t border-white/10 text-left">
            <p class="text-xs text-gray-500 tracking-widest mb-3">HISTORY</p>
            <div id="on-history-list" class="flex flex-col gap-1.5 text-xs text-gray-400">
                <?php if (empty($activationHistory)): ?>
                    <span class="text-gray-600">記録はまだありません</span>
                <?php else: ?>
                    <?php foreach ($activationHistory as $entry):
                        $startTs = strtotime($entry['started_at']);
                        $endTs = strtotime($entry['ended_at']);
                        $durationSec = max(0, $endTs - $startTs);
                        $durStr = sprintf('%02d:%02d:%02d', intdiv($durationSec, 3600), intdiv($durationSec % 3600, 60), $durationSec % 60);
                        $dateStr = date('n/j H:i', $startTs);
                        $isTimeout = $entry['ended_reason'] === 'timeout';
                    ?>
                        <div class="flex items-center justify-between gap-2 bg-black/20 px-2.5 py-1.5 rounded">
                            <span><?php echo htmlspecialchars($dateStr); ?></span>
                            <span class="font-mono"><?php echo htmlspecialchars($durStr); ?></span>
                            <?php if ($isTimeout): ?>
                                <span class="text-yellow-400">自動終了(24h)</span>
                            <?php else: ?>
                                <span class="text-gray-500">手動OFF</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 下部の余白スペーサー：スクロール可能な領域を確保し、Safariの意図しないpull-to-refreshを防ぐ -->
    <div class="w-full shrink-0" style="height: 40vh;" aria-hidden="true"></div>

    <script>
        // Levelパスワードの保存・自動入力（Level番号ごとにlocalStorageへ保存。サーバーには送信しません）
        (function() {
            const level = <?php echo (int)$level; ?>;
            const storageKey = 'lw_level_password_' + level;
            const passwordInput = document.querySelector('input[name="level_password"]');
            const rememberCheckbox = document.getElementById('rememberLevelPassword');
            const rememberBox = document.getElementById('rememberLevelPasswordBox');
            const rememberCheckIcon = document.getElementById('rememberLevelPasswordCheckIcon');
            const levelForm = document.querySelector('form');
            if (!passwordInput || !rememberCheckbox || !levelForm) return;

            function syncVisual() {
                if (rememberCheckbox.checked) {
                    rememberBox.classList.add('bg-white', 'border-white');
                    rememberCheckIcon.classList.remove('hidden');
                } else {
                    rememberBox.classList.remove('bg-white', 'border-white');
                    rememberCheckIcon.classList.add('hidden');
                }
            }
            rememberCheckbox.addEventListener('change', syncVisual);

            const savedPassword = localStorage.getItem(storageKey);
            if (savedPassword !== null) {
                passwordInput.value = savedPassword;
                rememberCheckbox.checked = true;
            }
            syncVisual();

            levelForm.addEventListener('submit', function() {
                if (rememberCheckbox.checked) {
                    localStorage.setItem(storageKey, passwordInput.value);
                } else {
                    localStorage.removeItem(storageKey);
                }
            });
        })();

        // Levelパスワードの表示／非表示切り替え
        function toggleLevelPassword(button) {
            const input = button.parentElement.querySelector('input');
            const willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            button.querySelector('.eye-open').classList.toggle('hidden', willShow);
            button.querySelector('.eye-closed').classList.toggle('hidden', !willShow);
            button.setAttribute('aria-label', willShow ? 'パスワードを隠す' : 'パスワードを表示');
        }

        function toggleImage(elementId, statusId, isChecked) {
            const targetElement = document.getElementById(elementId);
            const statusElement = document.getElementById(statusId);
            if (isChecked) {
                targetElement.classList.remove('hidden');
                setTimeout(() => { targetElement.classList.add('opacity-100'); }, 10);
                statusElement.textContent = 'ON';
                statusElement.classList.replace('text-gray-400', 'text-white');
            } else {
                targetElement.classList.remove('opacity-100');
                setTimeout(() => { targetElement.classList.add('hidden'); }, 300);
                statusElement.textContent = 'OFF';
                statusElement.classList.replace('text-white', 'text-gray-400');
            }
        }

        // 「技術発生」の状態はサーバー側(DB)で管理する。クライアントはstarted_atを元に表示するだけで、
        // バックグラウンドで何時間放置されようが、開いた瞬間に正しい経過時間を計算できる。
        (function() {
            const level = <?php echo (int)$level; ?>;
            const csrfToken = <?php echo json_encode($csrfToken); ?>;
            const onTimerEl = document.getElementById('on-timer');
            const toggleCheckbox = document.getElementById('toggleLevel');
            let timerInterval = null;
            let serverStartedAtMs = <?php echo $activationStartedAt ? (strtotime($activationStartedAt) * 1000) : 'null'; ?>;

            function formatElapsed(ms) {
                const totalSeconds = Math.floor(ms / 1000);
                const h = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
                const m = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
                const s = String(totalSeconds % 60).padStart(2, '0');
                return h + ':' + m + ':' + s;
            }

            function tick() {
                if (!serverStartedAtMs) return;
                onTimerEl.textContent = '(' + formatElapsed(Date.now() - serverStartedAtMs) + ')';
            }

            function startTicking() {
                onTimerEl.classList.remove('hidden');
                tick();
                if (timerInterval) clearInterval(timerInterval);
                timerInterval = setInterval(tick, 1000);
            }

            function stopTicking() {
                onTimerEl.classList.add('hidden');
                if (timerInterval) clearInterval(timerInterval);
                timerInterval = null;
            }

            if (serverStartedAtMs) startTicking();

            // トグル操作 → サーバーへON/OFFをPOSTし、成功した場合のみ画面に反映する
            window.handleToggleChange = function(isChecked) {
                toggleCheckbox.disabled = true;
                const body = new URLSearchParams({
                    action: isChecked ? 'start' : 'stop',
                    level: String(level),
                    csrf_token: csrfToken
                });
                fetch('toggle_level.php', { method: 'POST', body: body })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        toggleCheckbox.disabled = false;
                        if (data.error) {
                            // 失敗時はトグルを元の状態に戻す
                            toggleCheckbox.checked = !isChecked;
                            return;
                        }
                        serverStartedAtMs = data.startedAtMs;
                        toggleImage('level-media', 'status-level', !!serverStartedAtMs);
                        if (serverStartedAtMs) startTicking(); else { stopTicking(); refreshHistory(); }
                    })
                    .catch(function() {
                        toggleCheckbox.disabled = false;
                        toggleCheckbox.checked = !isChecked;
                    });
            };

            // フォアグラウンドに戻った瞬間、サーバーの最新状態と同期する
            // （バックグラウンド中にCronによる自動終了(24h)が行われていた場合の反映も兼ねる）
            function resyncWithServer() {
                const body = new URLSearchParams({ action: 'status', level: String(level), csrf_token: csrfToken });
                fetch('toggle_level.php', { method: 'POST', body: body })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.error) return;
                        const wasOn = !!serverStartedAtMs;
                        serverStartedAtMs = data.startedAtMs;
                        const isOn = !!serverStartedAtMs;
                        if (wasOn !== isOn) {
                            toggleCheckbox.checked = isOn;
                            toggleImage('level-media', 'status-level', isOn);
                            if (isOn) startTicking(); else { stopTicking(); refreshHistory(); }
                        } else if (isOn) {
                            tick();
                            if (!timerInterval) startTicking();
                        }
                    })
                    .catch(function() {});
            }

            function refreshHistory() {
                // 履歴はページ再読み込み時にサーバーから描画されるため、OFF直後は簡易的に再読み込みする
                window.location.reload();
            }

            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') resyncWithServer();
            });
            window.addEventListener('pageshow', resyncWithServer);
            window.addEventListener('focus', resyncWithServer);
        })();

        const canvas = document.getElementById('waveCanvas');
        const ctx = canvas.getContext('2d');
        let width, height, time = 0;
        function resize() { width = canvas.width = window.innerWidth; height = canvas.height = window.innerHeight; }
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
