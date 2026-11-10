// Service Worker for Portal Wali Murid PWA
const CACHE_NAME = 'portal-wali-v2';
const ASSETS_TO_CACHE = [
  './manifest.json',
  './assets/logo.png',
  'https://cdn.tailwindcss.com',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  // Network first, fallback to cached response or friendly offline page
  if (e.request.mode === 'navigate') {
    e.respondWith(
      fetch(e.request).catch(async () => {
        const cached = await caches.match(e.request);
        if (cached) return cached;
        return new Response(
          '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Koneksi Terputus</title><style>body{font-family:sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:20px;text-align:center}h1{font-size:18px;margin-bottom:8px}p{font-size:13px;color:#94a3b8}button{margin-top:16px;background:#0d9488;color:#fff;border:none;padding:10px 20px;border-radius:12px;font-weight:bold;cursor:pointer}</style></head><body><div><h1>Koneksi Internet Terputus</h1><p>Pastikan perangkat Anda terhubung ke internet, lalu coba lagi.</p><button onclick="location.reload()">Coba Lagi</button></div></body></html>',
          { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
      })
    );
    return;
  }

  // Cache first for static assets
  e.respondWith(
    caches.match(e.request).then((res) => {
      return res || fetch(e.request);
    })
  );
});

