const CACHE_NAME = 'randevu-yonetim-sistemi-v1';
const urlsToCache = [
    '/',
    '/index',
    '/dashboard',
    '/clients',
    '/appointments',
    '/payments',
    '/client-details',
    '/assets/css/style.css?v=9',
    '/manifest.json',
    '/browserconfig.xml'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    // Sadece HTTP/HTTPS isteklerini önbelleğe al
    if (!event.request.url.startsWith('http')) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Sadece başarılı yanıtları önbelleğe al
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }

                // Önbelleğe al
                const responseClone = response.clone();
                caches.open(CACHE_NAME)
                    .then(cache => {
                        cache.put(event.request, responseClone);
                    });
                return response;
            })
            .catch(() => {
                // Çevrimdışı ise önbellekten getir
                return caches.match(event.request);
            })
    );
});

// Eski önbellekleri temizle
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
}); 