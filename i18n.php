<?php
/**
 * 多言語（/en/ /fr/ …）の言語レイヤー。db.php の先頭から読み込まれる。
 *
 * 仕組み:
 *  - URLが「<サイトのディレクトリ>/<言語コード>/…」のときだけその言語モード（LW_LANG = 'en' など）。
 *    .htaccess が /<言語コード>/xxx.php を xxx.php へ内部転送するので、ページ本体は全言語で共通。
 *  - ページのHTML出力（およびJSON応答の文字列）を、言語ごとの辞書で日本語→各言語に置換して返す。
 *  - HTMLには「JP | EN | FR …」切替リンクを差し込む。自動切替はしない。
 *
 * 言語を増やす手順:
 *  1) 下の $LW_LANGS に「コード => 表示名」を足す  2) i18n_<コード>.php を作る（i18n_en.php をコピーして訳す）
 *  3) .htaccess の言語コード一覧（(en|fr|…)）に足す
 *
 * 辞書ファイルのルール（崩れ防止）:
 *  - 'sub'   : 部分一致で置換する（文章など長いもの）。
 *  - 'exact' : 前後が < > ' " ` で区切られた「単語だけ」のときに置換する
 *              （ユーザー入力のデバイス名などの一部を誤って書き換えないため）。
 *  - 訳文に ASCIIの ' " \ < > & を入れない（JS文字列・属性値の中でも壊れないように。
 *    アポストロフィは ’ を使う）。
 *  - 管理画面(admin.php)・メール本文は対象外（日本語のまま）。
 */

// 対応言語（日本語は既定。キーはURLの言語コード）
$LW_LANGS = ['en' => 'EN', 'fr' => 'FR'];

if (!defined('LW_LANG')) {
    $lwPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $lwBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $lwDetected = 'ja';
    if (preg_match('#^' . preg_quote($lwBase, '#') . '/([a-z]{2})(?:/|$)#', $lwPath, $lwM) && isset($LW_LANGS[$lwM[1]])) {
        $lwDetected = $lwM[1];
    }
    define('LW_LANG', $lwDetected);
}

/** 現在の言語の辞書（['sub'=>…, 'exact'=>…]）。日本語・辞書なしは空 */
function lwDict() {
    static $d = null;
    if ($d === null) {
        $d = ['sub' => [], 'exact' => []];
        if (LW_LANG !== 'ja' && preg_match('/^[a-z]{2}$/', LW_LANG) && is_file(__DIR__ . '/i18n_' . LW_LANG . '.php')) {
            $loaded = require __DIR__ . '/i18n_' . LW_LANG . '.php';
            if (is_array($loaded)) $d = array_merge($d, $loaded);
        }
    }
    return $d;
}

/** 現在のページを $target 言語（'ja' / 'en' / 'fr'…）で開くURL（パス＋クエリ）を返す */
function lwLangUrl($target) {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = (string)parse_url($uri, PHP_URL_PATH);
    $query = parse_url($uri, PHP_URL_QUERY);
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if (LW_LANG !== 'ja') {
        $rest = substr($path, strlen($base . '/' . LW_LANG . '/'));
    } else {
        $rest = ($path === $base . '/' || $path === $base) ? '' : substr($path, strlen($base . '/'));
    }
    $url = $base . '/' . ($target === 'ja' ? '' : $target . '/') . $rest;
    return $url . ($query !== null && $query !== '' ? '?' . $query : '');
}

/** 文字列1つを翻訳（日本語以外の言語モード用） */
function lwTranslateText($s) {
    if ($s === '' || !preg_match('/[぀-ヿ一-鿿]/u', $s)) return $s;
    $d = lwDict();
    if ($d['sub']) $s = strtr($s, $d['sub']);
    if ($d['exact'] && preg_match('/[぀-ヿ一-鿿]/u', $s)) {
        $s = preg_replace_callback('/(?<=[>\'"`])(\s*)(' . lwExactPattern($d['exact']) . ')(\s*)(?=[<\'"`])/u', function ($m) use ($d) {
            return $m[1] . $d['exact'][$m[2]] . $m[3];
        }, $s);
    }
    return $s;
}
function lwExactPattern($exact) {
    static $cache = null;
    if ($cache === null) {
        $keys = array_keys($exact);
        usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
        $cache = implode('|', array_map(function ($k) { return preg_quote($k, '/'); }, $keys));
    }
    return $cache;
}
function lwTranslateJsonValue($v) {
    if (is_string($v)) return lwTranslateText($v);
    if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = lwTranslateJsonValue($x); }
    elseif (is_object($v)) { foreach (get_object_vars($v) as $k => $x) $v->$k = lwTranslateJsonValue($x); }
    return $v;
}

/** 出力バッファのコールバック：日本語以外のモードで翻訳＋切替リンク挿入 */
function lwOutputFilter($buf) {
    $ctype = '';
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') === 0) $ctype = strtolower($h);
    }
    if (strpos($ctype, 'json') !== false) {
        if (LW_LANG === 'ja') return $buf;   // 日本語版のJSONは一切加工しない
        $data = json_decode($buf);
        if (json_last_error() !== JSON_ERROR_NONE) return $buf;
        return json_encode(lwTranslateJsonValue($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($ctype !== '' && strpos($ctype, 'html') === false) return $buf;   // 画像・音声など
    if (stripos($buf, '<html') === false && stripos($buf, '<body') === false) return $buf;  // 断片は触らない
    if (LW_LANG !== 'ja') {
        $buf = lwTranslateText($buf);
        $buf = preg_replace('/<html([^>]*)\blang="ja"/i', '<html$1lang="' . LW_LANG . '"', $buf, 1);
    }
    return lwInjectSwitcher($buf);
}

/** 「JP | EN | FR …」切替リンクを </body> の直前に差し込む */
function lwInjectSwitcher($buf) {
    global $LW_LANGS;
    $items = ['ja' => 'JP'] + $LW_LANGS;
    $parts = [];
    foreach ($items as $code => $label) {
        if ($code === LW_LANG) {
            $parts[] = '<span style="color:#fff">' . $label . '</span>';
        } else {
            $parts[] = '<a href="' . htmlspecialchars(lwLangUrl($code), ENT_QUOTES, 'UTF-8') . '" style="color:#9ca3af;text-decoration:none">' . $label . '</a>';
        }
    }
    $box = '<div id="lw-lang-switch" style="position:fixed;left:8px;bottom:8px;z-index:2147483000;'
         . 'font:11px/1 Montserrat,sans-serif;letter-spacing:.12em;background:rgba(0,0,0,.55);'
         . 'border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:6px 10px">'
         . implode(' <span style="color:#6b7280">|</span> ', $parts) . '</div>';
    $pos = strripos($buf, '</body>');
    return $pos === false ? $buf . $box : substr($buf, 0, $pos) . $box . substr($buf, $pos);
}

// admin.php・CLI は対象外。それ以外は出力フィルタを有効化（翻訳＋切替リンク）
if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'admin.php' && !defined('LW_NO_I18N')) {
    ob_start('lwOutputFilter');
}
