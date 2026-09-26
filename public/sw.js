/*
 * Beszéd & DIFER service worker: offline play after the first visit.
 *
 *   /build/*, /icons/*        cache-first; the whole Vite build is precached on install
 *   /pictograms/*              cache-first (ARASAAC pictures never change under their id)
 *   TTS + recording audio      cache-first (URLs never change their content)
 *   GET /api/*                 network-first, falls back to the last answer (offline play)
 *   page navigations           network-first, falls back to the cached app shell
 *   POST/PUT/DELETE            never touched: offline results go to the app's outbox
 *
 * Personal API data is wiped on sign-out (message "clear-user-data").
 * Bump VERSION when this file's logic changes.
 */
const VERSION = 'v1'
const ASSETS = `beszed-assets-${VERSION}`
const SHELL = `beszed-shell-${VERSION}`
const AUDIO = 'beszed-audio'
const API = 'beszed-api'
const PICTOGRAMS = 'beszed-pictograms'
const KEEP = [ASSETS, SHELL, AUDIO, API, PICTOGRAMS]

self.addEventListener('install', event => {
  event.waitUntil(
    (async () => {
      try {
        const manifest = await (await fetch('/build/manifest.json', { cache: 'no-store' })).json()
        const files = new Set()
        for (const entry of Object.values(manifest)) {
          files.add(entry.file)
          for (const f of [...(entry.css ?? []), ...(entry.assets ?? [])]) files.add(f)
        }
        const cache = await caches.open(ASSETS)
        await cache.addAll([...files].map(f => `/build/${f}`))
      } catch {
        // Installing while offline or without a build: runtime caching still covers what gets used.
      }
      try {
        // The app shell, so even the first offline start has a page to boot from.
        const shell = await fetch('/', { credentials: 'same-origin' })
        if (shell.status === 200) await (await caches.open(SHELL)).put('/__shell', shell)
      } catch {
        /* offline */
      }
      await self.skipWaiting()
    })(),
  )
})

self.addEventListener('activate', event => {
  event.waitUntil(
    (async () => {
      for (const key of await caches.keys()) if (!KEEP.includes(key)) await caches.delete(key)
      await self.clients.claim()
    })(),
  )
})

self.addEventListener('message', event => {
  if (event.data?.type === 'clear-user-data') {
    event.waitUntil(Promise.all([caches.delete(API), caches.delete(SHELL), caches.delete(AUDIO)]))
  }
})

// Never kept: the data export, the editor, and therapist share links (a revoked link must stop working).
const NO_CACHE = /^\/api\/(me\/export$|admin\/|share\/)/

self.addEventListener('fetch', event => {
  const request = event.request
  if (request.method !== 'GET') return
  const url = new URL(request.url)
  if (url.origin !== self.location.origin) return

  if (request.mode === 'navigate') {
    event.respondWith(networkFirst(request, SHELL, '/__shell'))
  } else if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
    event.respondWith(cacheFirst(request, ASSETS))
  } else if (url.pathname.startsWith('/pictograms/')) {
    event.respondWith(cacheFirst(request, PICTOGRAMS))
  } else if (url.pathname === '/api/beszed/tts' || /^\/api\/beszed\/recordings\/[^/]+\/audio$/.test(url.pathname)) {
    event.respondWith(cacheFirst(request, AUDIO))
  } else if (url.pathname.startsWith('/api/') && !NO_CACHE.test(url.pathname)) {
    event.respondWith(networkFirst(request, API))
  }
})

async function cacheFirst(request, name) {
  const cache = await caches.open(name)
  const hit = await cache.match(request, { ignoreVary: true })
  if (hit) return hit
  const response = await fetch(request)
  // Only whole responses (an <audio> range request gets 206, which can't be cached).
  if (response.status === 200) cache.put(request, response.clone())
  return response
}

async function networkFirst(request, name, key = request) {
  const cache = await caches.open(name)
  try {
    const response = await fetch(request)
    if (response.status === 200) cache.put(key, response.clone())
    return response
  } catch (error) {
    const hit = await cache.match(key, { ignoreVary: true })
    if (hit) return hit
    throw error
  }
}
