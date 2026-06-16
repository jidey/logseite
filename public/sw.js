/**
 * SERVICE WORKER
 * Cache intelligent et support offline
 * Sauvegardé en : public/sw.js
 */

const CACHE_NAME = 'logg-v2.0';
const RUNTIME_CACHE = 'logg-runtime';
const IMAGES_CACHE = 'logg-images';

// Ressources à mettre en cache au premier chargement
const PRECACHE_URLS = [
    '/',
    '/index.php',
    '/public/css/styles.min.css',
    '/public/js/app.min.js',
    '/public/cache_management.php'
];

// Install event - mettre en cache les ressources critiques
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Service Worker: mise en cache des ressources');
                return cache.addAll(PRECACHE_URLS);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - nettoyer les anciens caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME && 
                        cacheName !== RUNTIME_CACHE && 
                        cacheName !== IMAGES_CACHE) {
                        console.log('Service Worker: suppression du cache', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - stratégie de caching intelligent
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Les requêtes POST vont directement au réseau
    if (request.method !== 'GET') {
        return event.respondWith(fetch(request));
    }
    
    // Images : cache-first
    if (request.destination === 'image') {
        return event.respondWith(
            caches.open(IMAGES_CACHE)
                .then(cache => {
                    return cache.match(request).then(response => {
                        return response || fetch(request).then(response => {
                            // Mettre en cache la réponse
                            cache.put(request, response.clone());
                            return response;
                        });
                    });
                })
                .catch(() => {
                    // Offline : retourner une image placeholder
                    return new Response('', { status: 404 });
                })
        );
    }
    
    // CSS/JS : cache-first avec fallback réseau
    if (request.destination === 'style' || request.destination === 'script') {
        return event.respondWith(
            caches.match(request).then(response => {
                return response || fetch(request).then(response => {
                    // Mettre en cache les nouvelles versions
                    if (response.ok) {
                        const cache = caches.open(CACHE_NAME);
                        cache.then(c => c.put(request, response.clone()));
                    }
                    return response;
                }).catch(() => {
                    // Offline : retourner une version en cache si disponible
                    return caches.match(request);
                });
            })
        );
    }
    
    // HTML et API : network-first avec fallback cache
    return event.respondWith(
        fetch(request)
            .then(response => {
                // Mettre en cache les réponses réussies
                if (response.ok) {
                    const cache = caches.open(RUNTIME_CACHE);
                    cache.then(c => c.put(request, response.clone()));
                }
                return response;
            })
            .catch(() => {
                // Offline : retourner la version en cache
                return caches.match(request).then(response => {
                    return response || new Response('Offline - Page non disponible', {
                        status: 503,
                        statusText: 'Service Unavailable',
                        headers: new Headers({
                            'Content-Type': 'text/plain'
                        })
                    });
                });
            })
    );
});

// Message event - contrôler le cache depuis le client
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        caches.delete(CACHE_NAME);
        caches.delete(RUNTIME_CACHE);
        caches.delete(IMAGES_CACHE);
    }
});
