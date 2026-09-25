import hu from './hu'

const messages = { hu }
const active = messages.hu

/**
 * Looks up a UI text by dotted key and fills its `{placeholders}`.
 * Deliberately tiny: if the module ever needs a second language, swap in
 * vue-i18n and keep the same keys.
 */
export function t(key, params) {
  const text = key.split('.').reduce((node, part) => node?.[part], active)
  if (typeof text !== 'string') {
    if (import.meta.env?.DEV) console.warn(`[beszed] missing text: ${key}`)
    return key
  }
  if (!params) return text
  return text.replace(/\{(\w+)\}/g, (match, name) => (name in params ? String(params[name]) : match))
}

/** Hungarian number word ("két", "három"…) for sentences built on the client. */
export const numberWord = n => active.numbers[n] ?? String(n)

export const formatDate = value => (value ? new Date(value).toLocaleDateString(active.dateLocale) : '–')

export const formatPercent = value => (value == null ? '–' : `${Math.round(value * 100)}%`)
