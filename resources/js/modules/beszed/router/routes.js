const toId = value => Number(value)

/**
 * Route records for the module: one layout route with the pages nested under it.
 * Route names (beszed.hub, beszed.play…) are stable; use them for links.
 *
 * @param {object} [options]
 * @param {string} [options.path]  URL prefix, default "/beszed".
 * @param {(route: import('vue-router').RouteLocationNormalized) => { childName?: string, guideName?: string }} [options.props]
 *   Extra layout props from the host app, e.g. the child's name from your own store.
 * @returns {import('vue-router').RouteRecordRaw[]}
 */
export function createBeszedRoutes({ path = '/beszed', props } = {}) {
  return [
    {
      path: `${path}/:childId(\\d+)`,
      component: () => import('../layouts/BeszedLayout.vue'),
      props: route => ({ ...props?.(route), childId: toId(route.params.childId) }),
      children: [
        { path: '', name: 'beszed.hub', component: () => import('../pages/HubPage.vue') },
        { path: 'hang', name: 'beszed.recordings', component: () => import('../pages/RecordingsPage.vue') },
        { path: 'haladas', name: 'beszed.progress', component: () => import('../pages/ProgressPage.vue') },
        { path: 'matricak', name: 'beszed.rewards', component: () => import('../pages/RewardsPage.vue') },
        { path: 'beallitasok', name: 'beszed.settings', component: () => import('../pages/SettingsPage.vue') },
        {
          path: 'jatek/:game',
          name: 'beszed.play',
          component: () => import('../pages/PlayPage.vue'),
          props: route => ({ game: String(route.params.game) }),
        },
      ],
    },
  ]
}
