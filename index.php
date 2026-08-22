<?php
require_once 'db.php';

// ログアウト処理
if (isset($_GET['logout'])) {
    logoutUser($pdo);
    header("Location: login.php");
    exit;
}

// PC版表示の強制フラグをセッションに保存（PC版を見るボタンを押した時用）
if (isset($_GET['force_pc'])) {
    if ($_GET['force_pc'] == '1') {
        $_SESSION['force_pc'] = true;
    } elseif ($_GET['force_pc'] == '0') {
        unset($_SESSION['force_pc']);
    }
}

// スマホからのアクセスで、かつ強制PC表示フラグがなければスマホ専用ページへリダイレクト
// if (empty($_SESSION['force_pc']) && isMobile()) {
//     header("Location: mobile.php");
//     exit;
// }

// HOMEはログイン必須（未ログインならログインページへ）
requireLogin($pdo, isMobile() && empty($_SESSION['force_pc']) ? 'mobile_login.php' : 'login.php');

require_once 'config.php';
// ----------------------------------------------------
$activePage = 'home'; // 初期表示ページ

// ログイン中ユーザーに発行されているLevelパスワード一覧を取得
$myLevelPasswords = [];
if (isset($_SESSION['user_id'])) {
    $myLevelPwStmt = $pdo->prepare("SELECT level, password FROM level_passwords WHERE user_id = ? AND revoked_at IS NULL AND password IS NOT NULL ORDER BY level ASC");
    $myLevelPwStmt->execute([$_SESSION['user_id']]);
    $myLevelPasswords = $myLevelPwStmt->fetchAll();
}
$csrfToken = generateCsrfToken();
$levelPasswordError = '';

