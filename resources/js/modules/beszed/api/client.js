import axios from 'axios'
import { config } from '../config/options.js'

let instance = null

/**
 * Use the host app's own axios instance (CSRF, interceptors, error toasts…).
 * Its `baseURL` is ignored for module calls; they always go to `config.apiBase`.
 */
export function setHttpClient(client) {
  instance = client
}

function client() {
  instance ??= axios.create({
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: 'application/json' },
  })
  return instance
}

const withBase = options => ({ ...options, baseURL: config.apiBase })

/** Thin wrapper that resolves to the response body. */
export const http = {
  get: (url, options) => client().get(url, withBase(options)).then(r => r.data),
  post: (url, body, options) => client().post(url, body, withBase(options)).then(r => r.data),
  put: (url, body, options) => client().put(url, body, withBase(options)).then(r => r.data),
  delete: (url, options) => client().delete(url, withBase(options)).then(r => r.data),
}

/** Absolute-path URL for things the browser loads itself (audio elements, prefetch). */
export const apiUrl = path => `${config.apiBase.replace(/\/$/, '')}/${path.replace(/^\//, '')}`

/**
 * Native app (`apiBase` names the API server): `url` on the API server's own origin. URLs the server builds name the
 * host it was asked on, and the app's Bearer token goes to the API server only.
 */
export function onApiOrigin(url) {
  if (!/^https?:\/\//i.test(config.apiBase)) return url
  const origin = new URL(config.apiBase).origin
  const { pathname, search } = new URL(url, origin)
  return origin + pathname + search
}

/** The bytes at `url`, fetched with the host's authenticated client (for audio, which an <audio> element cannot authenticate). */
export const fetchBlob = url => client().get(onApiOrigin(url), { responseType: 'blob' }).then(r => r.data)
