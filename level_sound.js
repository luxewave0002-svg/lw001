// Level.1 テスト用：技術発生がONの間だけ level_sound.m4a をループ再生する。
// 音は Web Audio のバッファループ（継ぎ目が出にくい）で鳴らし、使えない場合は <audio loop> にフォールバックする。
// OFFの間は従来どおり無音ループ(lw-bg-keepalive)に戻す。他のLevelでは読み込まれない。
(function () {
    var silent = document.getElementById('lw-bg-keepalive');
    var sound = document.getElementById('lw-level-sound');
    if (!sound) return;
    var video = document.querySelector('[data-lw-level-video]');
    var volBtn = document.querySelector('[data-lw-volume]');
    var on = false;
    var armed = false;

    // Web Audio 用
    var ctx = null, gain = null, buf = null, src = null;
    var loading = false, failed = false, waActive = false;

    function play(a) {
        if (!a) return;
        try { var r = a.play(); if (r && r.catch) r.catch(function () {}); } catch (e) {}
    }

    function getCtx() {
        if (ctx || failed) return ctx;
        var C = window.AudioContext || window.webkitAudioContext;
        if (!C) { failed = true; return null; }
        try {
            ctx = new C();
            gain = ctx.createGain();
            gain.gain.value = sound.muted ? 0 : 1;
            gain.connect(ctx.destination);
        } catch (e) { ctx = null; failed = true; }
        return ctx;
    }

    function load() {
        if (buf || loading || failed) return;
        var c = getCtx();
        if (!c) return;
        loading = true;
        fetch(sound.getAttribute('src'))
            .then(function (r) { return r.arrayBuffer(); })
            .then(function (ab) {
                return new Promise(function (resolve, reject) {
                    var p = c.decodeAudioData(ab, resolve, reject);
                    if (p && p.catch) p.catch(reject);
                });
            })
            .then(function (b) { buf = b; loading = false; if (on) apply(); })
            .catch(function () { loading = false; failed = true; if (on) apply(); });
    }

    function startSrc() {
        if (src || !ctx || !buf) return;
        src = ctx.createBufferSource();
        src.buffer = buf;
        src.loop = true;
        src.connect(gain);
        src.start(0);
    }

    function stopSrc() {
        if (!src) return;
        try { src.stop(); } catch (e) {}
        try { src.disconnect(); } catch (e) {}
        src = null;
    }

    // 標準コントロールは出さず、音量(ミュート切替)ボタンだけ用意する
    function syncVolIcon() {
        if (!volBtn) return;
        var m = sound.muted;
        var onI = volBtn.querySelector('[data-lw-vol-on]'), offI = volBtn.querySelector('[data-lw-vol-off]');
        if (onI) onI.classList.toggle('hidden', m);
        if (offI) offI.classList.toggle('hidden', !m);
        if (gain) gain.gain.value = m ? 0 : 1;
    }
    if (volBtn) {
        volBtn.addEventListener('click', function () { sound.muted = !sound.muted; syncVolIcon(); });
        syncVolIcon();
    }

    function apply() {
        if (on) {
            var c = getCtx();
            if (c) {
                try { if (c.state !== 'running') c.resume(); } catch (e) {} // タップ中に呼ばれればiOSでも解除される
                load();
            }
            if (c && buf) {
                // 継ぎ目の出にくいバッファループで再生。<audio>は止め、無音ループは背景維持のため動かしておく
                sound.pause();
                startSrc();
                waActive = true;
                play(silent);
            } else {
                // 読み込み前・非対応時は <audio loop> で再生
                waActive = false;
                if (silent) silent.pause();
                play(sound);
            }
            play(video);
        } else {
            waActive = false;
            stopSrc();
            if (video) { video.pause(); try { video.currentTime = 0; } catch (e) {} }
            sound.pause();
            try { sound.currentTime = 0; } catch (e) {}
            play(silent);
        }
    }

    // 自動再生がブロックされた場合（ONのままページを開いた直後など）は、最初のタップで再生を開始する
    function arm() {
        if (armed) return;
        armed = true;
        document.addEventListener('click', apply, { once: true });
        document.addEventListener('touchend', apply, { once: true });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') apply();
    });

    load(); // 先に音源を読み込んでおく（再生はタップ後）

    window.lwLevelSound = {
        // <audio>フォールバックで鳴らしている間だけtrue（無音ループ側が止める判定に使う）
        isOn: function () { return on && !waActive; },
        // タップ操作の中から同期的に呼ぶこと（iOSの再生制限対策）
        set: function (v) { on = !!v; apply(); arm(); }
    };
})();
