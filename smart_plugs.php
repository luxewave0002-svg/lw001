<?php
require_once 'db.php';

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// PC版表示の強制フラグをセッションに保存
if (isset($_GET['force_pc'])) {
    if ($_GET['force_pc'] == '1') {
        $_SESSION['force_pc'] = true;
    } elseif ($_GET['force_pc'] == '0') {
        unset($_SESSION['force_pc']);
    }
}

if (empty($_SESSION['force_pc']) && isMobile()) {
    header("Location: mobile_smart_plugs.php");
    exit;
}

$csrfToken = generateCsrfToken();

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM devices WHERE user_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$userId]);
$userDevices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Plugs - LUXE WAVE</title>
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

        .toggle-checkbox:checked { right: 0; border-color: #ffffff; }
        .toggle-checkbox:checked + .toggle-label { background-color: #ffffff; }
        .toggle-checkbox { right: 0; z-index: 1; border-color: #4b5563; transition: all 0.3s; }
        .toggle-label { width: 3rem; height: 1.5rem; background-color: #4b5563; border-radius: 9999px; transition: all 0.3s; }
        .toggle-dot { top: 0.125rem; left: 0.125rem; width: 1.25rem; height: 1.25rem; background-color: #1f2937; border-radius: 50%; transition: all 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .toggle-checkbox:checked ~ .toggle-dot { transform: translateX(1.5rem); background-color: #000000; }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-blue-800 to-white bg-fixed text-white min-h-screen overflow-x-hidden antialiased selection:bg-white selection:text-black relative z-0">

    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <header class="fixed top-0 left-0 w-full z-50 p-4 md:p-6 md:px-12 flex flex-col md:flex-row justify-between items-center bg-gradient-to-b from-black/60 to-transparent">
        <a href="index.php" class="brand-font text-2xl md:text-2xl tracking-[0.2em] font-light cursor-pointer mb-3 md:mb-0">
            LW
        </a>
        <nav class="flex flex-wrap justify-center md:justify-end gap-x-4 gap-y-3 md:gap-x-6 text-[10px] md:text-xs tracking-[0.15em] uppercase text-gray-300">
            <a href="index.php" class="hover:text-white transition-colors duration-300 focus:outline-none">L/W</a>
            <a href="dashboard.php" class="hover:text-white transition-colors duration-300 focus:outline-none">Dashboard</a>
            <?php if (isMobile()): ?>
            <a href="?force_pc=0" class="hover:text-white transition-colors duration-300 focus:outline-none">スマホ版に戻る</a>
            <?php endif; ?>
            <a href="?logout=1" class="hover:text-white transition-colors duration-300 focus:outline-none">Logout</a>
        </nav>
    </header>

    <main class="min-h-screen flex flex-col items-center justify-start pt-24 md:pt-32 pb-24 px-4 md:px-6 relative z-10">

        <section class="text-center w-full max-w-4xl active">
            <div class="flex flex-col items-center w-full">
                <h1 class="brand-font text-lg md:text-3xl font-extralight tracking-[0.25em] uppercase text-white drop-shadow-2xl opacity-90 pl-[0.25em] mb-12">
                    Smart Plugs
                </h1>

                <!-- SwitchBot Control -->
                <div class="mb-12 flex flex-col items-center bg-black/20 border border-white/10 p-6 rounded-2xl backdrop-blur-md shadow-2xl w-full max-w-3xl mx-auto transition-all hover:bg-black/30">
                    <span class="mb-5 font-light text-gray-200 tracking-[0.15em] uppercase text-xs">System Control</span>
                    
                    <?php if(empty($userDevices)): ?>
                        <div class="bg-white/5 p-8 rounded-xl border border-white/10 text-center w-full">
                            <p class="text-gray-300 text-sm mb-4">デバイスが登録されていません。</p>
                            <a href="add_device.php" class="text-xs border border-white/30 px-4 py-2 rounded hover:bg-white/10 transition">新しく機器を登録する</a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 w-full">
                            <?php foreach($userDevices as $device): ?>
                                <div id="device_card_<?php echo $device['device_id']; ?>" class="flex flex-col items-center bg-white/5 p-4 rounded-xl border border-white/10 transition-colors duration-500">
                                    <span class="mb-3 text-[10px] tracking-widest text-center leading-relaxed <?php echo htmlspecialchars($device['color'] ?? 'text-gray-400'); ?>">
                                        <span class="text-2xl block mb-2"><?php echo htmlspecialchars($device['icon'] ?? '🔌'); ?></span>
                                        <?php echo htmlspecialchars($device['device_name']); ?><br>
                                        <span class="text-[10px] text-yellow-400/80 font-mono tracking-widest">Lv.<?php echo htmlspecialchars($device['level'] ?? 1); ?></span><br>
                                        <?php if (!empty($device['level_updated_at'])): ?>
                                            <span class="text-[8px] text-gray-500 font-mono tracking-widest">Updated: <?php echo htmlspecialchars(date('Y.m.d', strtotime($device['level_updated_at']))); ?></span><br>
                                        <?php endif; ?>
                                        <span class="text-[8px] opacity-70 text-gray-500"><?php echo htmlspecialchars($device['device_id']); ?></span>
                                    </span>
                                    <div class="flex items-center justify-center">
                                        <span class="mr-4 font-light text-gray-400 tracking-wider text-xs">OFF</span>
                                        <div class="relative inline-block w-12 h-6 align-middle select-none">
                                            <input type="checkbox" name="toggleSwitchBot_<?php echo $device['device_id']; ?>" id="toggleSwitchBot_<?php echo $device['device_id']; ?>" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-transparent border-2 appearance-none cursor-pointer outline-none" onchange="toggleSwitchBotPlug('<?php echo $device['device_id']; ?>', this.checked)"/>
                                            <label for="toggleSwitchBot_<?php echo $device['device_id']; ?>" class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer border border-white/30"></label>
                                            <div class="toggle-dot absolute block w-5 h-5 rounded-full shadow inset-y-0 left-0 mt-0.5 ml-0.5 pointer-events-none"></div>
                                        </div>
                                        <span class="ml-4 font-light text-gray-400 tracking-wider text-xs">ON</span>
                                    </div>
                                    <span id="switchbot-status-<?php echo $device['device_id']; ?>" class="mt-4 text-[10px] text-gray-400 tracking-widest transition-colors duration-300">Ready</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 独自技術起動ボタン -->
                <?php if(!empty($userDevices)): ?>
                <div class="mb-12 w-full flex justify-center">
                    <button type="button" id="switchbot-toggle" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-3 rounded-full tracking-[0.2em] text-sm transition-all duration-300 focus:outline-none shadow-lg backdrop-blur-sm">
                        独自技術を起動する
                    </button>
                </div>
                <?php endif; ?>

                <div class="mb-12 w-full flex justify-center">
                    <a href="add_device.php" class="border border-blue-500/50 bg-blue-900/20 text-blue-200 hover:text-white hover:bg-blue-800/40 px-8 py-3 rounded-full tracking-widest text-sm transition-all duration-300 shadow-lg backdrop-blur-sm flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        新しい機器を追加する
                    </a>
                </div>

                <div class="mt-16 text-center">
                    <a href="index.php" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-2.5 rounded-full tracking-[0.2em] text-xs transition-all duration-300 inline-block">BACK TO HOME</a>
                </div>
            </div>
        </section>

    </main>

    <script>
        // --- 背景の周波数波（キャンバスアニメーション） ---
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
                ctx.beginPath();
                ctx.strokeStyle = wave.color;
                ctx.lineWidth = 1;
                for (let x = 0; x <= width; x += 4) {
                    const envelope = Math.sin(x * 0.001 + time * 0.01) * 0.8 + 0.2;
                    const y = height / 2 + Math.sin(x * wave.frequency + time * wave.speed) * wave.amplitude * envelope;
                    if (x === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                }
                ctx.stroke();
            });
            time += 1;
            requestAnimationFrame(drawWaves);
        }
        drawWaves();

        let plugIntendedStates = {}; // LUXE WAVE側で各デバイスをONにしているか記憶 { 'deviceId': true/false }

        // SwitchBot スマートプラグのON/OFF切り替え
        function toggleSwitchBotPlug(deviceId, isTurnOn) {
            plugIntendedStates[deviceId] = isTurnOn; // 意図した状態をセット
            const statusText = document.getElementById('switchbot-status-' + deviceId);
            const command = isTurnOn ? 'turnOn' : 'turnOff';
            
            // 通信中のステータス表示
            statusText.textContent = 'Processing...';
            statusText.className = 'mt-4 text-[10px] tracking-widest transition-colors duration-300 text-yellow-400';

            fetch('user_switchbot_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $csrfToken; ?>'
                },
                body: JSON.stringify({ action: command, device_id: deviceId })
            })
            .then(response => {
                return response.json().then(data => {
                    if (!data.success) throw new Error(data.error || 'Network response was not ok');
                });
            })
            .then(() => {
                // 成功時のステータス表示
                statusText.textContent = isTurnOn ? 'Power is ON' : 'Power is OFF';
                statusText.className = 'mt-4 text-[10px] tracking-widest transition-colors duration-300 text-green-400';
                setTimeout(() => { statusText.className = 'mt-4 text-[10px] tracking-widest transition-colors duration-300 text-gray-400'; }, 3000);
            })
            .catch(error => {
                console.error('Error:', error);
                
                // エラー時のステータス表示とトグルのリセット
                statusText.textContent = error.message || '通信エラーが発生しました';
                statusText.className = 'mt-4 text-[10px] tracking-widest transition-colors duration-300 text-red-400';
                document.getElementById('toggleSwitchBot_' + deviceId).checked = !isTurnOn;
                setTimeout(() => { statusText.textContent = 'Ready'; statusText.className = 'mt-4 text-[10px] tracking-widest transition-colors duration-300 text-gray-400'; }, 5000);
            });
        }

        // --- プラグの自動復旧（監視）機能 ---
        function monitorPlugStatus() {
            // タブが非アクティブ（裏側）の場合はAPIリクエストを行わない（制限対策）
            if (document.hidden) return;

            fetch('user_switchbot_status.php')
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.statuses) return;
                
                for (const [deviceId, currentPower] of Object.entries(data.statuses)) {
                    const statusText = document.getElementById('switchbot-status-' + deviceId);
                    const card = document.getElementById('device_card_' + deviceId);
                    const toggle = document.getElementById('toggleSwitchBot_' + deviceId);
                    
                    // API側でエラーが返ってきた場合
                    if (currentPower.startsWith('error: ')) {
                        if (statusText && statusText.textContent !== 'Processing...') {
                            statusText.innerHTML = '<span class="flex items-center justify-center gap-1 text-red-400"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>' + currentPower.substring(7) + '</span>';
                        }
                        if (card) {
                            card.classList.add('border-red-500/50', 'bg-red-900/20');
                            card.classList.remove('border-white/10', 'bg-white/5');
                        }
                        if (toggle) {
                            toggle.disabled = true;
                            toggle.classList.add('cursor-not-allowed', 'opacity-50');
                        }
                        continue; // エラー時は自動復旧判定をスキップ
                    } else {
                        // エラーから復旧した（直前がエラー表示だった）場合に緑色のバッジを表示
                        if (statusText && statusText.innerHTML.includes('text-red-400')) {
                             statusText.innerHTML = '<span class="inline-block bg-green-500/20 text-green-400 border border-green-500/50 px-2 py-0.5 rounded tracking-widest transition-all shadow-[0_0_10px_rgba(34,197,94,0.2)]">ONLINE</span>';
                             setTimeout(() => {
                                 if (statusText.innerHTML.includes('ONLINE')) {
                                     statusText.textContent = 'Ready';
                                     statusText.className = 'mt-4 text-[10px] tracking-widest transition-colors duration-300 text-gray-400';
                                 }
                             }, 3000);
                        }
                        if (card) {
                            card.classList.remove('border-red-500/50', 'bg-red-900/20');
                            card.classList.add('border-white/10', 'bg-white/5');
                        }
                        if (toggle) {
                            toggle.disabled = false;
                            toggle.classList.remove('cursor-not-allowed', 'opacity-50');
                        }
                    }
                    
                    if (plugIntendedStates[deviceId] !== undefined) {
                        const intendedPower = plugIntendedStates[deviceId] ? 'on' : 'off';
                        
                        // 意図した状態（Web画面の状態）と実際の状態が違う場合、強制的に元に戻す
                        if (currentPower !== intendedPower) {
                            if(statusText) {
                                statusText.textContent = 'Auto Recovering...';
                                statusText.className = 'mt-4 text-[10px] tracking-widest transition-colors duration-300 text-yellow-400';
                            }
                            
                            const action = plugIntendedStates[deviceId] ? 'turnOn' : 'turnOff';

                            fetch('user_switchbot_api.php', {
                                method: 'POST',
                                headers: { 
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': '<?php echo $csrfToken; ?>'
                                },
                                body: JSON.stringify({ action: action, device_id: deviceId })
                            })
                            .then(() => {
                                const toggleBtn = document.getElementById('toggleSwitchBot_' + deviceId);
                                if(toggleBtn) toggleBtn.checked = plugIntendedStates[deviceId];
                                if(statusText) {
                                    statusText.textContent = 'Recovered (自動復旧)';
                                    statusText.className = 'mt-4 text-[10px] tracking-widest transition-colors duration-300 text-green-400';
                                }
                            });
                        }
                    }
                }
            })
            .catch(error => console.error('Status Check Error:', error));
        }
        setInterval(monitorPlugStatus, 1000); // 1秒ごとに監視を実行

        // ページ読み込み時の処理
        window.addEventListener('DOMContentLoaded', () => {
            // 独自技術起動ボタンの処理
            const switchbotToggleBtn = document.getElementById('switchbot-toggle');
            if (switchbotToggleBtn) {
                switchbotToggleBtn.addEventListener('click', () => {
                    // ボタンの状態を「通信中」に変更し連打を防止
                    switchbotToggleBtn.textContent = '通信中...';
                    switchbotToggleBtn.disabled = true;
                    switchbotToggleBtn.classList.add('opacity-50', 'cursor-not-allowed');

                    // すべてのデバイスをONにするように状態を更新
                    const promises = [];
                    <?php foreach($userDevices as $device): ?>
                        plugIntendedStates['<?php echo $device['device_id']; ?>'] = true;
                        var toggle_<?php echo $device['device_id']; ?> = document.getElementById('toggleSwitchBot_<?php echo $device['device_id']; ?>');
                        if(toggle_<?php echo $device['device_id']; ?>) {
                            toggle_<?php echo $device['device_id']; ?>.checked = true;
                        }
                        promises.push(
                            fetch('user_switchbot_api.php', {
                                method: 'POST',
                                headers: { 
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': '<?php echo $csrfToken; ?>'
                                },
                                body: JSON.stringify({ action: 'turnOn', device_id: '<?php echo $device['device_id']; ?>' })
                            }).then(res => res.json())
                        );
                    <?php endforeach; ?>

                    Promise.all(promises)
                    .then(results => {
                        const allSuccess = results.every(r => r.success);
                        switchbotToggleBtn.textContent = allSuccess ? '起動完了' : '通信エラー';
                    })
                    .catch(error => {
                        console.error('Fetch Error:', error);
                        switchbotToggleBtn.textContent = '通信エラー';
                    })
                    .finally(() => {
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