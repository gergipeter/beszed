import { createPinia } from 'pinia'
import { createApp, h } from 'vue'
import { RouterView } from 'vue-router'
import { http, onUnauthorized } from './app/http'
import { apiUrl, isNative } from './app/native'
import { router } from './app/router'
import { useSessionStore } from './app/stores/session'
import beszed from './modules/beszed'

const app = createApp({ render: () => h(RouterView) })
const pinia = createPinia()
/** The iOS / Android app (Capacitor shell, docs/native-app.md): the page is a local bundle, the API is on another origin. */
const native = isNative()

app.use(pinia)
// The games share the app's axios (session cookie or Bearer token, XSRF, 401 handling).
app.use(beszed, {
  http,
  exitTo: { name: 'children' },
  apiBase: apiUrl('/api/beszed'),
  // Twemoji pictures shipped with the build (scripts/copy-emoji.mjs): the same look on every device.
  emoji: { baseUrl: native ? './emoji/' : '/build/emoji/' },
  ...(native && {
    // The bundle carries the emoji and Mulberry symbols (scripts/native-assets.mjs); ARASAAC pictograms and uploaded
    // pictures are public routes of the API server.
    symbols: { baseUrl: './symbols/' },
    pictograms: { baseUrl: apiUrl('/pictograms/') },
    contentImages: { baseUrl: apiUrl('/api/content-images/') },
    // An <audio> element cannot send the Bearer token: Csillám's voice and the recordings are fetched with it and played from memory.
    blobAudio: true,
  }),
  // Premium is bought in the native app (Apple / Google, via RevenueCat); the server confirms and sets the plan.
  billing: {
    info: () => http.get('/api/billing').then(r => r.data),
    sync: async () => {
      const { data } = await http.post('/api/billing/sync')
      await useSessionStore(pinia).load(true)
      return Boolean(data.premium)
    },
    termsTo: { name: 'terms' },
    privacyTo: { name: 'privacy' },
  },
})
app.use(router)

// Session expired mid-game: back to sign-in.
onUnauthorized(() => {
  useSessionStore(pinia).signedOut()
  router.replace({ name: 'login' })
})

app.mount('#app')

// Offline play after the first visit (public/sw.js). Service workers need HTTPS (or localhost). Not in the app: its files are already on the device.
// updateViaCache 'none': the update check also skips the HTTP cache for /build/sw-version.js, which tells a new build.
if (import.meta.env.PROD && !native && 'serviceWorker' in navigator && window.isSecureContext) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' }).catch(() => { }))
}
