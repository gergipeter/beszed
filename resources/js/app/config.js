import { isNative } from './native'

/**
 * Server-provided settings, rendered into the page by SpaController
 * (<script id="app-config" type="application/json">). The native app has no server-rendered page: the same node is
 * written into resources/native/index.html at build time (vite.native.config.js).
 */
const node = typeof document !== 'undefined' ? document.getElementById('app-config') : null

export const appConfig = {
  name: 'Beszéd & DIFER',
  auth: { google: false, demo: false, email: true },
  ...(node ? JSON.parse(node.textContent || '{}') : {}),
}

// Inside the app only the app's own e-mail sign-in is offered: Google refuses sign-in in an embedded web view
// (and Apple 4.8 is met because no third-party sign-in is offered), and the demo parent is a local development aid.
if (isNative()) appConfig.auth = { google: false, demo: false, email: true }
