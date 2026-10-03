import { ref } from 'vue'
import catalogEn from './catalog.en'
import catalogEs from './catalog.es'
import en from './en'
import es from './es'
import hu from './hu'

const messages = { hu, en, es }
/** Names the server sends in Hungarian (games, folders, stickers…), in the other languages. */
const catalogs = { en: catalogEn, es: catalogEs }
const STORAGE_KEY = 'beszed.lang'

/** The languages offered by the switcher: American English and Latin American Spanish besides Hungarian. */
export const LANGUAGES = [
  { code: 'hu', name: 'Magyar', flag: '🇭🇺', html: 'hu' },
  { code: 'en', name: 'English (US)', flag: '🇺🇸', html: 'en-US' },
  { code: 'es', name: 'Español (Latinoamérica)', flag: '🌎', html: 'es-419' },
]
const htmlLang = code => LANGUAGES.find(l => l.code === code)?.html ?? code

/** First visit on this device: the browser's own language if we have it, else Hungarian. */
function browserLanguage() {
  try {
    const prefix = (navigator.language || '').slice(0, 2).toLowerCase()
    return prefix in messages ? prefix : null
  } catch {
    return null
  }
}

function readStoredLanguage() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    return stored in messages ? stored : null
  } catch {
    return null
  }
}

/** Reactive so `{{ t('x.y') }}` in a template re-renders when the language changes. */
const lang = ref(readStoredLanguage() ?? browserLanguage() ?? 'hu')

/** Screen readers, hyphenation and the browser's own translate offer follow <html lang>. */
function applyDocumentLanguage(code) {
  if (typeof document !== 'undefined') document.documentElement.lang = htmlLang(code)
}
applyDocumentLanguage(lang.value)

/**
 * Switches the active language and remembers it on this device. Any part of
 * the UI reading `t()`/`numberWord()`/`formatDate()` re-renders on its own
 * (they read the reactive `lang` ref); nothing else needs a page reload.
 */
export function setLanguage(code) {
  if (!(code in messages) || code === lang.value) return
  lang.value = code
  applyDocumentLanguage(code)
  try {
    localStorage.setItem(STORAGE_KEY, code)
  } catch {
    /* not kept on this device */
  }
}

export const currentLanguage = lang

/**
 * Interface texts produced in a language other than Hungarian. Csillám's voice is Hungarian (the language being
 * learned), so the guide asks here whether a text she is about to say is such an interface text, and says it with a voice
 * of that language instead of reading English or Spanish with Hungarian sounds.
 */
const interfaceTexts = new Set()
const remember = text => {
  if (lang.value === 'hu' || typeof text !== 'string' || !text) return text
  if (interfaceTexts.size > 3000) interfaceTexts.clear()
  interfaceTexts.add(text)
  return text
}
/** @returns {'en' | 'es' | null} the language to say `text` in, or null for Csillám's own Hungarian voice */
export const spokenLanguage = text => (lang.value !== 'hu' && interfaceTexts.has(text) ? lang.value : null)

/** A text in Hungarian whatever the interface language is (what Csillám says inside a game). */
export const tHu = key => key.split('.').reduce((node, part) => node?.[part], messages.hu) ?? key

/**
 * A server-provided name in the active language: `catalog('games', 'zs', 'name', fallback)`; the fallback (the
 * Hungarian original from the server) when the language is Hungarian or the catalog lacks it.
 */
export function catalog(kind, id, field, fallback) {
  const entry = catalogs[lang.value]?.[kind]?.[id]
  const value = field == null ? entry : entry?.[field]
  return typeof value === 'string' && value ? remember(value) : fallback
}

/**
 * Looks up a UI text by dotted key and fills its `{placeholders}`.
 * Deliberately tiny: a language is one file (hu.js, en.js, es.js) added to `messages` above.
 */
export function t(key, params) {
  const active = messages[lang.value]
  const text = key.split('.').reduce((node, part) => node?.[part], active)
  if (typeof text !== 'string') {
    if (import.meta.env?.DEV) console.warn(`[beszed] missing text: ${key}`)
    return key
  }
  if (!params) return remember(text)
  return remember(text.replace(/\{(\w+)\}/g, (match, name) => (name in params ? String(params[name]) : match)))
}

/** Number word ("két"/"two"…) for sentences built on the client. */
export const numberWord = n => messages[lang.value].numbers[n] ?? String(n)

export const formatDate = value => (value ? new Date(value).toLocaleDateString(messages[lang.value].dateLocale) : '–')

/** "2026-09-21" → "szept. 21." (a calendar day, no timezone shift). */
export const formatDay = (isoDay, options = { month: 'short', day: 'numeric' }) =>
  new Date(`${isoDay}T12:00:00`).toLocaleDateString(messages[lang.value].dateLocale, options)

export const formatPercent = value => (value == null ? '–' : `${Math.round(value * 100)}%`)
