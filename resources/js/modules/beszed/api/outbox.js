import { http } from './client'
import { DROP_AFTER, isRetryable, isServerError, judgeFailure, newIdempotencyKey, retryDelayMs } from './outboxPolicy'

/**
 * Results that couldn't be sent wait here, in localStorage, and are uploaded in
 * order when the server can take them again: offline, signed out, throttled or
 * a server error during a deploy (the decision is in outboxPolicy.js). Each
 * carries `played_at`, so streaks and the daily goal count the day the child
 * actually played, and an `Idempotency-Key` sent with every attempt, so a
 * request that did arrive but lost its response is not counted twice. A result
 * that keeps getting server errors (`serverErrors`, stored with it) steps aside
 * for newer ones, and is dropped in the end.
 */
const KEY = 'beszed/outbox'
const MAX = 500
const IDEMPOTENCY_HEADER = 'Idempotency-Key'
let flushing = false
/** Failed flushes in a row: drives the backoff, back to 0 after a success. */
let failures = 0
let timer = null
let nextFlushAt = 0

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

const send = item => http.post(item.url, item.body, { headers: { [IDEMPOTENCY_HEADER]: item.key } })

/** Another flush later, `delay` ms from now (replaces one already waiting). */
function scheduleFlush(delay) {
  if (typeof window === 'undefined') return
  clearTimeout(timer)
  nextFlushAt = Date.now() + delay
  timer = setTimeout(() => {
    timer = null
    flushOutbox()
  }, delay)
}

/** POST that falls back to the outbox when it can't get through; still rejects, so callers know it didn't reach the server. */
export async function postOrQueue(url, body) {
  // Before the first attempt: if its response is lost, the retry carries the same key.
  const key = newIdempotencyKey()
  try {
    return await http.post(url, body, { headers: { [IDEMPOTENCY_HEADER]: key } })
  } catch (error) {
    if (isRetryable(error)) {
      write([...read(), { url, body: { ...body, played_at: new Date().toISOString() }, key, serverErrors: isServerError(error) ? 1 : 0 }])
      if (!flushing && timer === null) scheduleFlush(retryDelayMs(error, ++failures))
    }
    throw error
  }
}

export const pendingCount = () => read().length

/**
 * Sends everything waiting, oldest first. Stops at the first failure that may
 * still pass later and tries again after a growing pause; drops what the
 * server rejects for good (403, 422…). A result that has had 5 server errors
 * goes to the back so the others still get out; after 25 it is dropped.
 */
export async function flushOutbox() {
  if (flushing || typeof navigator === 'undefined' || navigator.onLine === false) return
  flushing = true
  clearTimeout(timer)
  timer = null
  let retryIn = null
  try {
    // Results queued by older versions have no key yet: give them one, kept for every retry.
    const queued = read()
    if (queued.some(item => !item.key)) write(queued.map(item => (item.key ? item : { ...item, key: newIdempotencyKey() })))

    // Re-read each time: a result queued while this one was in flight must not be overwritten.
    const sent = new Set()
    for (let item = read()[0]; item && !sent.has(item.key); item = read()[0]) {
      sent.add(item.key)
      try {
        await send(item)
        failures = 0
      } catch (error) {
        const { action, serverErrors } = judgeFailure(error, item.serverErrors)
        if (action === 'wait') {
          if (serverErrors !== (item.serverErrors ?? 0)) write(read().map(other => (other.key === item.key ? { ...other, serverErrors } : other)))
          retryIn = retryDelayMs(error, ++failures)
          break
        }
        if (action === 'requeue') {
          // Always answered with a server error: the others go first, this one is tried again later.
          write([...read().filter(other => other.key !== item.key), { ...item, serverErrors }])
          retryIn ??= retryDelayMs(error, ++failures)
          continue
        }
        // 403/422…: this can never succeed (other account, too old). Or it has had too many server errors: give up.
        if (serverErrors >= DROP_AFTER) console.warn('[beszed] outbox: gave up on a result after repeated server errors')
      }
      write(read().filter(other => other.key !== item.key))
    }
  } finally {
    flushing = false
    if (retryIn !== null) scheduleFlush(retryIn)
    else nextFlushAt = 0
  }
}

if (typeof window !== 'undefined') {
  // Back online: try straight away, whatever the pause was.
  window.addEventListener('online', () => {
    failures = 0
    flushOutbox()
  })
  // The page comes back to the front (a phone unlocked): flush, unless a pause is still running.
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && Date.now() >= nextFlushAt && pendingCount()) flushOutbox()
  })
}
