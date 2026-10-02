/*
 * Beszéd & DIFER service worker: offline play after the first visit.
 *
 *   /build/*, /icons/*        cache-first; the Vite build is precached on install, except the PDF report stack
 *   /pictograms/*, /symbols/*  cache-first (ARASAAC / Mulberry pictures never change under their id)
 *   TTS + recording audio      cache-first (URLs never change their content)
 *   GET /api/*                 network-first, falls back to the last answer (offline play)
 *   page navigations           network-first, falls back to the cached app shell
 *   POST/PUT/DELETE            never touched: offline results go to the app's outbox
 *
 * A new build installs a new worker: scripts/stamp-sw.mjs (after `vite build`) writes /build/sw-version.js,
 * a hash of the build's manifest, and the browser byte-compares imported scripts on every update check.
 * The asset and shell caches are named after that build, so each deploy is precached afresh and the old
 * build's caches are dropped on activate. Nothing here needs a hand-bumped version; only a change to this
 * file's logic is picked up by its own bytes, as before.
 *
 * The audio, API and pictogram caches are capped (oldest stored goes first). The heavy PDF stack
 * (jspdf, html2canvas, dompurify, canvg: ~0.8 MB, only used when a parent exports a report) is not
 * precached; it is cached at runtime when first used.
 *
 * Personal API data is wiped on sign-out (message "clear-user-data").
 */
try {
  importScripts('/build/sw-version.js')
} catch {
  // The dev server, or a build that was not stamped.
}
const BUILD = String(self.BESZED_BUILD || 'dev')
const ASSETS = `beszed-assets-${BUILD}`
const SHELL = `beszed-shell-${BUILD}`
const AUDIO = 'beszed-audio'
const API = 'beszed-api'
const PICTOGRAMS = 'beszed-pictograms'
const KEEP = [ASSETS, SHELL, AUDIO, API, PICTOGRAMS]
/** Most entries kept per cache; past that the oldest stored are deleted. */
const LIMITS = { [AUDIO]: 150, [API]: 100, [PICTOGRAMS]: 400 }
/** Manifest entries of the lazy PDF report (clinicalReport holds jspdf); matched on the manifest key. */
const PDF_STACK = /clinicalReport|node_modules\/(jspdf|html2canvas|dompurify|canvg)\//

self.addEventListener('install', event => {
  event.waitUntil(
    (async () => {
      try {
        const manifest = await (await fetch('/build/manifest.json', { cache: 'no-store' })).json()
        const files = new Set()
        for (const [key, entry] of Object.entries(manifest)) {
          if (PDF_STACK.test(key)) continue
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
    event.respondWith(networkFirst(event, SHELL, '/__shell'))
  } else if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
    event.respondWith(cacheFirst(event, ASSETS))
  } else if (url.pathname.startsWith('/pictograms/') || url.pathname.startsWith('/symbols/')) {
    event.respondWith(cacheFirst(event, PICTOGRAMS))
  } else if (url.pathname === '/api/beszed/tts' || /^\/api\/beszed\/recordings\/[^/]+\/audio$/.test(url.pathname)) {
    event.respondWith(cacheFirst(event, AUDIO))
  } else if (url.pathname.startsWith('/api/') && !NO_CACHE.test(url.pathname)) {
    event.respondWith(networkFirst(event, API))
  }
})

/** Stores a response, then deletes the oldest entries past the cache's limit (Cache.keys() lists oldest first). */
async function store(cache, name, key, response) {
  try {
    await cache.put(key, response)
    const limit = LIMITS[name]
    if (!limit) return
    const keys = await cache.keys()
    await Promise.all(keys.slice(0, Math.max(0, keys.length - limit)).map(k => cache.delete(k)))
  } catch {
    // Quota exceeded or a response that can't be stored: the page already has it.
  }
}

async function cacheFirst(event, name) {
  const request = event.request
  const cache = await caches.open(name)
  const hit = await cache.match(request, { ignoreVary: true })
  if (hit) return hit
  const response = await fetch(request)
  // Only whole responses (an <audio> range request gets 206, which can't be cached).
  if (response.status === 200) event.waitUntil(store(cache, name, request, response.clone()))
  return response
}

async function networkFirst(event, name, key = event.request) {
  const request = event.request
  const cache = await caches.open(name)
  try {
    const response = await fetch(request)
    if (response.status === 200) event.waitUntil(store(cache, name, key, response.clone()))
    return response
  } catch (error) {
    const hit = await cache.match(key, { ignoreVary: true })
    if (hit) return hit
    throw error
  }
}
