const CACHE_NAME = __AURA_CACHE_NAME__;
const CACHE_PREFIX = __AURA_CACHE_PREFIX__;
const RETIRE = __AURA_RETIRE__;
const OFFLINE_URL = '/offline.html';
const STATIC_ASSETS = [
    OFFLINE_URL,
    '/assets/aura/pwa/icon-192.png',
    '/assets/aura/pwa/icon-512.png',
    '/assets/aura/pwa/icon-maskable-192.png',
    '/assets/aura/pwa/icon-maskable-512.png',
    '/assets/aura/pwa/apple-touch-icon.png',
];
const SENSITIVE_PREFIXES = ['/admin', '/login', '/register', '/forgot-password', '/reset-password', '/verify-email', '/mission-submissions/'];
const OFFLINE_NAVIGATIONS = [/^\/$/, /^\/dashboard\/?$/, /^\/opportunities(?:\/\d+)?\/?$/, /^\/projects(?:\/\d+)?\/?$/, /^\/missions(?:\/\d+)?\/?$/, /^\/referrals\/?$/, /^\/achievements\/?$/, /^\/wallet\/?$/, /^\/integrations\/?$/, /^\/subscriptions\/?$/, /^\/settings\/?$/];

function isSameOrigin(requestUrl) {
    return requestUrl.origin === self.location.origin;
}

function isSensitivePath(pathname) {
    return SENSITIVE_PREFIXES.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`));
}

function isAllowedOfflineNavigation(pathname) {
    return OFFLINE_NAVIGATIONS.some((pattern) => pattern.test(pathname));
}

self.addEventListener('install', (event) => {
    if (RETIRE) {
        event.waitUntil(self.skipWaiting());
        return;
    }

    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);

        for (const asset of STATIC_ASSETS) {
            const response = await fetch(asset, {cache: 'no-store'});
            const contentType = response.headers.get('Content-Type') || '';
            const isExpectedContentType = asset.endsWith('.html')
                ? contentType.toLowerCase().includes('text/html')
                : contentType.toLowerCase().includes('image/png');

            if (!response.ok || !isExpectedContentType) {
                throw new Error(`The public PWA asset could not be cached: ${asset}`);
            }

            await cache.put(asset, response);
        }
    })());
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys
            .filter((key) => key.startsWith(CACHE_PREFIX) && (RETIRE || key !== CACHE_NAME))
            .map((key) => caches.delete(key)));

        if (RETIRE) {
            await self.registration.unregister();
        }
    })());
});

self.addEventListener('fetch', (event) => {
    if (RETIRE) {
        return;
    }

    const requestUrl = new URL(event.request.url);

    if (event.request.method !== 'GET' || !isSameOrigin(requestUrl) || event.request.mode !== 'navigate') {
        return;
    }

    if (isSensitivePath(requestUrl.pathname) || !isAllowedOfflineNavigation(requestUrl.pathname)) {
        return;
    }

    event.respondWith(fetch(event.request).catch(() => offlineResponse()));
});

async function offlineResponse() {
    try {
        if (await caches.has(CACHE_NAME)) {
            const response = await (await caches.open(CACHE_NAME)).match(OFFLINE_URL);
            const contentType = response?.headers.get('Content-Type') || '';

            if (response?.ok && contentType.toLowerCase().includes('text/html')) {
                return response;
            }
        }
    } catch {
        // Cache Storage may be unavailable. A generic response still keeps the request safe.
    }

    return new Response(
        '<!doctype html><html lang="ro"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AURA offline</title><body><main><h1>AURA este momentan offline</h1><p>Verifică conexiunea la internet și încearcă din nou.</p></main></body></html>',
        {status: 503, headers: {'Content-Type': 'text/html; charset=UTF-8', 'Cache-Control': 'no-store'}},
    );
}
