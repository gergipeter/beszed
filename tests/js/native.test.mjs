// Where the app runs and where the API is (resources/js/app/native.js). Run: npm run test:js
import assert from 'node:assert/strict'
import { afterEach, test } from 'node:test'
import { apiOrigin, createApiUrl, deviceName, isNative, nativePlatform } from '../../resources/js/app/native.js'

afterEach(() => {
  delete globalThis.window
})

const capacitor = (platform, native = true) => ({ isNativePlatform: () => native, getPlatform: () => platform })

test('in a plain browser (or node) the app is not native and the origin is empty', () => {
  assert.equal(isNative(), false)
  assert.equal(nativePlatform(), 'web')
  assert.equal(apiOrigin, '')
})

test('a web page that has Capacitor but not as a native platform is the website', () => {
  globalThis.window = { Capacitor: capacitor('web', false) }
  assert.equal(isNative(), false)
})

test('inside the Capacitor shell the app is native, and says which platform', () => {
  globalThis.window = { Capacitor: capacitor('ios') }
  assert.equal(isNative(), true)
  assert.equal(nativePlatform(), 'ios')
  assert.equal(deviceName(), 'Csillám (ios)')
})

test('the website keeps every path as it is (same origin, cookie session)', () => {
  const apiUrl = createApiUrl({ native: () => false, origin: 'https://beszed.example.hu' })
  assert.equal(apiUrl('/api/me'), '/api/me')
  assert.equal(apiUrl('/api/beszed/tts?t=a%20b'), '/api/beszed/tts?t=a%20b')
})

test('the app puts the API server in front of the path', () => {
  const apiUrl = createApiUrl({ native: () => true, origin: 'https://beszed.example.hu' })
  assert.equal(apiUrl('/api/me'), 'https://beszed.example.hu/api/me')
  assert.equal(apiUrl('/pictograms/'), 'https://beszed.example.hu/pictograms/')
  assert.equal(apiUrl('api/me'), 'https://beszed.example.hu/api/me')
})

test('the native check is made when the URL is made, not when the module loads', () => {
  let native = false
  const apiUrl = createApiUrl({ native: () => native, origin: 'https://beszed.example.hu' })
  assert.equal(apiUrl('/api/me'), '/api/me')
  native = true
  assert.equal(apiUrl('/api/me'), 'https://beszed.example.hu/api/me')
})

test('without an origin even the app keeps relative paths (nothing to prefix)', () => {
  assert.equal(createApiUrl({ native: () => true, origin: '' })('/api/me'), '/api/me')
})
