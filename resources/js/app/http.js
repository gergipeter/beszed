import axios from 'axios'

/**
 * The one axios instance of the app, shared with the Beszéd module. Cookie
 * session + XSRF header (Sanctum SPA auth).
 */
export const http = axios.create({
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

/** Calls `handler` whenever the server says the session is gone (401), except for requests marked `quiet401`. */
export function onUnauthorized(handler) {
  http.interceptors.response.use(undefined, error => {
    if (error.response?.status === 401 && !error.config?.quiet401) handler()
    return Promise.reject(error)
  })
}
