<?php
/**
 * 英語版（/en/）の言語レイヤー。db.php の先頭から読み込まれる。
 *
 * 仕組み:
 *  - URLが「<サイトのディレクトリ>/en/…」のときだけ英語モード（LW_LANG = 'en'）。
 *    .htaccess が /en/xxx.php を xxx.php へ内部転送するので、ページ本体は共通。
 *  - ページのHTML出力（およびJSON応答の文字列）を、下の辞書で日本語→英語に置換して返す。
 *  - HTMLには「JP | EN」切替リンクを差し込む。自動切替はしない。
 *
 * 辞書のルール（崩れ防止）:
 *  - 英訳文に ' " \ < > & を入れない（JS文字列・属性値の中でも壊れないように）。
 *  - $LW_EN_SUB   : 部分一致で置換する（文章など長いもの）。
 *  - $LW_EN_EXACT : 前後が < > ' " ` で区切られた「単語だけ」のときに置換する
 *                   （ユーザー入力のデバイス名などの一部を誤って書き換えないため）。
 *  - 管理画面(admin.php)・メール本文は対象外（日本語のまま）。
 */

if (!defined('LW_LANG')) {
    $lwPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $lwBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    define('LW_LANG', (strpos($lwPath, $lwBase . '/en/') === 0) ? 'en' : 'ja');
}

/** 現在のページの「もう一方の言語」のURL（パス＋クエリ）を返す */
function lwAltLangUrl() {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = (string)parse_url($uri, PHP_URL_PATH);
    $query = parse_url($uri, PHP_URL_QUERY);
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if (LW_LANG === 'en') {
        $alt = $base . '/' . substr($path, strlen($base . '/en/'));
    } else {
        $rest = ($path === $base . '/' || $path === $base) ? '' : substr($path, strlen($base . '/'));
        $alt = $base . '/en/' . $rest;
    }
    return $alt . ($query !== null && $query !== '' ? '?' . $query : '');
}

