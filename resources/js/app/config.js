/**
 * Server-provided settings, rendered into the page by SpaController
 * (<script id="app-config" type="application/json">).
 */
const node = typeof document !== 'undefined' ? document.getElementById('app-config') : null

export const appConfig = {
  name: 'Beszéd & DIFER',
  auth: { google: false, demo: false, email: true },
  ...(node ? JSON.parse(node.textContent || '{}') : {}),
}
