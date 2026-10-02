// The native app's in-memory audio cache (modules/beszed/services/audio/blobCache.js) and the URL helper it uses.
// Run: npm run test:js
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { onApiOrigin } from '../../resources/js/modules/beszed/api/client.js'
import { configure } from '../../resources/js/modules/beszed/config/options.js'
import { createBlobCache } from '../../resources/js/modules/beszed/services/audio/blobCache.js'

/** A cache whose "network" and object URLs are fakes that keep a log. */
function setup({ max, fail = () => false } = {}) {
  const fetched = []
  const revoked = []
  let made = 0
  const cache = createBlobCache({
    max,
    fetchBlob: async url => {
      fetched.push(url)
      if (fail(url)) throw new Error('offline')
      return { url }
    },
    createUrl: blob => `blob:${++made}:${blob.url}`,
    revokeUrl: url => revoked.push(url),
  })
  return { cache, fetched, revoked }
}

test('the audio is fetched once and then comes from memory', async () => {
  const { cache, fetched } = setup()
  const first = await cache.load('https://s/tts?t=a')
  const again = await cache.load('https://s/tts?t=a')
  assert.equal(first, again)
  assert.ok(first.startsWith('blob:'))
  assert.deepEqual(fetched, ['https://s/tts?t=a'])
})

test('a fetch that is still running is shared (the preloader and the player)', async () => {
  const { cache, fetched } = setup()
  const [a, b] = await Promise.all([cache.load('u'), cache.load('u')])
  assert.equal(a, b)
  assert.equal(fetched.length, 1)
})

test('different URLs are different entries (a re-recorded line has a new ?v=)', async () => {
  const { cache, fetched } = setup()
  const old = await cache.load('https://s/rec/1/audio?v=1')
  const fresh = await cache.load('https://s/rec/1/audio?v=2')
  assert.notEqual(old, fresh)
  assert.equal(fetched.length, 2)
})

test('the least recently used audio goes first, and its object URL is revoked', async () => {
  const { cache, fetched, revoked } = setup({ max: 2 })
  const a = await cache.load('a')
  await cache.load('b')
  await cache.load('a') // a is the freshest now
  await cache.load('c') // b is evicted
  assert.equal(cache.size, 2)
  assert.equal(revoked.length, 1)
  assert.ok(revoked[0].endsWith(':b'), 'b was the least recently used')
  assert.equal(await cache.load('a'), a, 'a is still cached')
  assert.deepEqual(fetched, ['a', 'b', 'c'])
  await cache.load('b')
  assert.deepEqual(fetched, ['a', 'b', 'c', 'b'], 'an evicted one is fetched again')
})

test('the one that was just asked for is never the one evicted', async () => {
  const { cache, revoked } = setup({ max: 1 })
  await cache.load('a')
  const b = await cache.load('b')
  assert.equal(cache.size, 1)
  assert.equal(revoked.length, 1)
  assert.ok(!revoked.includes(b))
})

test('clear revokes everything (sign-out: the recordings are the family\'s own voices)', async () => {
  const { cache, revoked } = setup()
  await cache.load('a')
  await cache.load('b')
  cache.clear()
  assert.equal(cache.size, 0)
  await new Promise(resolve => setTimeout(resolve)) // the revoking follows the (already settled) fetches
  assert.equal(revoked.length, 2)
})

test('a failed fetch rejects and is not remembered', async () => {
  let offline = true
  const { cache, fetched } = setup({ fail: () => offline })
  await assert.rejects(cache.load('a'), /offline/)
  assert.equal(cache.size, 0)
  offline = false
  assert.ok((await cache.load('a')).startsWith('blob:'))
  assert.equal(fetched.length, 2)
})

test('onApiOrigin: the website keeps URLs as they are', () => {
  configure({ apiBase: '/api/beszed' })
  assert.equal(onApiOrigin('/api/beszed/tts?t=a'), '/api/beszed/tts?t=a')
})

test('onApiOrigin: in the app every server-made URL is moved to the API server', () => {
  configure({ apiBase: 'https://beszed.example.hu/api/beszed' })
  assert.equal(onApiOrigin('https://beszed.example.hu/api/beszed/recordings/x/audio?v=5'), 'https://beszed.example.hu/api/beszed/recordings/x/audio?v=5')
  assert.equal(onApiOrigin('http://internal-host:8080/api/beszed/recordings/x/audio?v=5'), 'https://beszed.example.hu/api/beszed/recordings/x/audio?v=5')
  assert.equal(onApiOrigin('/api/beszed/tts?t=a%20b'), 'https://beszed.example.hu/api/beszed/tts?t=a%20b')
  configure({ apiBase: '/api/beszed' })
})
