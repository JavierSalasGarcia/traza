const CACHE_NAME = 'trazafi-v1';
const OFFLINE_URL = '/public/offline.html';

// Recursos críticos para cachear durante la instalación
const CRITICAL_ASSETS = [
  '/public/dashboard.php',
  '/public/offline.html',
  '/main.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Instalación del Service Worker
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[SW] Precaching critical assets');
      return cache.addAll(CRITICAL_ASSETS).catch((error) => {
        console.error('[SW] Failed to cache critical assets:', error);
      });
    })
  );
  // Activar inmediatamente sin esperar
  self.skipWaiting();
});

// Activación del Service Worker
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('[SW] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  // Tomar control inmediatamente
  return self.clients.claim();
});

// Estrategia de cache: Network First con fallback a Cache
self.addEventListener('fetch', (event) => {
  // Solo cachear GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // Ignorar requests que no sean de la misma origen o CDN
  const url = new URL(event.request.url);
  if (url.origin !== location.origin && !url.host.includes('cdnjs.cloudflare.com')) {
    return;
  }

  // Ignorar API requests y formularios POST
  if (url.pathname.includes('/api/') || url.pathname.includes('logout.php')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Si la respuesta es válida, clonarla y guardarla en cache
        if (response && response.status === 200) {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return response;
      })
      .catch(() => {
        // Si falla el fetch, intentar obtener del cache
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }

          // Si no hay en cache y es una página, mostrar offline.html
          if (event.request.mode === 'navigate') {
            return caches.match(OFFLINE_URL);
          }

          // Para otros recursos, retornar un error genérico
          return new Response('Recurso no disponible offline', {
            status: 503,
            statusText: 'Service Unavailable'
          });
        });
      })
  );
});

// Sincronización en background (para cuando recupere conexión)
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-notifications') {
    event.waitUntil(syncNotifications());
  }
});

async function syncNotifications() {
  try {
    // Aquí puedes implementar lógica para sincronizar notificaciones
    console.log('[SW] Syncing notifications');
  } catch (error) {
    console.error('[SW] Sync failed:', error);
  }
}

// Push notifications (para futuras implementaciones)
self.addEventListener('push', (event) => {
  if (event.data) {
    const data = event.data.json();
    const options = {
      body: data.body || 'Nueva notificación de TrazaFI',
      icon: '/public/icons/icon-192x192.png',
      badge: '/public/icons/icon-72x72.png',
      vibrate: [200, 100, 200],
      data: {
        url: data.url || '/public/dashboard.php'
      }
    };

    event.waitUntil(
      self.registration.showNotification(data.title || 'TrazaFI', options)
    );
  }
});

// Click en notificación push
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  event.waitUntil(
    clients.openWindow(event.notification.data.url || '/public/dashboard.php')
  );
});
