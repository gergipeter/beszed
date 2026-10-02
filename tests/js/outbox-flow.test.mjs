// api/outbox.js end to end against a fake http client, localStorage and timers: keys, retries, backoff, poison items.
// The module imports './client' and './outboxPolicy' without extensions (Vite style), so a copy with them added
// is imported from a temporary folder, next to a stand-in for the http client.
import assert from 'node:assert/strict'
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { after, afterEach, beforeEach, describe, test } from 'node:test'
import { pathToFileURL } from 'node:url'

const api = new URL('../../resources/js/modules/beszed/api/', import.meta.url)
const dir = mkdtempSync(join(tmpdir(), 'beszed-outbox-'))
const source = name => readFileSync(new URL(name, api), 'utf8')
writeFileSync(join(dir, 'outboxPolicy.js'), source('outboxPolicy.js'))
writeFileSync(join(dir, 'client.js'), 'export const http = { post: (...args) => globalThis.__post(...args) }\n')
writeFileSync(join(dir, 'outbox.js'), source('outbox.js').replace("'./client'", "'./client.js'").replace("'./outboxPolicy'", "'./outboxPolicy.js'"))

const STORAGE = 'beszed/outbox'
const store = new Map()
const timers = []
const realSetTimeout = globalThis.setTimeout
const realClearTimeout = globalThis.clearTimeout
const realWarn = console.warn
let calls = []
let script = []
let warnings = []
let counter = 0

const fail = (status, headers) => Object.assign(new Error(`http ${status}`), { response: { status, headers } })
const queue = () => JSON.parse(store.get(STORAGE) || '[]')

beforeEach(() => {
  store.clear()
  timers.length = 0
  calls = []
  script = []
  warnings = []
  globalThis.localStorage = {
    getItem: k => (store.has(k) ? store.get(k) : null),
    setItem: (k, v) => store.set(k, String(v)),
    removeItem: k => store.delete(k),
  }
  globalThis.window = { addEventListener() {} }
  globalThis.document = { visibilityState: 'visible', addEventListener() {} }
  Object.defineProperty(globalThis, 'navigator', { value: { onLine: true }, configurable: true })
  globalThis.setTimeout = (fn, ms) => timers.push({ fn, ms })
  globalThis.clearTimeout = () => {}
  console.warn = (...args) => warnings.push(args)
  // `script` holds what the next requests answer: 'ok', 'net', a status code, or [status, headers].
  globalThis.__post = async (url, body, options) => {
    calls.push({ url, body, key: options?.headers?.['Idempotency-Key'] })
    const next = script.shift() ?? 'ok'
    if (next === 'ok') return { ok: true }
    if (next === 'net') throw new Error('Network Error')
    throw Array.isArray(next) ? fail(...next) : fail(next)
  }
})
after(() => rmSync(dir, { recursive: true, force: true }))
afterEach(() => {
  globalThis.setTimeout = realSetTimeout
  globalThis.clearTimeout = realClearTimeout
  console.warn = realWarn
})

/** A fresh copy of the module (its backoff state starts empty). */
const load = () => import(`${pathToFileURL(join(dir, 'outbox.js')).href}?n=${counter++}`)

