/**
 * The outbox's decisions, kept pure (no browser, no axios) so `node --test`
 * covers them (tests/js/outbox.test.mjs). api/outbox.js does the storing and sending.
 */

/** First retry after this; every further failed flush doubles it. */
export const BACKOFF_FIRST_MS = 30_000
export const BACKOFF_MAX_MS = 15 * 60_000

/** A result that keeps answering 5xx goes to the back of the queue after this many such answers, so newer ones are not stuck behind it... */
export const REQUEUE_AFTER = 5
/** ...and is given up on at this many. Only server errors count: being offline, signed out or throttled says nothing about the result. */
export const DROP_AFTER = 25

/** Not a server verdict on the result itself: signed out, CSRF expired, timed out, too early, throttled. */
const RETRY_STATUSES = new Set([401, 408, 419, 425, 429])

/**
 * Keep the result for later? Yes when it may still go through: no response at
 * all (offline), the statuses above, and any 5xx (a deploy, an outage). No for
 * the other 4xx (403 another account, 422 too old or invalid): they can never succeed.
 */
export function isRetryable(error) {
  if (!error) return false
  const status = error.response?.status
  if (!status) return true
  return status >= 500 || RETRY_STATUSES.has(status)
}

export const isServerError = error => (error?.response?.status ?? 0) >= 500

/**
 * What to do with a queued result after a failed attempt; `serverErrors` is how many 5xx answers it had before.
 * 'drop' = never (a 4xx that cannot succeed) or given up; 'requeue' = to the back of the queue, others first;
 * 'wait' = keep it at the front and stop for now. Returns the new count to store with it.
 * @returns {{ action: 'drop' | 'requeue' | 'wait', serverErrors: number }}
 */
export function judgeFailure(error, serverErrors = 0) {
  const before = Number(serverErrors) || 0
  if (!isRetryable(error)) return { action: 'drop', serverErrors: before }
  if (!isServerError(error)) return { action: 'wait', serverErrors: before }
  const count = before + 1
  if (count >= DROP_AFTER) return { action: 'drop', serverErrors: count }
  return { action: count % REQUEUE_AFTER === 0 ? 'requeue' : 'wait', serverErrors: count }
}

/** 30 s, 60 s, 120 s … capped at 15 min; `failures` counts the failed flushes in a row (1 = the first). */
export function backoffMs(failures) {
  const n = Math.max(1, Math.floor(Number(failures)) || 1)
  return Math.min(BACKOFF_MAX_MS, BACKOFF_FIRST_MS * 2 ** (n - 1))
}

function header(error, name) {
  const headers = error?.response?.headers
  const value = typeof headers?.get === 'function' ? headers.get(name) : headers?.[name]
  return value == null ? null : String(value).trim()
}

/** A 429's numeric `Retry-After` (seconds) in ms, at least 1 s and at most 15 min; null when absent or not a number. */
export function retryAfterMs(error) {
  if (error?.response?.status !== 429) return null
  const value = header(error, 'retry-after')
  if (value === null || !/^\d+(\.\d+)?$/.test(value)) return null
  return Math.min(BACKOFF_MAX_MS, Math.max(1000, Math.round(Number(value) * 1000)))
}

/** How long to wait before the next flush after this failure. */
export const retryDelayMs = (error, failures) => retryAfterMs(error) ?? backoffMs(failures)

/**
 * One key per result, made before its first attempt and sent again with every
 * retry, so the server can answer a repeat with the stored response instead of
 * counting the result twice. `crypto.randomUUID` only exists on secure origins.
 */
export function newIdempotencyKey(source = globalThis.crypto) {
  if (typeof source?.randomUUID === 'function') return source.randomUUID()
  const bytes = new Uint8Array(16)
  if (typeof source?.getRandomValues === 'function') source.getRandomValues(bytes)
  else for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256)
  bytes[6] = (bytes[6] & 0x0f) | 0x40 // version 4
  bytes[8] = (bytes[8] & 0x3f) | 0x80 // variant
  const hex = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('')
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}
