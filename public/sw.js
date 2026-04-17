self.addEventListener('push', function(event) {
    var data = {};
    try { data = event.data.json(); } catch(e) { data = { title: 'FINOVATE', body: event.data ? event.data.text() : 'Nouvelle notification' }; }

    event.waitUntil(
        self.registration.showNotification(data.title || 'FINOVATE', {
            body: data.body || '',
            icon: data.icon || '/backoffice/assets/images/logo-icon.svg',
            badge: '/backoffice/assets/images/favicon-32x32.png',
            data: { url: data.url || '/' }
        })
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data.url || '/'));
});