describe('outbox', () => {
  test('a 503 on the first attempt is queued with the key that was sent, and a flush is scheduled in 30 s', async () => {
    const { postOrQueue, pendingCount } = await load()
    script = [503]
    await assert.rejects(() => postOrQueue('/children/1/attempts', { correct: true }))
    assert.equal(pendingCount(), 1)
    assert.equal(queue()[0].key, calls[0].key)
    assert.ok(queue()[0].body.played_at)
    assert.equal(queue()[0].serverErrors, 1)
    assert.equal(timers.at(-1).ms, 30_000)
  })

  test('a 429 keeps the result and honours Retry-After; the same key is sent again until it succeeds', async () => {
    const { postOrQueue, flushOutbox, pendingCount } = await load()
    script = [503]
    await assert.rejects(() => postOrQueue('/x', {}))
    const key = queue()[0].key
    script = [[429, { 'retry-after': '7' }]]
    await flushOutbox()
    assert.equal(pendingCount(), 1)
    assert.equal(timers.at(-1).ms, 7000)
    assert.equal(queue()[0].serverErrors, 1, 'a 429 is not counted')
    script = ['ok']
    await flushOutbox()
    assert.equal(pendingCount(), 0)
    assert.deepEqual(calls.map(c => c.key), [key, key, key])
  })

  test('backoff doubles while it keeps failing and starts over after a success', async () => {
    const { postOrQueue, flushOutbox } = await load()
    script = ['net']
    await assert.rejects(() => postOrQueue('/x', {}))
    assert.equal(timers.at(-1).ms, 30_000)
    script = ['net']
    await flushOutbox()
    assert.equal(timers.at(-1).ms, 60_000)
    script = ['net']
    await flushOutbox()
    assert.equal(timers.at(-1).ms, 120_000)
    script = ['ok']
    await flushOutbox()
    script = [502]
    await assert.rejects(() => postOrQueue('/y', {}))
    assert.equal(timers.at(-1).ms, 30_000)
  })

  test('401 is kept in order, 422 and 403 are dropped', async () => {
    const { postOrQueue, flushOutbox, pendingCount } = await load()
    script = [401, 401, 401]
    await assert.rejects(() => postOrQueue('/a', {}))
    await assert.rejects(() => postOrQueue('/b', {}))
    await assert.rejects(() => postOrQueue('/c', {}))
    assert.deepEqual(queue().map(i => i.url), ['/a', '/b', '/c'])
    script = [422, 403, 'ok']
    await flushOutbox()
    assert.equal(pendingCount(), 0)
    assert.deepEqual(calls.slice(-3).map(c => c.url), ['/a', '/b', '/c'])
  })

  test('results queued by an older version get a key at flush time and keep it', async () => {
    const { flushOutbox, pendingCount } = await load()
    store.set(STORAGE, JSON.stringify([{ url: '/old', body: { a: 1 } }]))
    script = [503, 'ok']
    await flushOutbox()
    const key = queue()[0].key
    assert.ok(key)
    await flushOutbox()
    assert.equal(calls[0].key, key)
    assert.equal(calls[1].key, key)
    assert.equal(pendingCount(), 0)
  })

  test('a result queued while a flush is in flight is not overwritten', async () => {
    const { postOrQueue, flushOutbox, pendingCount } = await load()
    let release
    store.set(STORAGE, JSON.stringify([{ url: '/slow', body: {}, key: 'k1' }]))
    globalThis.__post = (url, body, options) => {
      calls.push({ url, key: options?.headers?.['Idempotency-Key'] })
      return url === '/slow' ? new Promise(resolve => (release = resolve)) : Promise.resolve({})
    }
    const flushing = flushOutbox()
    await new Promise(resolve => setImmediate(resolve))
    const normal = globalThis.__post
    globalThis.__post = async () => {
      throw fail(500)
    }
    await assert.rejects(() => postOrQueue('/during', {}))
    assert.deepEqual(queue().map(i => i.url), ['/slow', '/during'])
    globalThis.__post = normal
    release({})
    await flushing
    assert.equal(pendingCount(), 0)
    assert.equal(calls.filter(c => c.url === '/during').length, 1)
  })
})

describe('poison items', () => {
  test('after 5 server errors the item steps back so newer results get out', async () => {
    const { flushOutbox } = await load()
    store.set(
      STORAGE,
      JSON.stringify([
        { url: '/poison', body: {}, key: 'p', serverErrors: 0 },
        { url: '/newer', body: {}, key: 'n', serverErrors: 0 },
      ]),
    )
    for (let attempt = 1; attempt <= 4; attempt++) {
      script = [500]
      await flushOutbox()
      assert.deepEqual(queue().map(i => i.url), ['/poison', '/newer'], `attempt ${attempt}: still first`)
      assert.equal(queue()[0].serverErrors, attempt, 'the count is stored with it')
      assert.equal(calls.at(-1).url, '/poison')
    }
    calls = []
    script = [500, 'ok']
    await flushOutbox()
    assert.deepEqual(calls.map(c => c.url), ['/poison', '/newer'], 'the 5th failure moves on to the newer result in the same flush')
    assert.deepEqual(queue().map(i => i.url), ['/poison'])
    assert.equal(queue()[0].serverErrors, 5)
    assert.ok(timers.at(-1).ms > 0, 'and the poisoned one is tried again later')
  })

  test('it is dropped after 25 server errors, with a log line that names nothing', async () => {
    const { flushOutbox, pendingCount } = await load()
    store.set(STORAGE, JSON.stringify([{ url: '/children/42/attempts', body: { secret: 'x' }, key: 'p', serverErrors: 24 }]))
    script = [503]
    await flushOutbox()
    assert.equal(pendingCount(), 0)
    assert.equal(warnings.length, 1)
    assert.doesNotMatch(JSON.stringify(warnings), /42|secret|attempts/)
  })

  test('being offline, signed out, CSRF-expired or throttled never counts', async () => {
    const { flushOutbox } = await load()
    store.set(STORAGE, JSON.stringify([{ url: '/a', body: {}, key: 'a', serverErrors: 3 }]))
    for (const answer of ['net', 401, 419, 429, 408, 425, [429, { 'retry-after': '5' }]]) {
      script = [answer]
      await flushOutbox()
      assert.equal(queue()[0].serverErrors, 3, String(answer))
    }
    assert.equal(warnings.length, 0)
  })

  test('with the count at 4, a network error does not push it to the back', async () => {
    const { flushOutbox } = await load()
    store.set(
      STORAGE,
      JSON.stringify([
        { url: '/a', body: {}, key: 'a', serverErrors: 4 },
        { url: '/b', body: {}, key: 'b', serverErrors: 0 },
      ]),
    )
    script = ['net']
    await flushOutbox()
    assert.deepEqual(queue().map(i => i.url), ['/a', '/b'])
  })
})
