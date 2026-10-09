// Level.2〜4 用：技術発生ONの間、映像を0.8秒のディゾルブ（クロスフェード）でループ再生する。
// 標準コントロールは出さず、音量(ミュート切替)ボタンだけ置く。動画は初期状態でミュート。
(function () {
    var FADE = 0.8;      // ディゾルブの長さ（秒）
    var OP = 0.9;        // 映像の不透明度
    var items = {};

    function play(v) {
        try { var r = v.play(); if (r && r.catch) r.catch(function () {}); } catch (e) {}
    }

    function init(level) {
        if (items[level]) return items[level];
        var video = document.querySelector('[data-lw-dv="' + level + '"]');
        if (!video) return null;
        var btn = document.querySelector('[data-lw-dvol="' + level + '"]');
        video.removeAttribute('loop');
        video.loop = false;
        var vB = video.cloneNode(false);
        vB.removeAttribute('data-lw-dv');
        vB.className = 'absolute inset-0 w-full h-full object-contain pointer-events-none';
        vB.style.opacity = '0';
        video.insertAdjacentElement('afterend', vB);

        var it = { video: video, vB: vB, cur: video, other: vB, on: false, fading: false, fadeStart: 0, raf: null };
        items[level] = it;

        function syncIcon() {
            if (!btn) return;
            var m = video.muted;
            var onI = btn.querySelector('[data-lw-vol-on]'), offI = btn.querySelector('[data-lw-vol-off]');
            if (onI) onI.classList.toggle('hidden', m);
            if (offI) offI.classList.toggle('hidden', !m);
        }
        if (btn) {
            btn.addEventListener('click', function () {
                var m = !video.muted;
                video.muted = m; vB.muted = m;
                syncIcon();
            });
            syncIcon();
        }

        // 背景などでrAFが止まり終端まで再生された場合の保険：頭から再生し直す
        [video, vB].forEach(function (v) {
            v.addEventListener('ended', function () {
                if (it.on && v === it.cur && !it.fading) { try { v.currentTime = 0; } catch (e) {} play(v); }
            });
        });

        function loop() {
            it.raf = null;
            if (!it.on) return;
            var d = it.cur.duration;
            if (d && isFinite(d) && d > FADE * 2) {
                if (!it.fading && d - it.cur.currentTime <= FADE) {
                    it.fading = true;
                    it.fadeStart = performance.now();
                    try { it.other.currentTime = 0; } catch (e) {}
                    play(it.other);
                }
                if (it.fading) {
                    var k = Math.min(1, (performance.now() - it.fadeStart) / (FADE * 1000));
                    it.other.style.opacity = String(k * OP);
                    it.cur.style.opacity = String((1 - k) * OP);
                    if (k >= 1) {
                        it.cur.pause();
                        try { it.cur.currentTime = 0; } catch (e) {}
                        var t = it.cur; it.cur = it.other; it.other = t;
                        it.fading = false;
                    }
                }
            }
            it.raf = requestAnimationFrame(loop);
        }
        it.loop = loop;
        return it;
    }

    function start(it) {
        play(it.cur);
        if (it.fading) play(it.other);
        if (!it.raf) it.raf = requestAnimationFrame(it.loop);
    }

    function stop(it) {
        if (it.raf) { cancelAnimationFrame(it.raf); it.raf = null; }
        it.fading = false;
        [it.video, it.vB].forEach(function (v) {
            v.pause();
            try { v.currentTime = 0; } catch (e) {}
        });
        it.cur = it.video;
        it.other = it.vB;
        it.video.style.opacity = '';
        it.vB.style.opacity = '0';
    }

    window.lwLevelVideo = {
        // タップ操作の中から同期的に呼ぶこと（iOSの再生制限対策）
        set: function (level, on) {
            var it = init(level);
            if (!it) return;
            it.on = !!on;
            if (it.on) start(it); else stop(it);
        }
    };

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'visible') return;
        Object.keys(items).forEach(function (k) { if (items[k].on) start(items[k]); });
    });
})();
