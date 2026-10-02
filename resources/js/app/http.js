import axios from 'axios'
import { tokens } from './authToken.js'
import { apiOrigin, isNative } from './native.js'

/** Is this request going to the API server? (The token must never go anywhere else.) */
export function goesTo(origin, { baseURL, url }) {
  const path = url ?? ''
  const target = /^https?:\/\//i.test(path) || !baseURL ? path : `${baseURL.replace(/\/+$/, '')}/${path.replace(/^\/+/, '')}`
  return target === origin || target.startsWith(`${origin}/`) || target.startsWith(`${origin}?`)
}

/**
 * The axios instance of the app.
 *
 * Website: cookie session + XSRF header (Sanctum SPA auth), same origin.
 * Native app: the page's own origin is the app bundle, so requests go to the API server (`origin`) with no cookies and
 * no XSRF; the signed-in parent is identified by `Authorization: Bearer <token>` from the secure token store, and a
 * 401 on an ordinary request forgets that token.
 */
export function createHttp({ native = isNative(), origin = apiOrigin, store = tokens } = {}) {
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  const instance = native
    ? axios.create({ baseURL: origin, withCredentials: false, withXSRFToken: false, headers })
    : axios.create({ withCredentials: true, withXSRFToken: true, headers })

  if (native) {
    // The token is read from the device once (the first request waits for it), then comes from memory.
    instance.interceptors.request.use(async config => {
      const token = await store.get()
      if (token && goesTo(origin, config)) config.headers.set('Authorization', `Bearer ${token}`)
      return config
    })
    instance.interceptors.response.use(undefined, async error => {
      if (error.response?.status === 401 && !error.config?.quiet401) await store.clear()
      throw error
    })
  }
  return instance
}

/**
 * The one axios instance of the app, shared with the Beszéd module.
 */
export const http = createHttp()

/** Calls `handler` whenever the server says the session is gone (401), except for requests marked `quiet401`. */
export function onUnauthorized(handler, instance = http) {
  instance.interceptors.response.use(undefined, error => {
    if (error.response?.status === 401 && !error.config?.quiet401) handler()
    return Promise.reject(error)
  })
}
