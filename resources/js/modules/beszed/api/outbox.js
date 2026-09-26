import { http } from './client'

/**
 * Results that couldn't be sent (no connection) wait here, in localStorage, and
 * are uploaded in order when the device is back online. Each carries `played_at`,
 * so streaks and the daily goal count the day the child actually played.
 */
const KEY = 'beszed/outbox'
const MAX = 500
let flushing = false

function read() {
  try {
    return JSON.parse(localStorage.getItem(KEY) || '[]')
  } catch {
    return []
  }
}

function write(items) {
  try {
    if (items.length) localStorage.setItem(KEY, JSON.stringify(items.slice(-MAX)))
    else localStorage.removeItem(KEY)
  } catch {
    /* storage full / blocked: the result is lost, the game goes on */
  }
}

/** No HTTP response at all = offline (not a server error). */
export const isNetworkError = error => Boolean(error) && !error.response

/** POST that falls back to the outbox when offline; still rejects, so callers know it didn't reach the server. */
export async function postOrQueue(url, body) {
  try {
    return await http.post(url, body)
  } catch (error) {
    if (isNetworkError(error)) write([...read(), { url, body: { ...body, played_at: new Date().toISOString() } }])
    throw error
  }
}

export const pendingCount = () => read().length

/** Sends everything waiting, oldest first. Stops at the first network error; drops what the server rejects. */
export async function flushOutbox() {
  if (flushing || typeof navigator === 'undefined' || navigator.onLine === false) return
  flushing = true
  try {
    let items = read()
    while (items.length) {
      try {
        await http.post(items[0].url, items[0].body)
      } catch (error) {
        const status = error.response?.status
        // Offline again, or signed out / CSRF expired: keep it for later.
        if (isNetworkError(error) || status === 401 || status === 419) break
        // 403/422…: this can never succeed (other account, too old); drop it.
      }
      items = items.slice(1)
      write(items)
    }
  } finally {
    flushing = false
  }
}

if (typeof window !== 'undefined') window.addEventListener('online', () => flushOutbox())
