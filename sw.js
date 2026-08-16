// LUXE WAVE PWA用 最小構成Service Worker
// キャッシュ戦略は持たせず、通常のネットワーク通信をそのまま素通しする。
// これはインストール可能性（Chrome等のPWA判定条件）を満たすためだけの実装。

self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    self.clients.claim();
});

self.addEventListener('fetch', function(event) {
    event.respondWith(fetch(event.request));
});
