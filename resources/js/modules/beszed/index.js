import { setHttpClient } from './api/client'
import { configure } from './config/options'
import { createBeszedRoutes } from './router/routes'

export { createBeszedRoutes }
export { registerEngine } from './engines'

// UI kit, so host pages (sign-in, child picker) look like the module. They need
// the module styles too: import './modules/beszed/styles/index.css' and wrap in .bz.
export { default as BzButton } from './components/ui/BzButton.vue'
export { default as BzNotice } from './components/ui/BzNotice.vue'
export { default as CsillamAvatar } from './components/guide/CsillamAvatar.vue'
export { default as EmojiArt } from './components/ui/EmojiArt.vue'
export { default as SkillMap } from './components/progress/SkillMap.vue'

/** Default routes, for `createRouter({ routes: [...yourRoutes, ...beszedRoutes] })`. */
export const beszedRoutes = createBeszedRoutes()

/**
 * Optional Vue plugin; only needed to change defaults (see config/options.js).
 *
 *   app.use(beszed, {
 *     http: myAxios,                   // the host's configured axios instance
 *     router,                          // registers the routes for you
 *     routes: { path: '/beszed', props: route => ({ childName: '…' }) },
 *     exitTo: { name: 'children' },    // "Gyerekek" button on the hub
 *     guideName: 'Csillám',
 *     emoji: { baseUrl: '/vendor/twemoji/svg/' },
 *   })
 */
export default {
  install(app, { http, router, routes, ...options } = {}) {
    if (http) setHttpClient(http)
    configure(options)
    if (router) createBeszedRoutes(routes).forEach(route => router.addRoute(route))
  },
}
