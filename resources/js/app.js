import { createPinia } from 'pinia'
import { createApp, h } from 'vue'
import { RouterView } from 'vue-router'
import { http, onUnauthorized } from './app/http'
import { router } from './app/router'
import { useSessionStore } from './app/stores/session'
import beszed from './modules/beszed'

const app = createApp({ render: () => h(RouterView) })
const pinia = createPinia()

app.use(pinia)
// The games share the app's axios (session cookie, XSRF, 401 handling).
app.use(beszed, { http, exitTo: { name: 'children' } })
app.use(router)

// Session expired mid-game: back to sign-in.
onUnauthorized(() => {
  useSessionStore(pinia).signedOut()
  router.replace({ name: 'login' })
})

app.mount('#app')

// Offline play after the first visit (public/sw.js). Service workers need HTTPS (or localhost).
if (import.meta.env.PROD && 'serviceWorker' in navigator && window.isSecureContext) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}))
}