$LW_EN_SUB = [
    // --- 共通 ---
    'アプリ内ブラウザで表示しています。正しく動作しない場合は右上「…」等のメニューから「ブラウザで開く」を選択してください。' => 'You are viewing this in an in-app browser. If it does not work correctly, choose Open in browser from the menu (...) at the top right.',
    'URLをコピー' => 'Copy URL',
    'コピーしました' => 'Copied',
    '前回の続きのページに自動で戻しました' => 'Returned to the page you were viewing last time',
    '通信維持エラー:' => 'Connection keep-alive error:',
    'CSRF token validation failed. 不正なリクエストです。' => 'CSRF token validation failed. Invalid request.',
    'ログインが必要です。' => 'Login is required.',
    'パスワードが間違っています。' => 'Incorrect password.',
    '入力してください。' => 'Please fill in this field.',
    'エラー:' => 'Error:',
    '通信エラーが発生しました。' => 'A communication error occurred.',
    '通信エラーが発生しました' => 'A communication error occurred',
    '通信に失敗しました' => 'Communication failed',
    '通信エラー' => 'Communication error',
    '通信失敗' => 'Communication failed',
    // --- ログイン / 登録 / パスワード再発行 / メール認証 ---
    'ログイン - LUXE WAVE' => 'Login - LUXE WAVE',
    'ログイン - Mobile' => 'Login - Mobile',
    '会員登録 - LUXE WAVE' => 'Sign up - LUXE WAVE',
    'パスワードのリセット - LUXE WAVE' => 'Reset password - LUXE WAVE',
    'パスワード再発行 - Mobile' => 'Reset password - Mobile',
    'メール認証 - LUXE WAVE' => 'Email verification - LUXE WAVE',
    'ページを選択 - LUXE WAVE' => 'Select page - LUXE WAVE',
    '機器の追加 - LUXE WAVE' => 'Add device - LUXE WAVE',
    'Token・Secretの調べ方 - LUXE WAVE' => 'How to find Token and Secret - LUXE WAVE',
    'アカウントは正常に削除されました。' => 'Your account has been deleted.',
    'ログイン試行回数が多いため、しばらく時間をおいてから再度お試しください。' => 'Too many login attempts. Please wait a while and try again.',
    'メールアドレスの確認が完了していません。ご登録時に届いたメール内のリンクをクリックしてください。' => 'Your email address has not been verified yet. Please click the link in the email sent when you signed up.',
    'メールアドレスまたはパスワードが間違っています。' => 'Incorrect email address or password.',
    '認証に成功しました' => 'Authentication successful',
    'メールアドレスを保存する' => 'Remember email address',
    'パスワードを保存する' => 'Remember password',
    'アカウント新規作成' => 'Create account',
    'パスワードを忘れた方はこちら' => 'Forgot your password?',
    '招待コードが無効か、既に使用されています。' => 'The invitation code is invalid or has already been used.',
    'データベースエラー:' => 'Database error:',
    'メールアドレス・パスワード・招待コードをすべて入力してください。' => 'Please enter your email address, password and invitation code.',
    '確認メールを送信しました' => 'Confirmation email sent',
    'ご登録のメールアドレスに確認メールをお送りしました。' => 'We have sent a confirmation email to your address.',
    'メール内のリンクをクリックして、登録を完了してください。' => 'Click the link in the email to complete your registration.',
    '（リンクの有効期限は24時間です）' => '(The link is valid for 24 hours)',
    'ログイン画面へ' => 'Go to login',
    'すでにアカウントをお持ちの方はこちら' => 'Already have an account?',
    'このメールアドレスは既に登録されています。' => 'This email address is already registered.',
    'ご入力のメールアドレスが登録されている場合、仮パスワードをお送りしました。メールをご確認ください。' => 'If the email address you entered is registered, we have sent a temporary password. Please check your email.',
    'メールアドレスを入力してください。' => 'Please enter your email address.',
    '新しい仮パスワード' => 'New temporary password',
    '※ このパスワードをコピーし、ログイン後にダッシュボードから任意のパスワードに再設定してください。' => '* Copy this password, log in, then set a new password of your choice from the dashboard.',
    '※ このパスワードをコピーしてログインし、' => '* Copy this password and log in, then',
    'ダッシュボードから任意のパスワードに再設定してください。' => 'set a new password of your choice from the dashboard.',
    'LOGIN 画面へ' => 'Go to LOGIN',
    '登録しているメールアドレスを入力してください。' => 'Please enter your registered email address.',
    '新しいパスワードを発行します。' => 'A new password will be issued.',
    'ログイン画面に戻る' => 'Back to login',
    '無効な認証リンクです。リンクの有効期限が切れているか、既に認証済みの可能性があります。' => 'Invalid verification link. The link may have expired or may already have been used.',
    '無効な認証リンクです。' => 'Invalid verification link.',
    'このメールアドレスは既に認証済みです。' => 'This email address has already been verified.',
    '認証リンクの有効期限が切れています。お手数ですが再度登録手続きを行うか、サポートまでご連絡ください。' => 'The verification link has expired. Please register again or contact support.',
    'メールアドレスの認証が完了しました。' => 'Your email address has been verified.',
    'メインページへ' => 'Go to main page',
    // --- Level ページ ---
    'このLevelを見るにはログインが必要です。' => 'Login is required to view this Level.',
    'このLevelはダッシュボードでCODEを入力すると表示されます。' => 'This Level appears when you enter its CODE on the dashboard.',
    'ダッシュボードへ' => 'Go to dashboard',
    'パスワードを表示' => 'Show password',
    'パスワードを隠す' => 'Hide password',
    'パスワードをコピー' => 'Copy password',
    '記録はまだありません' => 'No records yet',
    '自動終了(24h)' => 'Auto-ended (24h)',
    'ログアウトで終了' => 'Ended by logout',
    // --- ホーム / ナビ ---
    'Apple Watch版ホームを表示する' => 'Show Apple Watch home',
    'Apple Watch版を表示する' => 'Show Apple Watch version',
    'PC版サイトを表示する' => 'Show PC site',
    'スマホ版に戻る' => 'Back to mobile version',
    'MENUに戻る' => 'Back to MENU',
    'TOPに戻る' => 'Back to TOP',
    // --- スマートプラグ ---
    '独自技術を起動する' => 'Activate Proprietary Technology',
    'デバイスが登録されていません。' => 'No devices are registered.',
    '新しく機器を登録する' => 'Register a new device',
    '新しい機器を追加する' => 'Add a new device',
    'Recovered (自動復旧)' => 'Recovered (auto)',
    '通信中...' => 'Communicating...',
    '起動完了' => 'Activated',
    // --- 機器の追加 ---
    'デバイスを追加しました！' => 'Device added!',
    '追加に失敗しました。' => 'Failed to add the device.',
    '自動的にプラグ画面に戻ります...' => 'Returning to the plug screen automatically...',
    'Token と Secret の調べ方がわからない場合はこちら' => 'Not sure how to find your Token and Secret? See here',
    '調べ方を見る' => 'How to find them',
    'Token と Secret を入力して、連携可能なデバイスを検索します。' => 'Enter your Token and Secret to search for devices that can be linked.',
    '前回登録したアカウントからデバイスを検索' => 'Search devices from the previously registered account',
    '新しいアカウントで検索' => 'Search with a new account',
    'デバイス一覧を検索する' => 'Search device list',
    '登録するデバイスを選択' => 'Select a device to register',
    '選択してください' => 'Please select',
    'デバイス名 (自由に変更可能)' => 'Device name (editable)',
    'Device ID (自動入力)' => 'Device ID (auto-filled)',
    'アイコン / カラー' => 'Icon / Color',
    '自動取得できない場合は手動で入力する' => 'Enter manually if automatic retrieval fails',
    '手動でデバイス情報を入力して登録します。' => 'Enter the device information manually to register it.',
    'デバイス名 (例: 自宅のプラグ)' => 'Device name (e.g. Home plug)',
    'Device ID (例: F85B1B276B1A)' => 'Device ID (e.g. F85B1B276B1A)',
    'ADD DEVICE (手動)' => 'ADD DEVICE (manual)',
    'TokenとSecretを入力してください。' => 'Please enter your Token and Secret.',
    '登録されているプラグが見つかりませんでした。' => 'No registered plugs were found.',
    '登録されているプラグ（Plug Mini等）が見つかりませんでした。' => 'No registered plugs (Plug Mini, etc.) were found.',
    '自動検索に戻る' => 'Back to automatic search',
    'デバイス名' => 'Device name',
    '検索中...' => 'Searching...',
    // --- ダッシュボード ---
    'が解除されました！' => 'has been unlocked!',
    'のCODEを入力して解除しました。' => 'unlocked with its CODE.',
    'CODEが正しくありません。' => 'The CODE is incorrect.',
    '招待コードを再発行しました。新しいコード: 「 {$newInviteCode} 」（以前の未使用コードは無効になりました。履歴には残ります）' => 'Invitation code reissued. New code: {$newInviteCode} (the previous unused code is now invalid; it remains in the history)',
    '新しい招待コードを発行しました: 「 {$newInviteCode} 」' => 'New invitation code issued: {$newInviteCode}',
    '招待コードを再発行: {$newInviteCode}' => 'Invitation code reissued: {$newInviteCode}',
    '招待コードの再発行に失敗しました。' => 'Failed to reissue the invitation code.',
    'デバイス名を更新しました。' => 'Device name updated.',
    'デバイス名の更新に失敗しました。' => 'Failed to update the device name.',
    'デバイス情報を更新しました。' => 'Device information updated.',
    'デバイス情報の更新に失敗しました。' => 'Failed to update the device information.',
    'すべての項目を入力してください。' => 'Please fill in all fields.',
    'デバイスを削除しました。' => 'Device deleted.',
    'メールアドレスが正常に変更されました。' => 'Your email address has been changed.',
    'メールアドレスの変更中にエラーが発生しました。' => 'An error occurred while changing your email address.',
    'パスワードが正しくありません。アカウントを削除できませんでした。' => 'Incorrect password. The account could not be deleted.',
    'パスワードが正しくありません。' => 'Incorrect password.',
    'すべてのフィールドを入力してください。' => 'Please fill in all fields.',
    '新しいパスワードが一致しません。' => 'The new passwords do not match.',
    'パスワードが正常に変更されました。' => 'Your password has been changed.',
    '現在のパスワードが正しくありません。' => 'The current password is incorrect.',
    'アカウントの削除中にエラーが発生しました。' => 'An error occurred while deleting your account.',
    '削除するにはパスワードの入力が必要です。' => 'Enter your password to delete.',
    '有効なコードがありません。再発行してください。' => 'There is no valid code. Please reissue one.',
    '招待コードを再発行しますか？' => 'Reissue the invitation code?',
    '現在の未使用コードは使えなくなります（履歴には残ります）。' => 'The current unused code will no longer work (it remains in the history).',
    '発行履歴（' => 'Issue history (',
    '件）' => ')',
    '発行 20' => 'Issued 20',
    '/ 使用 20' => '/ Used at 20',
    '/ 無効化 20' => '/ Invalidated 20',
    'メディア"' => 'Media"',
    '無効（再発行済み）' => 'Invalid (reissued)',
    '特別なCODEを入力すると、対応するLevelが出現します。' => 'Enter a special CODE and the matching Level will appear.',
    'まだ発行されていません。' => 'Not issued yet.',
    '（スマートプラグ契約者のみ）' => '(Smart plug subscribers only)',
    '本当にこのデバイスを削除しますか？' => 'Are you sure you want to delete this device?',
    'Icon (絵文字など)' => 'Icon (emoji, etc.)',
    '登録されているデバイスがありません。' => 'No devices are registered.',
    '現在登録されているメールアドレス:' => 'Current email address:',
    '新しいメールアドレス' => 'New email address',
    '現在のパスワード' => 'Current password',
    '新しいパスワードの確認' => 'Confirm new password',
    '新しいパスワード' => 'New password',
    '本当によろしいですか？このアカウントと関連するすべてのデータが完全に削除されます。この操作は元に戻せません。' => 'Are you sure? This account and all related data will be permanently deleted. This cannot be undone.',
    'この操作は元に戻せません。アカウントを削除すると、登録されているすべてのデバイス情報が完全に失われます。' => 'This cannot be undone. Deleting your account permanently removes all registered device information.',
    '確認のためパスワードを入力' => 'Enter your password to confirm',
    // --- Token / Secret の調べ方 ---
    'SwitchBotアプリから、連携に必要な「Token（トークン）」「Secret（シークレット）」「Device ID（デバイスID）」を調べる手順をご案内します。' => 'This guide shows how to find the Token, Secret and Device ID needed for linking in the SwitchBot app.',
    '1. Token と Secret の調べ方' => '1. How to find the Token and Secret',
    '※ これらの情報は、アカウントを外部システムと連携するための特別な暗号です。' => '* This information is a special key used to link your account with external systems.',
    'スマートフォンで「SwitchBot」アプリを開きます。' => 'Open the SwitchBot app on your smartphone.',
    '画面の右下にある「プロフィール」をタップします。' => 'Tap Profile at the bottom right of the screen.',
    'メニューの中から「設定」をタップします。' => 'Tap Settings in the menu.',
    '画面に「アプリバージョン」という項目があります。' => 'You will see an item called App Version.',
    'ここを トントントントン… と 10回連続で素早くタップ してください。' => 'Tap it quickly 10 times in a row.',
    '（これは隠しメニューを出すための秘密の操作です）' => '(This is a secret action that reveals a hidden menu)',
    'すると、「開発者向けオプション」という新しいメニューが現れるので、それをタップします。' => 'A new menu called Developer Options will appear. Tap it.',
    '画面に「トークン（Token）」と「クライアントシークレット（Secret）」という長いアルファベットと数字の列が表示されます。' => 'Long strings of letters and numbers labeled Token and Client Secret will be shown.',
    '※ クライアントシークレットは、目のマークをタップすると表示されます。' => '* Tap the eye icon to reveal the Client Secret.',
    'それぞれの横にある「コピーボタン（書類が重なったようなアイコン）」をタップしてコピーし、LUXE WAVEの画面に貼り付けてください。' => 'Tap the copy button (the icon that looks like overlapping sheets) next to each one, then paste them into the LUXE WAVE screen.',
    '2. Device ID（デバイスID）の調べ方' => '2. How to find the Device ID',
    'SwitchBotアプリのホーム画面で、登録したいプラグ（デバイス）をタップします。' => 'On the SwitchBot app home screen, tap the plug (device) you want to register.',
    '画面の右上にある「歯車マーク（設定）」をタップします。' => 'Tap the gear icon (Settings) at the top right of the screen.',
    'メニューから「デバイス情報」をタップします。' => 'Tap Device Info in the menu.',
    '「デバイスMACアドレス」という項目に書かれている英数字が Device ID です。' => 'The letters and numbers shown under Device MAC Address are the Device ID.',
    'この文字をLUXE WAVEの画面に入力してください。' => 'Enter these characters into the LUXE WAVE screen.',
    '※ 入力する際は「:（コロン）」を取り除いて、文字をつなげて入力してください。' => '* Remove the colons and enter the characters joined together.',
    'このタブを閉じる' => 'Close this tab',
    'ユーザーがログインしました。' => 'User logged in.',
    'ユーザーがパスワードを再発行しました。' => 'User reissued their password.',
];

