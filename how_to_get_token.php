<?php
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token・Secretの調べ方 - LUXE WAVE</title>
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
    </style>
</head>
<body class="bg-gradient-to-br from-black via-blue-900 to-black min-h-screen text-white relative z-0 overflow-x-hidden p-6 md:p-12">

    <canvas id="waveCanvas" class="fixed top-0 left-0 w-full h-full z-[-1] pointer-events-none"></canvas>

    <div class="max-w-3xl mx-auto bg-black/40 backdrop-blur-md p-8 md:p-12 rounded-2xl border border-white/20 shadow-2xl">
        <div class="text-center mb-12">
            <h1 class="brand-font text-2xl md:text-4xl tracking-[0.1em] font-light mb-4">How to Get Token & Secret</h1>
            <p class="text-gray-300 text-sm leading-relaxed">
                SwitchBotアプリから、連携に必要な「Token（トークン）」「Secret（シークレット）」「Device ID（デバイスID）」を調べる手順をご案内します。
            </p>
        </div>

        <!-- Token と Secret の調べ方 -->
        <div class="mb-16">
            <h2 class="text-xl font-medium mb-6 text-blue-300 border-b border-white/20 pb-3">1. Token と Secret の調べ方</h2>
            <p class="text-sm text-gray-400 mb-6">※ これらの情報は、アカウントを外部システムと連携するための特別な暗号です。</p>
            
            <ol class="space-y-6 text-sm md:text-base text-gray-200">
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">1</div>
                    <div>スマートフォンで「SwitchBot」アプリを開きます。</div>
                </li>
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">2</div>
                    <div>画面の右下にある「プロフィール」をタップします。</div>
                </li>
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">3</div>
                    <div>メニューの中から「設定」をタップします。</div>
                </li>
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">4</div>
                    <div>
                        <p class="mb-2">画面に「アプリバージョン」という項目があります。</p>
                        <p class="text-yellow-400 font-medium">ここを トントントントン… と 10回連続で素早くタップ してください。</p>
                        <p class="text-xs text-gray-400 mt-1">（これは隠しメニューを出すための秘密の操作です）</p>
                    </div>
                </li>
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">5</div>
                    <div>すると、「開発者向けオプション」という新しいメニューが現れるので、それをタップします。</div>
                </li>
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">6</div>
                    <div>
                        <p class="mb-2">画面に「トークン（Token）」と「クライアントシークレット（Secret）」という長いアルファベットと数字の列が表示されます。</p>
                        <p class="text-xs text-gray-400">※ クライアントシークレットは、目のマークをタップすると表示されます。</p>
                    </div>
                </li>
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">7</div>
                    <div>それぞれの横にある「コピーボタン（書類が重なったようなアイコン）」をタップしてコピーし、LUXE WAVEの画面に貼り付けてください。</div>
                </li>
            </ol>
        </div>

        <!-- Device ID の調べ方 -->
        <div class="mb-10">
            <h2 class="text-xl font-medium mb-6 text-blue-300 border-b border-white/20 pb-3">2. Device ID（デバイスID）の調べ方</h2>
            
            <ol class="space-y-6 text-sm md:text-base text-gray-200">
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">1</div>
                    <div>SwitchBotアプリのホーム画面で、登録したいプラグ（デバイス）をタップします。</div>
                </li>
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">2</div>
                    <div>画面の右上にある「歯車マーク（設定）」をタップします。</div>
                </li>
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">3</div>
                    <div>メニューから「デバイス情報」をタップします。</div>
                </li>
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">4</div>
                    <div>
                        <p class="mb-2">「デバイスMACアドレス」という項目に書かれている英数字が Device ID です。</p>
                        <p class="text-xs text-gray-400">例：<span class="text-white font-mono bg-white/10 px-1 rounded">F8:5B:1B:27:6B:1A</span></p>
                    </div>
                </li>
                <li class="flex items-start gap-4 bg-white/5 p-4 rounded-lg border border-white/5">
                    <div class="w-8 h-8 rounded-full bg-blue-900/50 flex items-center justify-center shrink-0 border border-blue-400/30">5</div>
                    <div>
                        <p class="mb-2">この文字をLUXE WAVEの画面に入力してください。</p>
                        <p class="text-yellow-400 font-medium">※ 入力する際は「:（コロン）」を取り除いて、文字をつなげて入力してください。</p>
                        <p class="text-xs text-gray-400 mt-1">入力例：<span class="text-white font-mono bg-white/10 px-1 rounded">F85B1B276B1A</span></p>
                    </div>
                </li>
            </ol>
        </div>

        <div class="mt-16 text-center">
            <button onclick="window.close()" class="border border-white/30 text-gray-300 hover:text-white hover:bg-white/10 px-10 py-3 rounded-full tracking-[0.2em] text-xs transition-all duration-300 inline-block bg-black/50">
                このタブを閉じる
            </button>
        </div>
    </div>

</body>
</html>