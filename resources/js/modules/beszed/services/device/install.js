import { ref } from 'vue'

/**
 * "Install on the home screen": Csillám as an app, full screen, no browser bars.
 * Android/Chrome offers its own install dialog (beforeinstallprompt, caught
 * here as soon as this module loads); iPhone/iPad Safari has none, so the app
 * shows how to do it by hand (Share → Add to Home Screen). Nothing is offered
 * when the app already runs installed.
 */
let deferred = null
export const canPrompt = ref(false)

if (typeof window !== 'undefined') {
  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault() // no mini-infobar: the parents' button asks at the right moment
    deferred = event
    canPrompt.value = true
  })
  window.addEventListener('appinstalled', () => {
    deferred = null
    canPrompt.value = false
  })
}

export const isInstalled = () =>
  typeof window !== 'undefined' &&
  (window.matchMedia?.('(display-mode: standalone)').matches || window.matchMedia?.('(display-mode: fullscreen)').matches || navigator.standalone === true)

/** iPhone, iPod, or an iPad (which says it's a Mac, but has touch). */
export const isIos = () =>
  typeof navigator !== 'undefined' &&
  (/iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1))

/** Shows Chrome's install dialog; resolves true when the parent said yes. */
export async function promptInstall() {
  if (!deferred) return false
  const event = deferred
  deferred = null
  canPrompt.value = false
  event.prompt()
  const { outcome } = await event.userChoice
  return outcome === 'accepted'
}
