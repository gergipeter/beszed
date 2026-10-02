/**
 * Where the app runs: the website (same origin as the API, cookie session) or the Capacitor shell of the iOS / Android
 * app (its own origin, Bearer token). See docs/native-app.md.
 *
 * Capacitor puts `window.Capacitor` in the page before any script runs, so the check needs no import and the website
 * bundle carries no Capacitor code.
 */

/** Running inside the iOS / Android app. */
export const isNative = () => typeof window !== 'undefined' && Boolean(window.Capacitor?.isNativePlatform?.())

/** 'ios' | 'android' | 'web' */
export const nativePlatform = () => (typeof window !== 'undefined' && window.Capacitor?.getPlatform?.()) || 'web'

/** The API server of the native build (`VITE_API_ORIGIN`, see .env.native.example), without a trailing slash. Empty on the web. */
export const apiOrigin = String(import.meta.env?.VITE_API_ORIGIN ?? '').replace(/\/+$/, '')

/** `path` ('/api/me') on the API server: unchanged on the web, with the server's origin in front inside the app. */
export function createApiUrl({ native, origin }) {
  return path => (native() && origin ? `${origin}${path.startsWith('/') ? '' : '/'}${path}` : path)
}

export const apiUrl = createApiUrl({ native: isNative, origin: apiOrigin })

/** Names the token in the parent's device list on the server ("Csillám (ios)"). */
export const deviceName = () => `Csillám (${nativePlatform()})`
