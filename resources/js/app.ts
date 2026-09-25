import { createApp, h } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory, RouterView } from 'vue-router'
import { beszedRoutes } from './modules/beszed/routes'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    ...beszedRoutes,
    { path: '/:pathMatch(.*)*', redirect: '/beszed/1' },
  ],
})

createApp({ render: () => h(RouterView) })
  .use(createPinia())
  .use(router)
  .mount('#app')