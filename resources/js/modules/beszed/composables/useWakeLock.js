import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Keeps the screen on while a game is played: a child listening to Csillám
 * doesn't touch the screen for a while, and a phone that dims and locks
 * mid-round spoils the game. Taken again when the app comes back to the
 * front (the browser drops it when hidden); released when the game closes.
 * Where the browser has no Wake Lock, nothing happens.
 */
export function useWakeLock() {
  let lock = null
  let active = true

  async function take() {
    if (!active || document.visibilityState !== 'visible' || !navigator.wakeLock) return
    try {
      lock = await navigator.wakeLock.request('screen')
    } catch {
      /* low battery, or not allowed here */
    }
  }
  const onVisibility = () => document.visibilityState === 'visible' && take()

  onMounted(() => {
    take()
    document.addEventListener('visibilitychange', onVisibility)
  })
  onBeforeUnmount(() => {
    active = false
    document.removeEventListener('visibilitychange', onVisibility)
    lock?.release().catch(() => {})
    lock = null
  })
}
