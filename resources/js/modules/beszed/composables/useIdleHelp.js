import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Calls `onIdle` when nothing is tapped for `ms`. Any tap on the page restarts
 * the countdown; `arm()` restarts it manually, `cancel()` stops it.
 * @param {{ ms: number, enabled: () => boolean, onIdle: () => void }} options
 */
export function useIdleHelp({ ms, enabled, onIdle }) {
  let timer

  function cancel() {
    clearTimeout(timer)
  }

  function arm() {
    cancel()
    if (enabled()) timer = setTimeout(onIdle, ms)
  }

  onMounted(() => document.addEventListener('pointerdown', arm))
  onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', arm)
    cancel()
  })

  return { arm, cancel }
}