// Levelパスワードの照合処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_level_password') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        // 不正/期限切れトークンの場合はエラー文言を出さず、通常のページ表示に戻す
        header("Location: index.php");
        exit;
    }

    $verifyLevel = $_POST['level'] ?? '';
    $inputPassword = $_POST['level_password'] ?? '';

    if (filter_var($verifyLevel, FILTER_VALIDATE_INT, array("options" => array("min_range"=>1, "max_range"=>10)))) {
        $activePage = 'test' . $verifyLevel;

        if (!isset($_SESSION['user_id'])) {
            $levelPasswordError = 'ログインが必要です。';
        } else {
            $correctPassword = getLevelPassword($pdo, $_SESSION['user_id'], $verifyLevel);

            if ($correctPassword !== null && hash_equals((string)$correctPassword, $inputPassword)) {
                $fingerprint = levelUnlockFingerprint($correctPassword);
                $_SESSION['unlocked_levels'][(int)$verifyLevel] = $fingerprint;
                setcookie('level_unlock_' . (int)$verifyLevel, $fingerprint, [
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
}

// ログイン中のユーザーが解除済みのLevel一覧（DBのパスワードと毎回照合）
$unlockedLevels = [];
foreach ([1, 2, 3, 4] as $lvl) {
    $unlockedLevels[$lvl] = isLevelUnlocked($pdo, $_SESSION['user_id'] ?? null, $lvl);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LUXE WAVE</title>
    <link rel="icon" href="favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@200;300&family=Noto+Sans+JP:wght@200;300&display=swap" rel="stylesheet">
    
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
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }
        body {
            font-family: 'Noto Sans JP', sans-serif;
            font-weight: 300;
        }
        .brand-font {
            font-family: 'Montserrat', sans-serif;
        }
        
        .page-content {
            display: none;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }
        .page-content.active {
            display: block;
            opacity: 1;
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

    <header class="fixed top-0 left-0 w-full z-50 p-4 md:p-6 md:px-12 flex flex-col md:flex-row justify-between items-center bg-gradient-to-b from-black/60 to-transparent">
        <div class="brand-font text-2xl md:text-2xl tracking-[0.2em] font-light cursor-pointer mb-3 md:mb-0" onclick="showPage('home')">
            LW
        </div>
        <nav class="flex flex-wrap justify-center md:justify-end gap-x-4 gap-y-3 md:gap-x-6 text-[10px] md:text-xs tracking-[0.15em] uppercase text-gray-300">
            <button onclick="showPage('home')" class="hover:text-white transition-colors duration-300 focus:outline-none">L/W</button>
            <button onclick="showPage('about')" class="hover:text-white transition-colors duration-300 focus:outline-none">About</button>
            <button onclick="showPage('info')" class="hover:text-white transition-colors duration-300 focus:outline-none">Info</button>
            <a href="smart_plugs.php" class="hover:text-white transition-colors duration-300 focus:outline-none">Smart Plugs</a>
            <?php if(isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php" class="hover:text-white transition-colors duration-300 focus:outline-none">Dashboard</a>
            <a href="?logout=1" onclick="[1,2,3,4].forEach(function(l){sessionStorage.removeItem('lw_level_on_since_'+l);})" class="hover:text-white transition-colors duration-300 focus:outline-none">Logout</a>
            <?php else: ?>
            <a href="login.php" class="hover:text-white transition-colors duration-300 focus:outline-none">Login</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="min-h-screen flex flex-col items-center justify-start pt-24 md:pt-32 pb-24 px-4 md:px-6 relative z-10">

        <section id="page-home" class="page-content text-center w-full max-w-4xl">
            <div class="flex flex-col items-center w-full">
                <h1 class="brand-font text-lg md:text-3xl lg:text-4xl font-extralight tracking-[0.25em] uppercase text-white drop-shadow-2xl opacity-90 pl-[0.25em] mb-12 md:mb-16">
                    Luxe Wave
                </h1>

                <div class="flex flex-wrap justify-center gap-4 w-full px-2 md:px-4">
                    <?php foreach([1, 2, 3, 4] as $i): ?>
                    <button onclick="showPage('test<?php echo $i; ?>')" class="w-28 sm:w-32 bg-white/5 hover:bg-white/10 border border-white/20 backdrop-blur-sm text-gray-200 hover:text-white py-4 rounded-md transition-all duration-300 tracking-widest font-light text-sm shadow-lg hover:shadow-white/10 hover:-translate-y-1">Level.<?php echo $i; ?></button>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($myLevelPasswords)): ?>
                <div class="mt-10 pt-8 border-t border-white/10 w-full max-w-xs">
                    <button type="button" id="lw-toggle-passwords" onclick="lwTogglePasswordList()" class="text-xs text-gray-400 hover:text-white tracking-widest border border-white/20 rounded-full px-5 py-2 transition-colors">PASSWORD</button>
                    <div id="lw-password-list" class="hidden mt-5 flex flex-col gap-2 text-left">
                        <?php foreach ($myLevelPasswords as $lvlPw): ?>
                            <div class="flex items-center justify-between gap-2 bg-black/30 border border-white/10 px-3 py-2 rounded-lg">
                                <span class="text-xs text-gray-400 shrink-0">Level.<?php echo (int)$lvlPw['level']; ?></span>
                                <span class="font-mono text-white text-sm truncate"><?php echo htmlspecialchars($lvlPw['password']); ?></span>
                                <button type="button" onclick="lwCopyLevelPassword(this, '<?php echo htmlspecialchars($lvlPw['password'], ENT_QUOTES); ?>')" aria-label="パスワードをコピー" class="text-gray-400 hover:text-white transition-colors p-1 shrink-0 focus:outline-none">
                                    <svg class="copy-icon w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                    </svg>
                                    <svg class="check-icon w-4 h-4 hidden text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="page-about" class="page-content w-full max-w-2xl bg-black/30 backdrop-blur-xl p-8 md:p-12 rounded-lg border border-white/20 shadow-2xl">
            <h2 class="text-2xl md:text-3xl font-light mb-6 border-b border-white/30 pb-4 tracking-wider text-white">About</h2>
            <p class="text-gray-200 leading-relaxed font-light tracking-wider">
                coming soon...
            </p>
            <div class="mt-10 text-center">
                <button onclick="showPage('home')" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-2.5 rounded-full tracking-[0.2em] text-xs transition-all duration-300 focus:outline-none">BACK TO HOME</button>
            </div>
        </section>

        <section id="page-info" class="page-content w-full max-w-2xl bg-black/30 backdrop-blur-xl p-8 md:p-12 rounded-lg border border-white/20 shadow-2xl">
            <h2 class="text-2xl md:text-3xl font-light mb-8 border-b border-white/30 pb-4 tracking-wider text-white">Info</h2>

            <div class="flex flex-col gap-4">
                <a href="https://note.com/luxewave" target="_blank" rel="noopener noreferrer"
                   class="group flex items-center justify-between bg-white/5 hover:bg-white/10 border border-white/20 hover:border-white/40 rounded-lg px-6 py-5 transition-all duration-300">
                    <span class="tracking-[0.2em] text-sm text-gray-200 group-hover:text-white">note</span>
                    <span class="flex items-center gap-2 text-[10px] tracking-widest text-gray-500 group-hover:text-gray-300">
                        note.com/luxewave
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </span>
                </a>

                <div class="flex items-center justify-between bg-black/20 border border-white/10 rounded-lg px-6 py-5">
                    <span class="tracking-[0.2em] text-sm text-gray-300">SNS</span>
                    <span class="text-[10px] tracking-widest text-gray-400">coming soon...</span>
                </div>

                <div class="flex items-center justify-between bg-black/20 border border-white/10 rounded-lg px-6 py-5">
                    <span class="tracking-[0.2em] text-sm text-gray-300">独自資料</span>
                    <span class="text-[10px] tracking-widest text-gray-400">coming soon...</span>
                </div>
            </div>

            <div class="mt-10 text-center">
                <button onclick="showPage('home')" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-2.5 rounded-full tracking-[0.2em] text-xs transition-all duration-300 focus:outline-none">BACK TO HOME</button>
            </div>
        </section>

        <?php foreach([1, 2, 3, 4] as $i): ?>
            <?php
                $imagePath = getImagePath((string)$i);
                $isLocked = empty($unlockedLevels[$i]);
            ?>

            <section id="page-test<?php echo $i; ?>" class="page-content w-full max-w-2xl bg-black/30 backdrop-blur-xl p-8 md:p-12 rounded-lg border border-white/20 shadow-2xl text-left">
                <h2 class="text-2xl md:text-3xl font-light mb-8 border-b border-white/30 pb-4 tracking-wider text-white">Level.<?php echo $i; ?></h2>

                <?php if ($isLocked): ?>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <p class="text-gray-300 text-sm mb-6">このLevelを見るにはログインが必要です。</p>
                        <div class="text-center">
                            <a href="login.php" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-2.5 rounded-full tracking-[0.2em] text-xs transition-all duration-300 inline-block">LOGIN</a>
                        </div>
                    <?php else: ?>
                        <?php if ($levelPasswordError && $activePage === 'test' . $i): ?>
                            <p class="text-red-400 text-xs mb-4"><?php echo htmlspecialchars($levelPasswordError); ?></p>
                        <?php endif; ?>
                        <form method="POST" class="flex flex-col gap-4 max-w-xs mx-auto">
                            <input type="hidden" name="action" value="verify_level_password">
                            <input type="hidden" name="level" value="<?php echo $i; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <div class="relative">
                                <input type="password" name="level_password" placeholder="Password" required class="bg-black/50 border border-white/20 rounded pl-3 pr-12 py-2 text-sm text-white w-full focus:outline-none focus:border-white/50">
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
                                <input type="checkbox" id="rememberLevelPassword<?php echo $i; ?>" class="sr-only">
                                <span class="w-5 h-5 shrink-0 rounded border border-white/30 bg-white/5 flex items-center justify-center transition-colors" id="rememberLevelPasswordBox<?php echo $i; ?>">
                                    <svg id="rememberLevelPasswordCheckIcon<?php echo $i; ?>" class="w-3.5 h-3.5 text-black hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.415l-7.5 7.5a1 1 0 01-1.415 0l-3.5-3.5a1 1 0 111.415-1.414L8.5 12.086l6.79-6.795a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                                パスワードを保存する
                            </label>
                            <button type="submit" class="bg-white/10 hover:bg-white/20 text-white text-sm px-4 py-2 rounded transition-colors tracking-widest font-light">UNLOCK</button>
                        </form>
                        <div class="mt-10 text-center">
                            <button onclick="showPage('home')" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-2.5 rounded-full tracking-[0.2em] text-xs transition-all duration-300 focus:outline-none">BACK TO HOME</button>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="flex items-center mb-8 inline-block">
                        <span class="mr-6 font-light text-gray-200 tracking-wider">技術発生</span>
                        <div class="relative inline-block w-12 h-6 align-middle select-none">
                            <input type="checkbox" name="toggleTest<?php echo $i; ?>" id="toggleTest<?php echo $i; ?>" autocomplete="off" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-transparent border-2 appearance-none cursor-pointer outline-none" onchange="toggleImage('test<?php echo $i; ?>-media', 'status-test<?php echo $i; ?>', this.checked)"/>
                            <label for="toggleTest<?php echo $i; ?>" class="toggle-label block overflow-hidden h-6 rounded-full cursor-pointer border border-white/30"></label>
                            <div class="toggle-dot absolute block w-5 h-5 rounded-full shadow inset-y-0 left-0 mt-0.5 ml-0.5 pointer-events-none"></div>
                        </div>
                        <span id="status-test<?php echo $i; ?>" class="ml-6 font-semibold tracking-widest text-gray-400 text-sm">OFF</span>
                        <span id="on-timer<?php echo $i; ?>" class="hidden ml-3 text-xs text-gray-400 tracking-wider tabular-nums">(00:00:00)</span>
                    </div>

                    <div id="test<?php echo $i; ?>-media" class="hidden transition-all duration-500 opacity-0">
                        <div class="overflow-hidden rounded shadow-2xl bg-black flex justify-center items-center py-8">
                            <?php if (isVideoFile($imagePath)): ?>
                                <video src="<?php echo htmlspecialchars($imagePath); ?>" controls class="w-full max-w-sm h-auto opacity-90 hover:opacity-100 transition-opacity duration-500"></video>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Test<?php echo $i; ?> メディア" class="w-full max-w-sm h-auto object-contain opacity-90 hover:opacity-100 transition-opacity duration-500">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-10 text-center">
                        <button onclick="showPage('home')" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-8 py-2.5 rounded-full tracking-[0.2em] text-xs transition-all duration-300 focus:outline-none">BACK TO HOME</button>
                    </div>

                    <div class="mt-10 pt-6 border-t border-white/10 text-left max-w-xs mx-auto">
                        <p class="text-xs text-gray-500 tracking-widest mb-3">HISTORY</p>
                        <div id="on-history-list<?php echo $i; ?>" class="flex flex-col gap-1.5 text-xs text-gray-400"></div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

    </main>

    <div class="fixed bottom-4 right-4 z-50 flex flex-col items-end gap-2">
        <a href="admin.php" class="text-[10px] text-white/50 hover:text-white transition-colors tracking-widest uppercase bg-black/30 px-2 py-1 rounded-md backdrop-blur-sm border border-white/10">Admin</a>
        <?php if (isMobile()): ?>
        <a href="index.php?force_pc=0" class="text-[10px] text-white/50 hover:text-white transition-colors tracking-widest uppercase bg-black/30 px-2 py-1 rounded-md backdrop-blur-sm border border-white/10">スマホ版に戻る</a>
        <?php endif; ?>
    </div>

    <script>
        // Levelパスワードの保存・自動入力（Level番号ごとにlocalStorageへ保存。サーバーには送信しません）
        [1, 2, 3, 4].forEach(function(level) {
            const form = document.querySelector('#page-test' + level + ' form');
            const passwordInput = form ? form.querySelector('input[name="level_password"]') : null;
            const rememberCheckbox = document.getElementById('rememberLevelPassword' + level);
            const rememberBox = document.getElementById('rememberLevelPasswordBox' + level);
            const rememberCheckIcon = document.getElementById('rememberLevelPasswordCheckIcon' + level);
            if (!form || !passwordInput || !rememberCheckbox) return;

            const storageKey = 'lw_level_password_' + level;

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

            form.addEventListener('submit', function() {
                if (rememberCheckbox.checked) {
                    localStorage.setItem(storageKey, passwordInput.value);
                } else {
                    localStorage.removeItem(storageKey);
                }
            });
        });

        // Levelパスワードの表示／非表示切り替え（各Levelのフォームで共通利用）
        function toggleLevelPassword(button) {
            const input = button.parentElement.querySelector('input');
            const willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            button.querySelector('.eye-open').classList.toggle('hidden', willShow);
            button.querySelector('.eye-closed').classList.toggle('hidden', !willShow);
            button.setAttribute('aria-label', willShow ? 'パスワードを隠す' : 'パスワードを表示');
        }

        // --- スリープ・切断対策（ショート・ポーリング） ---
function keepAlive() {
    fetch('keep_alive.php')
        .then(response => {
            if (!response.ok) {
                console.error('Keep-alive error');
                return;
            }
            return response.json();
        })
        .then(data => {
            if (data && data.loggedIn === false) {
                window.location.href = 'login.php';
            }
        })
        .catch(error => {
            console.error('通信維持エラー:', error);
        });
}
// 30秒（30000ミリ秒）ごとに裏側でサーバーをノックする
setInterval(keepAlive, 5000);
// ----------------------------------------------------
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

        // ページ切り替え関数
        function showPage(pageId) {
            const pages = document.querySelectorAll('.page-content');
            pages.forEach(page => page.classList.remove('active'));
            setTimeout(() => {
                const targetPage = document.getElementById('page-' + pageId);
                if (targetPage) {
                    targetPage.classList.add('active');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }, 50);
            // 現在表示中のタブを記憶しておく（バックグラウンド後の再読み込みで表示がズレないように）
            sessionStorage.setItem('lw_active_page', pageId);
        }

        function lwTogglePasswordList() {
            const list = document.getElementById('lw-password-list');
            const btn = document.getElementById('lw-toggle-passwords');
            const isHidden = list.classList.contains('hidden');
            list.classList.toggle('hidden');
            btn.textContent = isHidden ? 'HIDE' : 'PASSWORD';
        }
        function lwCopyLevelPassword(button, password) {
            navigator.clipboard.writeText(password).then(function() {
                const copyIcon = button.querySelector('.copy-icon');
                const checkIcon = button.querySelector('.check-icon');
                copyIcon.classList.add('hidden');
                checkIcon.classList.remove('hidden');
                setTimeout(function() {
                    checkIcon.classList.add('hidden');
                    copyIcon.classList.remove('hidden');
                }, 1500);
            });
        }

        // ON/OFF切り替え関数
        function toggleImage(elementId, statusId, isChecked) {
            const targetElement = document.getElementById(elementId);
            const statusElement = document.getElementById(statusId);
            const level = statusId.replace('status-test', '');
            if (isChecked) {
                targetElement.classList.remove('hidden');
                setTimeout(() => { targetElement.classList.add('opacity-100'); }, 10);
                statusElement.textContent = 'ON';
                statusElement.classList.replace('text-gray-400', 'text-white');
                startOnTimer(level);
            } else {
                targetElement.classList.remove('opacity-100');
                setTimeout(() => { targetElement.classList.add('hidden'); }, 300);
                statusElement.textContent = 'OFF';
                statusElement.classList.replace('text-white', 'text-gray-400');
                stopOnTimer(level);
            }
        }

        // 「技術発生」ONになってからの経過時間タイマー（Level番号ごとにlocalStorageへ保存。ページを開き直しても継続表示）
        (function() {
            const onTimerIntervals = {};

            function formatElapsed(ms) {
                const totalSeconds = Math.floor(ms / 1000);
                const h = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
                const m = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
                const s = String(totalSeconds % 60).padStart(2, '0');
                return h + ':' + m + ':' + s;
            }

            function getHistory(level) {
                try { return JSON.parse(localStorage.getItem('lw_level_history_' + level) || '[]'); }
                catch (e) { return []; }
            }

            function addHistoryEntry(level, startedAt, endedAt, cutoff) {
                const key = 'lw_level_history_' + level;
                const history = getHistory(level);
                history.unshift({ startedAt: startedAt, durationMs: endedAt - startedAt, cutoff: !!cutoff });
                localStorage.setItem(key, JSON.stringify(history.slice(0, 15)));
                renderHistory(level);
            }

            function renderHistory(level) {
                const listEl = document.getElementById('on-history-list' + level);
                if (!listEl) return;
                const history = getHistory(level);
                if (history.length === 0) {
                    listEl.innerHTML = '<span class="text-gray-600">記録はまだありません</span>';
                    return;
                }
                listEl.innerHTML = history.map(function(entry) {
                    const d = new Date(entry.startedAt);
                    const dateStr = (d.getMonth() + 1) + '/' + d.getDate() + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
                    const durStr = formatElapsed(entry.durationMs);
                    const badge = entry.cutoff
                        ? '<span class="text-red-400">強制終了</span>'
                        : '<span class="text-gray-500">手動OFF</span>';
                    return '<div class="flex items-center justify-between gap-2 bg-black/20 px-2.5 py-1.5 rounded">' +
                        '<span>' + dateStr + '</span><span class="font-mono">' + durStr + '</span>' + badge + '</div>';
                }).join('');
            }

            // 強制終了を検知した際に、画面上部にポップアップ通知を表示する
            function showCutoffToast(level, durationMs) {
                var existing = document.getElementById('lw-cutoff-toast');
                if (existing) existing.remove();
                var toast = document.createElement('div');
                toast.id = 'lw-cutoff-toast';
                toast.textContent = '前回 Level.' + level + ' の「技術発生」が強制終了しました（経過時間: ' + formatElapsed(durationMs) + '）';
                toast.style.cssText = 'position:fixed;top:12px;left:50%;transform:translateX(-50%);' +
                    'background:rgba(120,20,20,0.9);color:#fff;font-size:12px;letter-spacing:0.03em;' +
                    'padding:10px 18px;border-radius:999px;border:1px solid rgba(255,120,120,0.4);' +
                    'z-index:99999;backdrop-filter:blur(6px);transition:opacity 0.5s;white-space:nowrap;';
                document.body.appendChild(toast);
                setTimeout(function() {
                    toast.style.opacity = '0';
                    setTimeout(function() { toast.remove(); }, 500);
                }, 4000);
            }

            function tick(level) {
                const storageKey = 'lw_level_on_since_' + level;
                const onTimerEl = document.getElementById('on-timer' + level);
                const since = parseInt(sessionStorage.getItem(storageKey) || '0', 10);
                if (!since || !onTimerEl) return;
                onTimerEl.textContent = '(' + formatElapsed(Date.now() - since) + ')';
                localStorage.setItem('lw_level_live_' + level, JSON.stringify({ start: since, lastSeen: Date.now() }));
            }

            window.startOnTimer = function(level) {
                const storageKey = 'lw_level_on_since_' + level;
                const onTimerEl = document.getElementById('on-timer' + level);
                if (!onTimerEl) return;
                if (!sessionStorage.getItem(storageKey)) {
                    sessionStorage.setItem(storageKey, String(Date.now()));
                }
                onTimerEl.classList.remove('hidden');
                tick(level);
                if (onTimerIntervals[level]) clearInterval(onTimerIntervals[level]);
                onTimerIntervals[level] = setInterval(() => tick(level), 1000);
            };

            window.stopOnTimer = function(level) {
                const storageKey = 'lw_level_on_since_' + level;
                const onTimerEl = document.getElementById('on-timer' + level);
                const since = parseInt(sessionStorage.getItem(storageKey) || '0', 10);
                if (since) {
                    addHistoryEntry(level, since, Date.now(), false);
                }
                sessionStorage.removeItem(storageKey);
                localStorage.removeItem('lw_level_live_' + level);
                if (onTimerEl) onTimerEl.classList.add('hidden');
                if (onTimerIntervals[level]) clearInterval(onTimerIntervals[level]);
                onTimerIntervals[level] = null;
            };

            // ページを開き直した時、ON状態が保存されていればトグルとタイマーを復元する（同一タブ内でのみ）
            // 保存が無ければ、ブラウザ側の自動復元によるズレを防ぐため明示的にOFFへ揃える
            document.addEventListener('DOMContentLoaded', function() {
                [1, 2, 3, 4].forEach(function(level) {
                    const storageKey = 'lw_level_on_since_' + level;
                    const checkbox = document.getElementById('toggleTest' + level);

                    // 前回、手動OFFを経ずにセッションが失われていた場合は「強制終了」として履歴に残す
                    if (!sessionStorage.getItem(storageKey)) {
                        const liveRaw = localStorage.getItem('lw_level_live_' + level);
                        if (liveRaw) {
                            try {
                                const live = JSON.parse(liveRaw);
                                if (live && live.start && live.lastSeen) {
                                    addHistoryEntry(level, live.start, live.lastSeen, true);
                                    showCutoffToast(level, live.lastSeen - live.start);
                                }
                            } catch (e) {}
                            localStorage.removeItem('lw_level_live_' + level);
                        }
                    }
                    renderHistory(level);

                    if (!checkbox) return;
                    if (sessionStorage.getItem(storageKey)) {
                        checkbox.checked = true;
                        toggleImage('test' + level + '-media', 'status-test' + level, true);
                    } else {
                        checkbox.checked = false;
                        toggleImage('test' + level + '-media', 'status-test' + level, false);
                    }
                });
            });
        })();

        // ページ読み込み時の処理 (PHPからの変数を受け取る)
        // Level解除等のPOST直後はサーバー側の指定を優先し、それ以外（＝再読み込み等）は
        // 直前に見ていたタブをsessionStorageから復元する
        window.addEventListener('DOMContentLoaded', () => {
            const serverPage = '<?php echo $activePage; ?>';
            const rememberedPage = sessionStorage.getItem('lw_active_page');
            const initialPage = (serverPage === 'home' && rememberedPage) ? rememberedPage : serverPage;
            showPage(initialPage);
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