// The outbox's retry decisions (resources/js/modules/beszed/api/outboxPolicy.js). Run: npm run test:js
import assert from 'node:assert/strict'
import { test } from 'node:test'
import {
  BACKOFF_FIRST_MS,
  BACKOFF_MAX_MS,
  DROP_AFTER,
  REQUEUE_AFTER,
  backoffMs,
  isRetryable,
  judgeFailure,
  newIdempotencyKey,
  retryAfterMs,
  retryDelayMs,
} from '../../resources/js/modules/beszed/api/outboxPolicy.js'

const failed = (status, headers) => ({ response: { status, headers } })
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/

test('no response (offline, timeout) is kept for later', () => {
  assert.equal(isRetryable(new Error('Network Error')), true)
  assert.equal(isRetryable({ response: undefined }), true)
})

test('signed out, CSRF expired, timeout, too early and throttled are kept', () => {
  for (const status of [401, 419, 408, 425, 429]) assert.equal(isRetryable(failed(status)), true, String(status))
})

test('every 5xx is kept (a deploy, an outage)', () => {
  for (const status of [500, 502, 503, 504, 599]) assert.equal(isRetryable(failed(status)), true, String(status))
})

test('the other 4xx can never succeed and are dropped', () => {
  for (const status of [400, 403, 404, 405, 409, 410, 422]) assert.equal(isRetryable(failed(status)), false, String(status))
})

test('no error is not a retry', () => {
  assert.equal(isRetryable(undefined), false)
  assert.equal(isRetryable(null), false)
})

test('backoff doubles from 30 s and stops at 15 min', () => {
  assert.equal(BACKOFF_FIRST_MS, 30_000)
  assert.deepEqual([1, 2, 3, 4, 5, 6].map(backoffMs), [30_000, 60_000, 120_000, 240_000, 480_000, 900_000])
  assert.equal(backoffMs(7), BACKOFF_MAX_MS)
  assert.equal(backoffMs(10_000), BACKOFF_MAX_MS)
})

test('a nonsense failure count still gives the first pause', () => {
  for (const n of [0, -3, NaN, undefined, 'x']) assert.equal(backoffMs(n), BACKOFF_FIRST_MS)
})

test('a 429 with a numeric Retry-After is honoured', () => {
  assert.equal(retryAfterMs(failed(429, { 'retry-after': '12' })), 12_000)
  assert.equal(retryDelayMs(failed(429, { 'retry-after': '12' }), 5), 12_000)
})

test('Retry-After is read from axios headers objects too', () => {
  const headers = { get: name => (name === 'retry-after' ? '45' : null) }
  assert.equal(retryAfterMs(failed(429, headers)), 45_000)
})

test('Retry-After is at least 1 s and at most 15 min', () => {
  assert.equal(retryAfterMs(failed(429, { 'retry-after': '0' })), 1000)
  assert.equal(retryAfterMs(failed(429, { 'retry-after': '86400' })), BACKOFF_MAX_MS)
})

test('without a usable Retry-After a 429 backs off like any other failure', () => {
  assert.equal(retryDelayMs(failed(429), 2), 60_000)
  assert.equal(retryDelayMs(failed(429, { 'retry-after': 'Wed, 21 Oct 2026 07:28:00 GMT' }), 3), 120_000)
})

test('Retry-After on other statuses is ignored', () => {
  assert.equal(retryAfterMs(failed(503, { 'retry-after': '5' })), null)
  assert.equal(retryDelayMs(failed(503, { 'retry-after': '5' }), 1), 30_000)
})

test('idempotency keys are v4 UUIDs and differ', () => {
  const a = newIdempotencyKey()
  assert.match(a, UUID)
  assert.notEqual(a, newIdempotencyKey())
})

test('without crypto.randomUUID (an http origin) a v4 UUID is still made', () => {
  const key = newIdempotencyKey({ getRandomValues: bytes => bytes.fill(0xff) })
  assert.match(key, UUID)
  assert.match(newIdempotencyKey({}), UUID)
})

test('judgeFailure: server errors count, every 5th steps back, the 25th drops', () => {
  assert.equal(REQUEUE_AFTER, 5)
  assert.equal(DROP_AFTER, 25)
  let count = 0
  const notable = []
  for (let n = 1; n <= 25; n++) {
    const verdict = judgeFailure(failed(500), count)
    count = verdict.serverErrors
    if (verdict.action !== 'wait') notable.push(`${n}:${verdict.action}`)
  }
  assert.equal(count, 25)
  assert.deepEqual(notable, ['5:requeue', '10:requeue', '15:requeue', '20:requeue', '25:drop'])
})

test('judgeFailure: offline, 401, 419, 429, 408 and 425 wait without counting', () => {
  for (const error of [new Error('Network Error'), failed(401), failed(419), failed(429), failed(408), failed(425)]) {
    assert.deepEqual(judgeFailure(error, 4), { action: 'wait', serverErrors: 4 })
  }
})

test('judgeFailure: a 4xx that cannot succeed is dropped, the count untouched', () => {
  assert.deepEqual(judgeFailure(failed(422), 3), { action: 'drop', serverErrors: 3 })
  assert.deepEqual(judgeFailure(failed(403)), { action: 'drop', serverErrors: 0 })
})

test('judgeFailure: items from before the counter existed start at 0', () => {
  assert.deepEqual(judgeFailure(failed(502), undefined), { action: 'wait', serverErrors: 1 })
  assert.deepEqual(judgeFailure(failed(502), 'x'), { action: 'wait', serverErrors: 1 })
})
