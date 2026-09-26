import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Calls `onIdle` when nothing is tapped for `ms`. Any tap on the page restarts
 * the countdown; `arm()` restarts it manually, `cancel()` stops it.
 * `onFidget` (optional) fires once first, partway through the wait (`fidgetAt`,
 * default 0.5 of `ms`), so Csillám seems alive before the full help kicks in.
 * @param {{ ms: number, enabled: () => boolean, onIdle: () => void, onFidget?: () => void, fidgetAt?: number }} options
 */
export function useIdleHelp({ ms, enabled, onIdle, onFidget, fidgetAt = 0.5 }) {
  let timer
  let fidgetTimer

  function cancel() {
    clearTimeout(timer)
    clearTimeout(fidgetTimer)
  }

  function arm() {
    cancel()
    if (!enabled()) return
    timer = setTimeout(onIdle, ms)
    if (onFidget) fidgetTimer = setTimeout(onFidget, ms * fidgetAt)
  }

  onMounted(() => document.addEventListener('pointerdown', arm))
  onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', arm)
    cancel()
  })

  return { arm, cancel }
}
