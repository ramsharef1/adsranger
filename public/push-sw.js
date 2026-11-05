/* AdsRanger #175: phone alerts. Shows Critical alert pushes and opens the alert page on tap. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let d = {};
    try { d = event.data ? event.data.json() : {}; } catch (e) { d = { title: 'AdsRanger', body: event.data ? event.data.text() : '' }; }
    event.waitUntil(self.registration.showNotification(d.title || 'AdsRanger', {
        body: d.body || '',
        tag: d.tag || 'adsr',
        renotify: true,
        icon: '/brand/icon-192.png',
        badge: '/brand/icon-192.png',
        data: { url: d.url || '/app' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL((event.notification.data && event.notification.data.url) || '/app', self.location.origin).href;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
        for (const c of list) {
            if (c.url === url && 'focus' in c) return c.focus();
        }
        return self.clients.openWindow(url);
    }));
});
