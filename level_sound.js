// Level.1 テスト用：技術発生がONの間だけ level_sound.m4a をループ再生する。
// OFFの間は従来どおり無音ループ(lw-bg-keepalive)に戻す。他のLevelでは読み込まれない。
(function () {
    var silent = document.getElementById('lw-bg-keepalive');
    var sound = document.getElementById('lw-level-sound');
    if (!sound) return;
    var on = false;
    var armed = false;

    function play(a) {
        if (!a) return;
        try { var r = a.play(); if (r && r.catch) r.catch(function () {}); } catch (e) {}
    }

    function apply() {
        if (on) {
            if (silent) silent.pause();
            play(sound);
        } else {
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

    window.lwLevelSound = {
        isOn: function () { return on; },
        // タップ操作の中から同期的に呼ぶこと（iOSの再生制限対策）
        set: function (v) { on = !!v; apply(); arm(); }
    };
})();
