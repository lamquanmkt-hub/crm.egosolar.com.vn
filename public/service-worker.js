self.addEventListener('push', function(event) {
  let data = {};
  try {
    data = event.data.json();
  } catch (e) {
    data = { title: 'Thông báo', body: event.data ? event.data.text() : 'Bạn có thông báo mới' };
  }

  const title = data.title || 'Thông báo';
  const options = {
    body: data.body || '',
    icon: data.icon || '/favicon.ico',
    badge: data.badge || '/favicon.ico',
    data: data.data || {}
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();

  const url = event.notification.data?.url || '/';
  event.waitUntil(
    clients.openWindow(url)
  );
});
