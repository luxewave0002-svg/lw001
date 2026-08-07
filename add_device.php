<?php
require_once 'db.php';

requireLogin($pdo, 'login.php');

$csrfToken = generateCsrfToken();
$userId = $_SESSION['user_id'];
$message = '';
$message_class = '';
$add_success = false;

// デバイスの追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_device') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die('CSRF token validation failed.');
    }

    $deviceName = $_POST['device_name'] ?? '';
    $deviceId = $_POST['device_id'] ?? '';
    $apiToken = $_POST['token'] ?? '';
    $apiSecret = $_POST['secret'] ?? '';
    $icon = $_POST['icon'] ?? '🔌';
    $color = $_POST['color'] ?? 'text-gray-100';

    if ($deviceName && $deviceId && $apiToken && $apiSecret) {
        $stmt = $pdo->prepare("INSERT INTO devices (user_id, device_name, device_id, token, secret, icon, color) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$userId, $deviceName, $deviceId, $apiToken, $apiSecret, $icon, $color])) {
            $message = "デバイスを追加しました！";
            $message_class = 'success';
            $add_success = true;
        } else {
            $message = "追加に失敗しました。";
            $message_class = 'error';
        }
    }
}

// 最後に登録したTokenとSecretを取得
$stmt = $pdo->prepare("SELECT token, secret FROM devices WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$userId]);
$lastDevice = $stmt->fetch();
$lastToken = $lastDevice ? $lastDevice['token'] : '';
$lastSecret = $lastDevice ? $lastDevice['secret'] : '';

// スマホ判定で戻り先URLを切り替える
$returnUrl = (isMobile() && empty($_SESSION['force_pc'])) ? 'mobile_smart_plugs.php' : 'smart_plugs.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>機器の追加 - LUXE WAVE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: sans-serif; font-weight: 300; }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black min-h-screen text-white relative z-0 overflow-x-hidden p-4 md:p-6 pb-20">
    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <div class="max-w-2xl mx-auto mt-4 md:mt-10">
        <header class="flex justify-between items-center mb-8 border-b border-white/10 pb-4">
            <h1 class="text-xl tracking-widest uppercase">Add Device</h1>
            <a href="<?php echo $returnUrl; ?>" class="text-[10px] bg-white/10 px-3 py-1.5 rounded tracking-widest text-gray-300 hover:text-white transition-colors">戻る</a>
        </header>

        <?php if($message): ?>
            <div class="mb-6 px-4 py-3 rounded text-sm <?php echo $message_class === 'success' ? 'bg-green-900/50 border border-green-500 text-green-200' : 'bg-red-900/50 border border-red-500 text-red-200'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php if($message_class === 'success'): ?>
                <div class="text-center mb-8 text-xs text-gray-400 tracking-widest">
                    自動的にプラグ画面に戻ります...
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="bg-black/30 border border-white/10 p-5 md:p-8 rounded-2xl backdrop-blur-sm flex flex-col gap-5">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-blue-900/20 border border-blue-500/30 p-3 rounded-lg gap-3 sm:gap-0 my-1">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-400 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <span class="text-[10px] md:text-xs text-blue-100">Token と Secret の調べ方がわからない場合はこちら</span>
                </div>
                <a href="how_to_get_token.php" target="_blank" class="flex items-center justify-center gap-1 text-[10px] md:text-xs bg-blue-600/40 hover:bg-blue-600/70 text-white border border-blue-400/50 px-4 py-2 rounded transition-colors tracking-wider whitespace-nowrap w-full sm:w-auto shadow-lg">
                    <span>調べ方を見る</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                </a>
            </div>

            <!-- Step 1: Token と Secret を入力してデバイス一覧を取得 -->
            <div id="fetch_devices_section" class="flex flex-col gap-4">
                <p class="text-[10px] md:text-xs text-gray-400">Token と Secret を入力して、連携可能なデバイスを検索します。</p>
                
                <?php if ($lastToken && $lastSecret): ?>
                <button type="button" onclick="fetchDevicesWithSavedCredentials('<?php echo htmlspecialchars($lastToken, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lastSecret, ENT_QUOTES); ?>')" class="bg-indigo-600/80 hover:bg-indigo-600 text-white py-2.5 rounded tracking-widest text-xs md:text-sm border border-indigo-400/50 w-full transition-colors focus:outline-none flex items-center justify-center gap-2 shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    前回登録したアカウントからデバイスを検索
                </button>
                <div class="flex items-center text-[10px] md:text-xs text-gray-500 my-1">
                    <div class="flex-grow border-t border-white/10"></div>
                    <span class="px-3 tracking-wider">OR</span>
                    <div class="flex-grow border-t border-white/10"></div>
                </div>
                <?php endif; ?>

                <input type="text" id="api_token" placeholder="Token" required class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <input type="text" id="api_secret" placeholder="Secret" required class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <button type="button" id="btn_fetch_devices" onclick="fetchDevicesFromAPI()" class="bg-blue-600/80 hover:bg-blue-600 text-white py-2.5 rounded tracking-widest text-xs md:text-sm border border-blue-400/50 w-full transition-colors focus:outline-none">
                    <?php echo ($lastToken && $lastSecret) ? '新しいアカウントで検索' : 'デバイス一覧を検索する'; ?>
                </button>
                <p id="fetch_status" class="text-xs hidden"></p>
            </div>

            <!-- Step 2: 取得したデバイスを選択して追加 -->
            <form method="POST" id="add_device_form" class="hidden flex-col gap-4 border-t border-white/20 pt-4 mt-2">
                <input type="hidden" name="action" value="add_device">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="token" id="hidden_token">
                <input type="hidden" name="secret" id="hidden_secret">

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] text-gray-400">登録するデバイスを選択</label>
                    <select id="device_selector" onchange="onDeviceSelected()" required class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                        <option value="">選択してください</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[10px] text-gray-400">デバイス名 (自由に変更可能)</label>
                    <input type="text" name="device_name" id="input_device_name" placeholder="デバイス名" required class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                </div>
                
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] text-gray-400">Device ID (自動入力)</label>
                    <input type="text" name="device_id" id="input_device_id" placeholder="Device ID" required readonly class="bg-black/50 border border-white/10 text-gray-400 rounded px-4 py-2.5 text-sm w-full cursor-not-allowed focus:outline-none">
                </div>
                
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] text-gray-400">アイコン / カラー</label>
                    <div class="flex gap-2">
                        <input type="text" name="icon" value="🔌" required class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-1/4 text-center focus:outline-none focus:border-white/50">
                        <select name="color" class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-3/4 focus:outline-none focus:border-white/50">
                            <option value="text-gray-100">ホワイト</option>
                            <option value="text-red-400">レッド</option>
                            <option value="text-blue-400">ブルー</option>
                            <option value="text-green-400">グリーン</option>
                            <option value="text-yellow-400">イエロー</option>
                            <option value="text-purple-400">パープル</option>
                            <option value="text-pink-400">ピンク</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex items-center gap-4 mt-2">
                    <button type="submit" class="bg-white/20 hover:bg-white/30 text-white px-6 py-3 rounded tracking-widest text-sm border border-white/30 transition-colors w-full">ADD DEVICE</button>
                    <button type="button" onclick="resetFetchForm()" class="text-xs text-gray-400 hover:text-white transition-colors whitespace-nowrap">やり直す</button>
                </div>
            </form>

            <div class="text-right mt-2" id="manual_input_link">
                <button type="button" onclick="showManualInput()" class="text-[10px] text-gray-500 hover:text-gray-300 underline tracking-wider focus:outline-none">自動取得できない場合は手動で入力する</button>
            </div>
            
            <!-- 手動入力用フォーム (初期非表示) -->
            <form method="POST" id="manual_add_device_form" class="hidden flex-col gap-4 border-t border-white/20 pt-4 mt-2">
                <p class="text-xs text-gray-400">手動でデバイス情報を入力して登録します。</p>
                <input type="hidden" name="action" value="add_device">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="text" name="device_name" placeholder="デバイス名 (例: 自宅のプラグ)" required class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <input type="text" name="device_id" placeholder="Device ID (例: F85B1B276B1A)" required class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <input type="text" name="token" placeholder="Token" required class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <input type="text" name="secret" placeholder="Secret" required class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-full focus:outline-none focus:border-white/50">
                <div class="flex gap-2">
                    <input type="text" name="icon" value="🔌" required class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-1/4 text-center focus:outline-none focus:border-white/50">
                    <select name="color" class="bg-white/5 border border-white/20 rounded px-4 py-2.5 text-sm text-white w-3/4 focus:outline-none focus:border-white/50">
                        <option value="text-gray-100">ホワイト</option>
                        <option value="text-red-400">レッド</option>
                        <option value="text-blue-400">ブルー</option>
                        <option value="text-green-400">グリーン</option>
                        <option value="text-yellow-400">イエロー</option>
                        <option value="text-purple-400">パープル</option>
                        <option value="text-pink-400">ピンク</option>
                    </select>
                </div>
                
                <div class="flex items-center gap-4 mt-2">
                    <button type="submit" class="bg-white/20 hover:bg-white/30 text-white px-6 py-3 rounded tracking-widest text-sm border border-white/30 transition-colors w-full">ADD DEVICE (手動)</button>
                    <button type="button" onclick="resetFetchForm()" class="text-xs text-gray-400 hover:text-white transition-colors whitespace-nowrap">自動検索に戻る</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        <?php if ($add_success): ?>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                document.body.style.transition = 'opacity 0.8s';
                document.body.style.opacity = '0';
                setTimeout(() => {
                    window.location.href = '<?php echo $returnUrl; ?>';
                }, 800);
            }, 1000);
        });
        <?php endif; ?>

        // --- デバイス自動取得機能 ---
        let fetchedDevices = [];

        function fetchDevicesWithSavedCredentials(savedToken, savedSecret) {
            document.getElementById('api_token').value = savedToken;
            document.getElementById('api_secret').value = savedSecret;
            fetchDevicesFromAPI();
        }

        function fetchDevicesFromAPI() {
            const token = document.getElementById('api_token').value.trim();
            const secret = document.getElementById('api_secret').value.trim();
            const statusEl = document.getElementById('fetch_status');
            const btn = document.getElementById('btn_fetch_devices');

            if (!token || !secret) {
                statusEl.textContent = 'TokenとSecretを入力してください。';
                statusEl.className = 'text-xs text-red-400';
                statusEl.classList.remove('hidden');
                return;
            }

            statusEl.classList.add('hidden');
            btn.textContent = '検索中...';
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');

            fetch('fetch_switchbot_devices.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $csrfToken; ?>'
                },
                body: JSON.stringify({ token: token, secret: secret })
            })
            .then(res => res.json())
            .then(data => {
                btn.textContent = 'デバイス一覧を検索する';
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');

                if (data.success) {
                    fetchedDevices = data.devices;
                    const selector = document.getElementById('device_selector');
                    selector.innerHTML = '<option value="">選択してください</option>';
                    
                    if (fetchedDevices.length === 0) {
                        statusEl.textContent = '登録されているプラグが見つかりませんでした。';
                        statusEl.className = 'text-xs text-yellow-400';
                        statusEl.classList.remove('hidden');
                        return;
                    }

                    fetchedDevices.forEach(device => {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.textContent = `${device.deviceName} (${device.deviceType})`;
                        selector.appendChild(option);
                    });

                    document.getElementById('fetch_devices_section').classList.add('hidden');
                    document.getElementById('manual_input_link').classList.add('hidden');
                    const addForm = document.getElementById('add_device_form');
                    addForm.classList.remove('hidden');
                    addForm.classList.add('flex');
                    
                    document.getElementById('hidden_token').value = token;
                    document.getElementById('hidden_secret').value = secret;
                    document.getElementById('input_device_name').value = '';
                    document.getElementById('input_device_id').value = '';
                } else {
                    statusEl.textContent = 'エラー: ' + data.error;
                    statusEl.className = 'text-xs text-red-400';
                    statusEl.classList.remove('hidden');
                }
            })
            .catch(err => {
                btn.textContent = 'デバイス一覧を検索する';
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                statusEl.textContent = '通信エラーが発生しました。';
                statusEl.className = 'text-xs text-red-400';
                statusEl.classList.remove('hidden');
            });
        }

        function onDeviceSelected() {
            const selectedId = document.getElementById('device_selector').value;
            const deviceNameInput = document.getElementById('input_device_name');
            const deviceIdInput = document.getElementById('input_device_id');

            if (selectedId) {
                const device = fetchedDevices.find(d => d.deviceId === selectedId);
                if (device) {
                    deviceNameInput.value = device.deviceName;
                    deviceIdInput.value = device.deviceId;
                }
            } else {
                deviceNameInput.value = '';
                deviceIdInput.value = '';
            }
        }

        function showManualInput() {
            document.getElementById('fetch_devices_section').classList.add('hidden');
            document.getElementById('manual_input_link').classList.add('hidden');
            document.getElementById('add_device_form').classList.add('hidden');
            document.getElementById('add_device_form').classList.remove('flex');
            
            const manualForm = document.getElementById('manual_add_device_form');
            manualForm.classList.remove('hidden');
            manualForm.classList.add('flex');
        }

        function resetFetchForm() {
            document.getElementById('fetch_devices_section').classList.remove('hidden');
            document.getElementById('manual_input_link').classList.remove('hidden');
            document.getElementById('add_device_form').classList.add('hidden');
            document.getElementById('add_device_form').classList.remove('flex');
            document.getElementById('manual_add_device_form').classList.add('hidden');
            document.getElementById('manual_add_device_form').classList.remove('flex');
            document.getElementById('fetch_status').classList.add('hidden');
        }

        const canvas = document.getElementById('waveCanvas'); const ctx = canvas.getContext('2d'); let width, height, time = 0;
        function resize() { width = canvas.width = window.innerWidth; height = canvas.height = window.innerHeight; }
        window.addEventListener('resize', resize); resize();
        function drawWaves() {
            ctx.clearRect(0, 0, width, height); const waves = [{ amplitude: 150, frequency: 0.002, speed: 0.015, color: 'rgba(255, 255, 255, 0.05)' }, { amplitude: 100, frequency: 0.004, speed: 0.02,  color: 'rgba(100, 150, 255, 0.15)' }, { amplitude: 60,  frequency: 0.006, speed: 0.03,  color: 'rgba(255, 255, 255, 0.03)' }];
            waves.forEach(wave => { ctx.beginPath(); ctx.strokeStyle = wave.color; ctx.lineWidth = 1; for (let x = 0; x <= width; x += 4) { const envelope = Math.sin(x * 0.001 + time * 0.01) * 0.8 + 0.2; const y = height / 2 + Math.sin(x * wave.frequency + time * wave.speed) * wave.amplitude * envelope; if (x === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y); } ctx.stroke(); });
            time += 1; requestAnimationFrame(drawWaves);
        } drawWaves();
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