$LW_EN_EXACT = [
    '戻る' => 'Back',
    'ホワイト' => 'White', 'レッド' => 'Red', 'ブルー' => 'Blue', 'グリーン' => 'Green',
    'イエロー' => 'Yellow', 'パープル' => 'Purple', 'ピンク' => 'Pink',
    'やり直す' => 'Start over',
    '独自資料' => 'Original Materials',
    '技術発生' => 'Technology Generation',
    '音量' => 'Volume',
    'メディア' => 'Media',
    '手動OFF' => 'Manual OFF',
    '再発行' => 'Reissue',
    '使用済み' => 'Used',
    '有効' => 'Valid',
    '発行' => 'Issued',
    '使用' => 'Used at',
    '無効化' => 'Invalidated',
    '送信' => 'Submit',
    '（未使用）' => '(unused)',
    '上へ' => 'Up',
    '下へ' => 'Down',
    'エラー' => 'Error',
    '例：' => 'Example: ',
    '入力例：' => 'Example: ',
];

/** 文字列1つを翻訳（英語モード用） */
function lwTranslateText($s) {
    global $LW_EN_SUB, $LW_EN_EXACT;
    if ($s === '' || !preg_match('/[぀-ヿ一-鿿]/u', $s)) return $s;
    $s = strtr($s, $LW_EN_SUB);
    if (preg_match('/[぀-ヿ一-鿿]/u', $s)) {
        $s = preg_replace_callback('/(?<=[>\'"`])(\s*)(' . lwExactPattern() . ')(\s*)(?=[<\'"`])/u', function ($m) use ($LW_EN_EXACT) {
            return $m[1] . $LW_EN_EXACT[$m[2]] . $m[3];
        }, $s);
    }
    return $s;
}
function lwExactPattern() {
    global $LW_EN_EXACT;
    $keys = array_keys($LW_EN_EXACT);
    usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
    return implode('|', array_map(function ($k) { return preg_quote($k, '/'); }, $keys));
}
function lwTranslateJsonValue($v) {
    if (is_string($v)) return lwTranslateText($v);
    if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = lwTranslateJsonValue($x); }
    elseif (is_object($v)) { foreach (get_object_vars($v) as $k => $x) $v->$k = lwTranslateJsonValue($x); }
    return $v;
}

