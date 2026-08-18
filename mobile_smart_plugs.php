<?php
require_once 'db.php';

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: mobile_login.php");
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: mobile_login.php");
    exit;
}

$csrfToken = generateCsrfToken();
$userId = $_SESSION['user_id'];

// 登録済みデバイス一覧を取得
$stmt = $pdo->prepare("SELECT * FROM devices WHERE user_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$userId]);
$userDevices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mobile Smart Plugs - LUXE WAVE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: sans-serif; font-weight: 300; background-color: #050505; }
        .toggle-checkbox:checked { right: 0; border-color: #ffffff; }
        .toggle-checkbox:checked + .toggle-label { background-color: #ffffff; }
        .toggle-checkbox { right: 0; z-index: 1; border-color: #4b5563; transition: all 0.3s; }
        .toggle-label { width: 3.5rem; height: 1.75rem; background-color: #4b5563; border-radius: 9999px; transition: all 0.3s; }
        .toggle-dot { top: 0.125rem; left: 0.125rem; width: 1.5rem; height: 1.5rem; background-color: #1f2937; border-radius: 50%; transition: all 0.3s; }
        .toggle-checkbox:checked ~ .toggle-dot { transform: translateX(1.75rem); background-color: #000000; }
    </style>
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="apple-touch-icon.png?v=2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="LUXE WAVE">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#000000">

<script>
    // PWA再起動時に直前のページへ戻すため、現在地をlocalStorageに記憶する
    localStorage.setItem('lw_last_page', location.pathname + location.search);
</script>
</head>
<body class="text-white min-h-screen p-4 pb-20">
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


    <header class="flex justify-between items-center mb-8 mt-2 border-b border-white/10 pb-4">
        <h1 class="text-xl tracking-widest uppercase">Smart Plugs</h1>
        <div class="flex gap-2">
            <a href="mobile.php" class="text-[10px] bg-white/10 px-3 py-1.5 rounded tracking-widest text-gray-300">L/W</a>
            <a href="?logout=1" class="text-[10px] bg-white/10 px-3 py-1.5 rounded tracking-widest text-gray-300">LOGOUT</a>
        </div>
    </header>

    <div class="flex flex-col gap-6">
        <!-- 独自技術起動ボタン -->
        <?php if(!empty($userDevices)): ?>
        <div class="w-full flex justify-center mb-4">
            <button type="button" id="switchbot-toggle" class="bg-blue-600/80 hover:bg-blue-600 text-white w-full py-4 rounded-xl tracking-[0.2em] text-sm transition-all duration-300 focus:outline-none shadow-lg">
                独自技術を起動する
            </button>
        </div>
        <?php endif; ?>

        <?php if(empty($userDevices)): ?>
            <div class="text-center py-10 bg-white/5 rounded-2xl border border-white/10">
                <p class="text-sm text-gray-400 mb-4">デバイスが登録されていません。</p>
                <a href="add_device.php" class="text-xs text-gray-300 underline underline-offset-4">新しく機器を登録する</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php foreach($userDevices as $device): ?>
                    <div id="device_card_<?php echo $device['device_id']; ?>" class="bg-white/5 border border-white/10 p-5 rounded-2xl flex flex-col gap-3 transition-colors duration-500">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-base font-medium <?php echo htmlspecialchars($device['color'] ?? 'text-gray-100'); ?>">
                                    <span class="mr-1"><?php echo htmlspecialchars($device['icon'] ?? '🔌'); ?></span>
                                    <?php echo htmlspecialchars($device['device_name']); ?>
                                </h3>
                                <p class="text-[10px] text-gray-500 font-mono mt-1">ID: <?php echo htmlspecialchars($device['device_id']); ?></p>
                                <p class="text-xs text-yellow-400/80 tracking-widest font-mono mt-2">
                                    Lv. <?php echo htmlspecialchars($device['level'] ?? 1); ?>
                                    <?php if (!empty($device['level_updated_at'])): ?>
                                        <span class="text-[10px] text-gray-500 ml-1">Updated: <?php echo htmlspecialchars(date('Y.m.d', strtotime($device['level_updated_at']))); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="relative inline-block w-14 h-7">
                                <input type="checkbox" id="toggleSwitchBot_<?php echo $device['device_id']; ?>" class="toggle-checkbox absolute block w-7 h-7 rounded-full bg-transparent border-2 appearance-none cursor-pointer outline-none" 
                                    onchange="toggleSwitchBotPlug('<?php echo $device['device_id']; ?>', this.checked)"/>
                                <label for="toggleSwitchBot_<?php echo $device['device_id']; ?>" class="toggle-label block overflow-hidden h-7 rounded-full cursor-pointer border border-white/30"></label>
                                <div class="toggle-dot absolute block w-6 h-6 rounded-full shadow inset-y-0 left-0 mt-0.5 ml-0.5 pointer-events-none"></div>
                            </div>
                        </div>
                        <p id="switchbot-status-<?php echo $device['device_id']; ?>" class="text-right text-[10px] tracking-widest text-gray-500">Ready</p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="w-full flex justify-center mt-6">
        <a href="add_device.php" class="border border-blue-500/50 bg-blue-900/20 text-blue-200 hover:text-white hover:bg-blue-800/40 px-6 py-3.5 rounded-full tracking-widest text-sm transition-all duration-300 shadow-lg flex items-center justify-center gap-2 w-full">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            新しい機器を追加する
        </a>
    </div>

    <div class="mt-12 flex justify-center flex-col gap-6 items-center">
        <a href="smart_plugs.php?force_pc=1" class="text-[10px] text-gray-600 bg-white/5 px-4 py-2 rounded-full">PC版サイトを表示する</a>
    </div>

    <script>
        let plugIntendedStates = {};

        function toggleSwitchBotPlug(deviceId, isTurnOn) {
            plugIntendedStates[deviceId] = isTurnOn;
            const statusText = document.getElementById('switchbot-status-' + deviceId);
            const command = isTurnOn ? 'turnOn' : 'turnOff';
            
            statusText.textContent = 'Processing...';
            statusText.className = 'text-right text-[10px] tracking-widest text-yellow-400';

            fetch('user_switchbot_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?php echo $csrfToken; ?>' },
                body: JSON.stringify({ action: command, device_id: deviceId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) { 
                    statusText.textContent = isTurnOn ? 'Power is ON' : 'Power is OFF'; 
                    statusText.className = 'text-right text-[10px] tracking-widest text-green-400'; 
                    setTimeout(() => { statusText.className = 'text-right text-[10px] tracking-widest text-gray-500'; }, 3000);
                }
                else { throw new Error(data.error || '通信失敗'); }
            })
            .catch(err => { 
                statusText.textContent = err.message; 
                statusText.className = 'text-right text-[10px] tracking-widest text-red-400'; 
                document.getElementById('toggleSwitchBot_' + deviceId).checked = !isTurnOn; 
                setTimeout(() => { statusText.textContent = 'Ready'; statusText.className = 'text-right text-[10px] tracking-widest text-gray-500'; }, 5000);
            });
        }

        function monitorPlugStatus() {
            // タブが非アクティブ（裏側）の場合はAPIリクエストを行わない（制限対策）
            if (document.hidden) return;

            fetch('user_switchbot_status.php')
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.statuses) return;
                
                for (const [deviceId, currentPower] of Object.entries(data.statuses)) {
                    const statusText = document.getElementById('switchbot-status-' + deviceId);
                    const toggle = document.getElementById('toggleSwitchBot_' + deviceId);
                    
                    if (currentPower.startsWith('error: ')) continue;
                    
                    if (plugIntendedStates[deviceId] !== undefined) {
                        const intendedPower = plugIntendedStates[deviceId] ? 'on' : 'off';
                        if (currentPower !== intendedPower) {
                            if(statusText) { statusText.textContent = 'Auto Recovering...'; statusText.className = 'text-right text-[10px] tracking-widest text-yellow-400'; }
                            const action = plugIntendedStates[deviceId] ? 'turnOn' : 'turnOff';
                            fetch('user_switchbot_api.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?php echo $csrfToken; ?>' },
                                body: JSON.stringify({ action: action, device_id: deviceId })
                            }).then(() => {
                                if(toggle) toggle.checked = plugIntendedStates[deviceId];
                                if(statusText) { statusText.textContent = 'Recovered (自動復旧)'; statusText.className = 'text-right text-[10px] tracking-widest text-green-400'; }
                            });
                        }
                    }
                }
            }).catch(error => console.error('Status Check Error:', error));
        }
        setInterval(monitorPlugStatus, 1000);

        window.addEventListener('DOMContentLoaded', () => {
            // 画面を開いている間のセッション完全維持（5分ごと）
            setInterval(() => { fetch('keep_alive.php').catch(() => {}); }, 300000);

            const switchbotToggleBtn = document.getElementById('switchbot-toggle');
            if (switchbotToggleBtn) {
                switchbotToggleBtn.addEventListener('click', () => {
                    switchbotToggleBtn.textContent = '通信中...';
                    switchbotToggleBtn.disabled = true;
                    switchbotToggleBtn.classList.add('opacity-50', 'cursor-not-allowed');

                    const promises = [];
                    <?php foreach($userDevices as $device): ?>
                        plugIntendedStates['<?php echo $device['device_id']; ?>'] = true;
                        var toggle_<?php echo $device['device_id']; ?> = document.getElementById('toggleSwitchBot_<?php echo $device['device_id']; ?>');
                        if(toggle_<?php echo $device['device_id']; ?>) { toggle_<?php echo $device['device_id']; ?>.checked = true; }
                        promises.push(
                            fetch('user_switchbot_api.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?php echo $csrfToken; ?>' },
                                body: JSON.stringify({ action: 'turnOn', device_id: '<?php echo $device['device_id']; ?>' })
                            }).then(res => res.json())
                        );
                    <?php endforeach; ?>

                    Promise.all(promises)
                    .then(results => {
                        const allSuccess = results.every(r => r.success);
                        switchbotToggleBtn.textContent = allSuccess ? '起動完了' : '通信エラー';
                    }).finally(() => {
                        setTimeout(() => {
                            switchbotToggleBtn.textContent = '独自技術を起動する';
                            switchbotToggleBtn.disabled = false;
                            switchbotToggleBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        }, 3000);
                    });
                });
            }
        });
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
</body>
</html>