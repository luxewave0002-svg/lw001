// LUXE WAVE: Service Worker廃止用（自己解除）
// 以前登録された端末に残っているSWを、更新時に自動で解除するためのファイル。
// fetchハンドラは持たない（全リクエストを横取りしない）。
// 理由: iOS 26.5系のSafariで、SW管理下のページからのファイル送信が空ボディ(Content-Length: 0)になる
//       報告があり（WebKit Bugzilla #319985 / #320904）、アップロード不具合の原因候補だったため。
self.addEventListener('install', function() {
    self.skipWaiting();
});
self.addEventListener('activate', function(event) {
    event.waitUntil(self.registration.unregister());
});
