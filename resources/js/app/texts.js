import { currentLanguage } from '../modules/beszed/i18n'
import en from './texts.en'
import hu from './texts.hu'

/**
 * Texts of the app shell (sign-in, child picker, therapist view) in the language the games use
 * (modules/beszed/i18n: the Settings switcher, remembered on the device). The games' own texts live there.
 *
 * `texts.x` reads the active language when it is used, so a template re-renders when the language changes
 * and the call sites need no `t()` wrapper. Keys are the same in texts.hu.js and texts.en.js.
 */
const byLanguage = { hu, en }
export const texts = new Proxy(hu, {
  get: (_, key) => (byLanguage[currentLanguage.value] ?? hu)[key],
})

/** The server's first validation message (Laravel 422), else the fallback. */
export const firstError = (e, fallback) => {
  const errors = e?.response?.data?.errors
  const first = errors ? Object.values(errors)[0]?.[0] : null
  return first || e?.response?.data?.message || fallback
}

export const fill = (text, params) => text.replace(/\{(\w+)\}/g, (m, k) => (k in params ? params[k] : m))