/** 出力バッファのコールバック：英語モードで翻訳＋切替リンク挿入 */
function lwOutputFilter($buf) {
    $ctype = '';
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') === 0) $ctype = strtolower($h);
    }
    if (strpos($ctype, 'json') !== false) {
        if (LW_LANG !== 'en') return $buf;   // 日本語版のJSONは一切加工しない
        $data = json_decode($buf);
        if (json_last_error() !== JSON_ERROR_NONE) return $buf;
        return json_encode(lwTranslateJsonValue($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($ctype !== '' && strpos($ctype, 'html') === false) return $buf;   // 画像・音声など
    if (stripos($buf, '<html') === false && stripos($buf, '<body') === false) return $buf;  // 断片は触らない
    if (LW_LANG === 'en') {
        $buf = lwTranslateText($buf);
        $buf = preg_replace('/<html([^>]*)\blang="ja"/i', '<html$1lang="en"', $buf, 1);
    }
    return lwInjectSwitcher($buf);
}

/** 「JP | EN」切替リンクを </body> の直前に差し込む */
function lwInjectSwitcher($buf) {
    $alt = htmlspecialchars(lwAltLangUrl(), ENT_QUOTES, 'UTF-8');
    $en = LW_LANG === 'en';
    $jpHtml = $en ? '<a href="' . $alt . '" style="color:#9ca3af;text-decoration:none">JP</a>' : '<span style="color:#fff">JP</span>';
    $enHtml = $en ? '<span style="color:#fff">EN</span>' : '<a href="' . $alt . '" style="color:#9ca3af;text-decoration:none">EN</a>';
    $box = '<div id="lw-lang-switch" style="position:fixed;right:8px;bottom:8px;z-index:2147483000;'
         . 'font:11px/1 Montserrat,sans-serif;letter-spacing:.12em;background:rgba(0,0,0,.55);'
         . 'border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:6px 10px">'
         . $jpHtml . ' <span style="color:#6b7280">|</span> ' . $enHtml . '</div>';
    $pos = strripos($buf, '</body>');
    return $pos === false ? $buf . $box : substr($buf, 0, $pos) . $box . substr($buf, $pos);
}

// admin.php・CLI は対象外。それ以外は出力フィルタを有効化（英語モードの翻訳＋切替リンク）
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'admin.php' && !defined('LW_NO_I18N')) {
    ob_start('lwOutputFilter');
}
