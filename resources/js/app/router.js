import { createRouter, createWebHashHistory, createWebHistory } from 'vue-router'
import { createBeszedRoutes } from '../modules/beszed'
import { appConfig } from './config'
import { isNative } from './native'
import { useSessionStore } from './stores/session'

/** Local development without Google set up: sign straight in as the demo parent. */
const autoDemo = appConfig.auth.demo && !appConfig.auth.google

export const router = createRouter({
  // The app is a bundle of static files, with no server to answer /gyerekek: its pages live after the #.
  history: isNative() ? createWebHashHistory() : createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('./pages/LoginPage.vue'), meta: { guest: true } },
    { path: '/jelszo-visszaallitas', name: 'reset-password', component: () => import('./pages/ResetPasswordPage.vue'), meta: { public: true } },
    { path: '/felhasznalasi-feltetelek', name: 'terms', component: () => import('./pages/TermsPage.vue'), meta: { public: true } },
    { path: '/adatvedelem', name: 'privacy', component: () => import('./pages/PrivacyPage.vue'), meta: { public: true } },
    { path: '/suti-tajekoztato', name: 'cookies', component: () => import('./pages/CookiePage.vue'), meta: { public: true } },
    { path: '/impresszum', name: 'imprint', component: () => import('./pages/ImprintPage.vue'), meta: { public: true } },
    // A therapist's read-only report: no sign-in, the link is the key.
    { path: '/megosztas/:token', name: 'share', component: () => import('./pages/SharePage.vue'), meta: { public: true } },
    { path: '/tartalom', name: 'content', component: () => import('./pages/ContentPage.vue'), meta: { editor: true } },
    { path: '/hozzajarulas', name: 'consent', component: () => import('./pages/ConsentPage.vue') },
    { path: '/gyerekek', name: 'children', component: () => import('./pages/ChildrenPage.vue') },
    // Never rendered: the guard sends "/" to the right place.
    { path: '/', name: 'home', component: { render: () => null } },
    ...createBeszedRoutes({
      props: route => {
        const child = useSessionStore().child(route.params.childId)
        return { childName: child?.name ?? '', childSign: child?.sign_emoji ?? '', premium: Boolean(useSessionStore().user?.premium), demo: Boolean(useSessionStore().user?.demo) }
      },
    }),
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async to => {
  if (to.meta.public) return true

  const session = useSessionStore()
  await session.load()

  if (!session.user && autoDemo && !session.unreachable && !session.loggedOut) {
    await session.demoLogin().catch(() => {})
  }

  if (to.meta.guest) return session.user ? { name: 'home' } : true
  if (!session.user) return { name: 'login' }

  // Nothing is stored about a child before the parent has consented.
  if (session.consentRequired) return to.name === 'consent' ? true : { name: 'consent' }
  if (to.name === 'consent') return { name: 'home' }

  if (to.name === 'home') {
    const [only, ...rest] = session.children
    return only && !rest.length ? { name: 'beszed.hub', params: { childId: only.id } } : { name: 'children' }
  }

  if (to.meta.editor && !session.user.can_edit_content) return { name: 'home' }

  // Someone else's (or a deleted) child in the URL.
  if (to.params.childId && !session.child(to.params.childId)) return { name: 'children' }
  return true
})
