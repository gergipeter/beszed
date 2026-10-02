// The app's axios instance (resources/js/app/http.js): cookie session on the website, Bearer token in the native app.
// A fake adapter stands in for the network. Run: npm run test:js
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { AxiosError } from 'axios'
import { createTokenStore } from '../../resources/js/app/authToken.js'
import { createHttp, goesTo, onUnauthorized } from '../../resources/js/app/http.js'

const ORIGIN = 'https://beszed.example.hu'

/** An instance whose requests are answered by `answer(config)` (a status) and recorded. */
function setup({ native, token = null, answer = () => 200 }) {
  const store = createTokenStore({ get: async () => token, set: async () => {}, remove: async () => {} })
  const http = createHttp({ native, origin: ORIGIN, store })
  const seen = []
  http.defaults.adapter = async config => {
    seen.push(config)
    const status = answer(config)
    const response = { data: {}, status, statusText: '', headers: {}, config, request: {} }
    if (status >= 400) throw new AxiosError(`status ${status}`, 'ERR_BAD_REQUEST', config, {}, response)
    return response
  }
  return { http, store, seen }
}

const auth = config => config.headers.get('Authorization')

test('native: the token is sent as a Bearer header, and the request goes to the API server', async () => {
  const { http, seen } = setup({ native: true, token: '7|abc' })
  await http.get('/api/me')
  assert.equal(auth(seen[0]), 'Bearer 7|abc')
  assert.equal(seen[0].baseURL, ORIGIN)
  assert.equal(seen[0].url, '/api/me')
})

test('native: no cookies and no XSRF header', async () => {
  const { http, seen } = setup({ native: true, token: 't' })
  await http.post('/api/children', { name: 'Zoé' })
  assert.equal(seen[0].withCredentials, false)
  assert.equal(seen[0].withXSRFToken, false)
})

test('native: without a token no header is sent (sign-in, sign-up)', async () => {
  const { http, seen } = setup({ native: true })
  await http.post('/api/auth/login', { email: 'a@b.hu', password: 'x' })
  assert.equal(auth(seen[0]), undefined)
})

test('native: the module calls its own base URL on the API server and still carries the token', async () => {
  const { http, seen } = setup({ native: true, token: 't' })
  await http.get('/tts', { baseURL: `${ORIGIN}/api/beszed` })
  assert.equal(auth(seen[0]), 'Bearer t')
})

test('native: a request to another server never gets the token', async () => {
  const { http, seen } = setup({ native: true, token: 't' })
  await http.get('https://elsewhere.example.com/x')
  await http.get('/y', { baseURL: 'https://elsewhere.example.com' })
  await http.get(`${ORIGIN}.evil.example/z`)
  assert.deepEqual(seen.map(auth), [undefined, undefined, undefined])
})

test('native: the first request waits for the token to be read from the device', async () => {
  let release
  const store = createTokenStore({ get: () => new Promise(resolve => (release = () => resolve('late'))), set: async () => {}, remove: async () => {} })
  const http = createHttp({ native: true, origin: ORIGIN, store })
  const seen = []
  http.defaults.adapter = async config => (seen.push(config), { data: {}, status: 200, statusText: '', headers: {}, config, request: {} })
  const pending = http.get('/api/me')
  await new Promise(resolve => setTimeout(resolve, 5))
  assert.equal(seen.length, 0, 'not sent before the token is known')
  release()
  await pending
  assert.equal(auth(seen[0]), 'Bearer late')
})

test('native: a 401 on an ordinary request forgets the token', async () => {
  const { http, store } = setup({ native: true, token: 't', answer: () => 401 })
  await assert.rejects(http.get('/api/children'), error => error.response.status === 401)
  assert.equal(await store.get(), null)
})

test('native: a 401 on a quiet request (the boot check, a public share link) keeps the token', async () => {
  const { http, store } = setup({ native: true, token: 't', answer: () => 401 })
  await assert.rejects(http.get('/api/me', { quiet401: true }))
  assert.equal(await store.get(), 't')
})

test('native: other failures keep the token', async () => {
  for (const status of [403, 422, 429, 500]) {
    const { http, store } = setup({ native: true, token: 't', answer: () => status })
    await assert.rejects(http.get('/api/children'))
    assert.equal(await store.get(), 't', String(status))
  }
})

test('onUnauthorized: called on a 401, not on a quiet one, not on other errors', async () => {
  const { http } = setup({ native: true, token: 't', answer: config => (config.url === '/gone' ? 401 : 500) })
  let calls = 0
  onUnauthorized(() => calls++, http)
  await assert.rejects(http.get('/gone'))
  assert.equal(calls, 1)
  await assert.rejects(http.get('/gone', { quiet401: true }))
  await assert.rejects(http.get('/broken'))
  assert.equal(calls, 1)
})

test('website: cookie session and XSRF header, no Bearer token, same-origin paths', async () => {
  const { http, seen } = setup({ native: false, token: 'would-be-ignored' })
  await http.get('/api/me')
  assert.equal(auth(seen[0]), undefined)
  assert.equal(seen[0].withCredentials, true)
  assert.equal(seen[0].withXSRFToken, true)
  assert.equal(seen[0].baseURL, undefined)
  assert.equal(seen[0].url, '/api/me')
})

test('website: a 401 does not touch the token store', async () => {
  const { http, store } = setup({ native: false, token: 't', answer: () => 401 })
  await store.get()
  await assert.rejects(http.get('/api/children'))
  assert.equal(store.peek(), 't')
})

test('both: the same JSON and AJAX headers as before', async () => {
  for (const native of [true, false]) {
    const { http, seen } = setup({ native })
    await http.get('/api/me')
    assert.equal(seen[0].headers.get('Accept'), 'application/json')
    assert.equal(seen[0].headers.get('X-Requested-With'), 'XMLHttpRequest')
  }
})

test('goesTo: only the API server counts', () => {
  assert.equal(goesTo(ORIGIN, { baseURL: ORIGIN, url: '/api/me' }), true)
  assert.equal(goesTo(ORIGIN, { baseURL: `${ORIGIN}/api/beszed/`, url: 'tts' }), true)
  assert.equal(goesTo(ORIGIN, { url: `${ORIGIN}/api/me` }), true)
  assert.equal(goesTo(ORIGIN, { url: `${ORIGIN}?x=1` }), true)
  assert.equal(goesTo(ORIGIN, { baseURL: ORIGIN, url: 'https://other.example/x' }), false)
  assert.equal(goesTo(ORIGIN, { url: `${ORIGIN}.evil.example/x` }), false)
  assert.equal(goesTo(ORIGIN, { url: '/api/me' }), false, 'a relative URL with no base is the app bundle itself')
})
