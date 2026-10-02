import { onScopeDispose, ref } from 'vue'

/** Local hour → the garden's time of day. */
export function daytimeAt(hour) {
  if (hour >= 5 && hour < 9) return 'morning'
  if (hour >= 9 && hour < 17) return 'day'
  if (hour >= 17 && hour < 20) return 'evening'
  return 'night'
}

/** The browser's top bar follows the sky (Android Chrome, installed app). */
const BAR_COLOR = { morning: '#a6d8ff', day: '#7cc8ff', evening: '#c4b8f8', night: '#0f1538' }

/**
 * Time of day for the garden scene, from the child's clock; checked every few
 * minutes. Dark mode is always night, so the scene matches the rest of the UI.
 */
export function useDaytime() {
  const dark = typeof window !== 'undefined' ? window.matchMedia?.('(prefers-color-scheme: dark)') : null
  const now = () => (dark?.matches ? 'night' : daytimeAt(new Date().getHours()))
  const daytime = ref(now())

  const update = () => {
    daytime.value = now()
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', BAR_COLOR[daytime.value])
  }
  update()
  const timer = setInterval(update, 5 * 60 * 1000)
  dark?.addEventListener?.('change', update)
  onScopeDispose(() => {
    clearInterval(timer)
    dark?.removeEventListener?.('change', update)
  })

  return daytime
}
