import { ref } from 'vue'
import en from './en'
import hu from './hu'

const messages = { hu, en }
const STORAGE_KEY = 'beszed.lang'

function readStoredLanguage() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    return stored in messages ? stored : null
  } catch {
    return null
  }
}

/** Reactive so `{{ t('x.y') }}` in a template re-renders when the language changes. */
const lang = ref(readStoredLanguage() ?? 'hu')

/**
 * Switches the active language and remembers it on this device. Any part of
 * the UI reading `t()`/`numberWord()`/`formatDate()` re-renders on its own
 * (they read the reactive `lang` ref); nothing else needs a page reload.
 */
export function setLanguage(code) {
  if (!(code in messages) || code === lang.value) return
  lang.value = code
  try {
    localStorage.setItem(STORAGE_KEY, code)
  } catch {
    /* not kept on this device */
  }
}

export const currentLanguage = lang

/**
 * Looks up a UI text by dotted key and fills its `{placeholders}`.
 * Deliberately tiny: hu.js and en.js are the only two languages; a third
 * language is just another file added to `messages` above.
 */
export function t(key, params) {
  const active = messages[lang.value]
  const text = key.split('.').reduce((node, part) => node?.[part], active)
  if (typeof text !== 'string') {
    if (import.meta.env?.DEV) console.warn(`[beszed] missing text: ${key}`)
    return key
  }
  if (!params) return text
  return text.replace(/\{(\w+)\}/g, (match, name) => (name in params ? String(params[name]) : match))
}

/** Number word ("két"/"two"…) for sentences built on the client. */
export const numberWord = n => messages[lang.value].numbers[n] ?? String(n)

export const formatDate = value => (value ? new Date(value).toLocaleDateString(messages[lang.value].dateLocale) : '–')

/** "2026-09-21" → "szept. 21." (a calendar day, no timezone shift). */
export const formatDay = (isoDay, options = { month: 'short', day: 'numeric' }) =>
  new Date(`${isoDay}T12:00:00`).toLocaleDateString(messages[lang.value].dateLocale, options)

export const formatPercent = value => (value == null ? '–' : `${Math.round(value * 100)}%`)
