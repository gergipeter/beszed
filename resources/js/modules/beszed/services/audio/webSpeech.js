import { sentences } from '../../utils/text'

/** Browser Web Speech: the last-resort voice when there's no recording and no server TTS. */
const synth = typeof window !== 'undefined' && 'speechSynthesis' in window ? window.speechSynthesis : null

const QUALITY = /enhanced|premium|továbbfejlesztett|prémium|natural|neural|online|google/i

/** undefined = not chosen yet; null = no voice for the language (browser default). */
let cachedVoice
let primed = false

// Chrome loads its voice list asynchronously; choose again once it arrives.
synth?.addEventListener?.('voiceschanged', () => (cachedVoice = undefined))

function voiceFor(lang) {
  if (cachedVoice !== undefined) return cachedVoice
  const prefix = lang.slice(0, 2).toLowerCase()
  const rank = v => (QUALITY.test(v.name) ? 10 : 0) + (v.localService ? 0 : 1)
  const voices = synth.getVoices().filter(v => v.lang.toLowerCase().startsWith(prefix))
  cachedVoice = voices.sort((a, b) => rank(b) - rank(a))[0] ?? null
  return cachedVoice
}

/**
 * Speaks `text` sentence by sentence; stops early as soon as `isCurrent()` is false.
 * @param {string} text
 * @param {{ lang: string, rate: number, pitch: number, isCurrent: () => boolean }} options
 * @returns {Promise<void>}
 */
export function speakWebSpeech(text, { lang, rate, pitch, isCurrent }) {
  return new Promise(resolve => {
    if (!synth) return resolve()
    const parts = sentences(text)
    const voice = voiceFor(lang)
    let i = 0

    const next = () => {
      if (!isCurrent() || i >= parts.length) return resolve()
      const part = parts[i++]
      const utterance = new SpeechSynthesisUtterance(part)
      utterance.lang = lang
      utterance.rate = rate
      utterance.pitch = pitch
      if (voice) utterance.voice = voice

      let done = false
      const finish = () => {
        if (done) return
        done = true
        clearTimeout(timeout)
        next()
      }
      // Some engines never fire `end`; estimate the length instead of hanging.
      const timeout = setTimeout(finish, part.length * 130 + 2500)
      utterance.onend = utterance.onerror = finish
      synth.speak(utterance)
    }
    next()
  })
}

export function cancelWebSpeech() {
  try {
    synth?.cancel()
  } catch {
    /* no Web Speech */
  }
}

/** iOS only lets speech start from a tap: say nothing, once, inside one. */
export function primeWebSpeech(lang) {
  if (primed || !synth || synth.speaking) return
  primed = true
  const utterance = new SpeechSynthesisUtterance(' ')
  utterance.lang = lang
  utterance.volume = 0
  synth.speak(utterance)
}
