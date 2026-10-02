// The native app's token store (resources/js/app/authToken.js) with a fake secure storage. Run: npm run test:js
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createTokenStore, secureStorageOf } from '../../resources/js/app/authToken.js'

/** A device storage that counts what is asked of it. */
function fakeStorage(initial = {}) {
  const data = new Map(Object.entries(initial))
  const calls = { get: 0, set: 0, remove: 0 }
  return {
    data,
    calls,
    async get(key) {
      calls.get++
      return data.get(key) ?? null
    },
    async set(key, value) {
      calls.set++
      data.set(key, value)
    },
    async remove(key) {
      calls.remove++
      data.delete(key)
    },
  }
}

test('the token is read from the device on first use, then from memory', async () => {
  const storage = fakeStorage({ 'beszed-token': '7|abc' })
  const store = createTokenStore(storage)
  assert.equal(store.peek(), null, 'nothing is known before the device was asked')
  assert.equal(await store.get(), '7|abc')
  assert.equal(await store.get(), '7|abc')
  assert.equal(store.peek(), '7|abc')
  assert.equal(storage.calls.get, 1)
})

test('requests that start together share one read of the device', async () => {
  const storage = fakeStorage({ 'beszed-token': 't' })
  const store = createTokenStore(storage)
  assert.deepEqual(await Promise.all([store.get(), store.get(), store.get()]), ['t', 't', 't'])
  assert.equal(storage.calls.get, 1)
})

test('no token on the device is null', async () => {
  const store = createTokenStore(fakeStorage())
  assert.equal(await store.get(), null)
})

test('set keeps the token in memory and on the device', async () => {
  const storage = fakeStorage()
  const store = createTokenStore(storage)
  await store.set('9|xyz')
  assert.equal(await store.get(), '9|xyz')
  assert.equal(storage.data.get('beszed-token'), '9|xyz')
  assert.equal(storage.calls.get, 0, 'no need to read what was just saved')
})

test('a new store on the same device finds the saved token (the next app start)', async () => {
  const storage = fakeStorage()
  await createTokenStore(storage).set('9|xyz')
  assert.equal(await createTokenStore(storage).get(), '9|xyz')
})

test('clear forgets the token in memory and on the device', async () => {
  const storage = fakeStorage({ 'beszed-token': 't' })
  const store = createTokenStore(storage)
  await store.get()
  await store.clear()
  assert.equal(await store.get(), null)
  assert.equal(store.peek(), null)
  assert.equal(storage.data.has('beszed-token'), false)
})

test('setting an empty token is the same as clearing it', async () => {
  const storage = fakeStorage({ 'beszed-token': 't' })
  const store = createTokenStore(storage)
  await store.set('')
  assert.equal(await store.get(), null)
  assert.equal(storage.data.has('beszed-token'), false)
})

test('a token saved while the first read is still running wins over what the device said', async () => {
  let release
  const slow = {
    get: () => new Promise(resolve => (release = () => resolve('old'))),
    set: async () => {},
    remove: async () => {},
  }
  const store = createTokenStore(slow)
  const reading = store.get()
  await store.set('new')
  release()
  assert.equal(await reading, 'new')
  assert.equal(await store.get(), 'new')
})

test('a clear while the first read is still running also wins', async () => {
  let release
  const slow = { get: () => new Promise(resolve => (release = () => resolve('old'))), set: async () => {}, remove: async () => {} }
  const store = createTokenStore(slow)
  const reading = store.get()
  await store.clear()
  release()
  assert.equal(await reading, null)
})

test('a device that fails to read is just "no token"; a failed save keeps the token for this visit', async () => {
  const fail = async () => {
    throw new Error('keychain locked')
  }
  const store = createTokenStore({ get: fail, set: fail, remove: fail })
  assert.equal(await store.get(), null)
  await store.set('t')
  assert.equal(await store.get(), 't')
  await store.clear()
  assert.equal(await store.get(), null)
})

test('without any storage (the website, tests) the token lives in memory only', async () => {
  const store = createTokenStore()
  assert.equal(await store.get(), null)
  await store.set('t')
  assert.equal(await store.get(), 't')
})

test('useStorage plugs in the device storage before the first read', async () => {
  const store = createTokenStore()
  store.useStorage(fakeStorage({ 'beszed-token': 'from-device' }))
  assert.equal(await store.get(), 'from-device')
})

test('the secure-storage plugin is used with its string methods and a this-device-only keychain', async () => {
  const log = []
  const plugin = {
    getItem: async key => (log.push(['getItem', key]), 'kept'),
    setItem: async (key, value) => log.push(['setItem', key, value]),
    removeItem: async key => log.push(['removeItem', key]),
    setDefaultKeychainAccess: async access => log.push(['access', access]),
  }
  const storage = secureStorageOf(plugin, { whenUnlockedThisDeviceOnly: 1 })
  assert.equal(await storage.get('k'), 'kept')
  await storage.set('k', 'v')
  await storage.remove('k')
  assert.deepEqual(log, [['getItem', 'k'], ['access', 1], ['setItem', 'k', 'v'], ['removeItem', 'k']])
  assert.equal(await secureStorageOf({ getItem: async () => '' }, {}).get('k'), null, 'an empty string is no token')
})
