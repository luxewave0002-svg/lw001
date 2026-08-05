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

// Levelパスワードの照合処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_level_password') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die('CSRF token validation failed. 不正なリクエストです。');
    }

    $inputPassword = $_POST['level_password'] ?? '';

    if (!isset($_SESSION['user_id'])) {
        $levelPasswordError = 'ログインが必要です。';
    } else {
        $correctPassword = getLevelPassword($pdo, $_SESSION['user_id'], $level);

        if ($correctPassword !== null && hash_equals((string)$correctPassword, $inputPassword)) {
            $_SESSION['unlocked_levels'][$level] = levelUnlockFingerprint($correctPassword);
        } else {
            $levelPasswordError = 'パスワードが間違っています。';
        }
    }
}

// このLevelを閲覧できるか（DBのパスワードと毎回照合するので、削除されれば再ロックされる）
$isLocked = !isLevelUnlocked($pdo, $_SESSION['user_id'] ?? null, $level);

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
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black text-white min-h-screen flex flex-col items-center justify-center p-6 relative z-0 overflow-hidden">

    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <div class="text-center bg-black/40 p-8 md:p-10 rounded-2xl border border-white/20 backdrop-blur-md shadow-2xl w-full max-w-sm relative z-10">
        <h1 class="brand-font text-2xl font-extralight tracking-[0.2em] mb-8">Level.<?php echo $level; ?></h1>

        <?php if ($isLocked): ?>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <p class="text-gray-300 text-sm mb-6">このLevelを見るにはログインが必要です。</p>
                <a href="mobile_login.php" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white py-3.5 rounded-full tracking-widest text-sm transition-all shadow-lg inline-block px-8">LOGIN</a>
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
                    <button type="submit" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white py-3 rounded-full tracking-widest text-sm transition-all">UNLOCK</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="flex items-center justify-center mb-8 gap-4">
                <span class="font-light text-gray-200 tracking-wider text-sm">技術発生</span>
                <div class="relative inline-block w-12 h-6 align-middle select-none">
                    <input type="checkbox" name="toggleLevel" id="toggleLevel" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-transparent border-2 appearance-none cursor-pointer outline-none" onchange="toggleImage('level-media', 'status-level', this.checked)"/>
                    <label for="toggleLevel" class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer border border-white/30"></label>
                    <div class="toggle-dot absolute block w-5 h-5 rounded-full shadow inset-y-0 left-0 mt-0.5 ml-0.5 pointer-events-none"></div>
                </div>
                <span id="status-level" class="font-semibold tracking-widest text-gray-400 text-sm">OFF</span>
            </div>

            <div id="level-media" class="hidden transition-all duration-500 opacity-0 mb-8">
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
    </div>

    <script>
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
</body>
</html>
