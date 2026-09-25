import { setHttpClient } from './api/client'
import { configure } from './config/options'
import { createBeszedRoutes } from './router/routes'

export { createBeszedRoutes }
export { registerEngine } from './engines'

/** Default routes, for `createRouter({ routes: [...yourRoutes, ...beszedRoutes] })`. */
export const beszedRoutes = createBeszedRoutes()

/**
 * Optional Vue plugin; only needed to change defaults (see config/options.js).
 *
 *   app.use(beszed, {
 *     http: myAxios,                   // the host's configured axios instance
 *     router,                          // registers the routes for you
 *     routes: { path: '/beszed', props: route => ({ childName: '…' }) },
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
