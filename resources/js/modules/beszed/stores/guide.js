import { defineStore } from 'pinia'
import { ttsUrl } from '../api'
import { config } from '../config/options'
import { currentLanguage, spokenLanguage } from '../i18n'
import { playAudio, stopAudio, unlockAudio } from '../services/audio/player'
import { unlockSfx } from '../services/audio/sfx'
import { preloadAudio } from '../services/audio/preload'
import { cancelWebSpeech, primeWebSpeech, speakWebSpeech } from '../services/audio/webSpeech'
import { useMetaStore } from './meta'
import { useRecordingsStore } from './recordings'
import { useSettingsStore } from './settings'

/** Slows down speech for a "say it again, slower" replay. */
const SLOW_RATE_FACTOR = 0.7
/** The browser voice language for interface texts in each language. */
const VOICE_LANG = { en: 'en-US', es: 'es-419' }

/**
 * @typedef {'idle' | 'happy' | 'sad' | 'hop'} Mood
 *
 * Something Csillám says: plain text (server TTS, else Web Speech), or
 * `{ rec, alt }` = the parent's recording of line `rec`, else `alt` as text.
 * @typedef {string | { rec: string, alt?: string }} SpeakItem
 */

/** How long each mood's animation stays on. */
const MOOD_MS = { happy: 1800, sad: 1400, hop: 600 }

/** Bumped on every stop(); a speech whose token is stale has been interrupted. */
let token = 0
let moodTimer
let moodFrame

function cancelMood() {
  clearTimeout(moodTimer)
  cancelAnimationFrame(moodFrame)
}

/** The URL of the server voice for `text`, tied to the parent's voice settings so a changed voice isn't served from cache. */
function serverUrl(text) {
  const { voice, rate, pitch } = useSettingsStore()
  return ttsUrl(text, `${voice ?? ''}.${rate ?? 0}.${pitch ?? 0}`)
}

/** Where a SpeakItem's audio comes from: `{ url }` (recording) or `{ text }`. */
function sourceOf(item) {
  if (typeof item === 'string') return { text: item }
  const url = useRecordingsStore().url(item.rec)
  return url ? { url } : { text: item.alt }
}

/** Csillám: what she says, and how she looks while saying it. */
export const useGuideStore = defineStore('beszed/guide', {
  state: () => ({
    /** @type {Mood} */
    mood: 'idle',
    party: false,
    talking: false,
    caption: '',
    /** Turtle mode: everything Csillám says is slower, until the child turns it off or leaves the game. */
    slow: false,
    ttsFailures: 0,
  }),

  getters: {
    /** Server voice is on, hasn't failed too often, and the parent hasn't turned it off. */
    serverTts: state =>
      useMetaStore().serverTts && state.ttsFailures < config.ttsMaxFailures && useSettingsStore().preferServerTts,
  },

  actions: {
    /** Call from a tap so iOS allows audio and speech for the rest of the visit. */
    unlock() {
      unlockAudio()
      unlockSfx()
      primeWebSpeech(config.voice.lang)
      const settings = useSettingsStore()
      if (!settings.loaded) settings.load().catch(() => {})
    },

    stop() {
      token++
      stopAudio()
      cancelWebSpeech()
      this.talking = false
    },

    /** Silence, and back to a neutral look (leaving a game). */
    reset() {
      this.stop()
      cancelMood()
      this.mood = 'idle'
      this.party = false
      this.caption = ''
    },

    /**
     * Says `items` in order; interrupts anything already being said.
     * @param {SpeakItem[]} items
     * @param {{ caption?: string, slow?: boolean, lang?: 'en' | 'es' }} [options] `slow` says it at a reduced rate; `lang`: the
     *   items are interface texts in that language, said with the browser's voice for it (the server voice is Hungarian).
     * @returns {Promise<boolean>} true if everything was said, false if interrupted.
     */
    async speak(items, { caption, slow: slowOnce = false, lang = null } = {}) {
      this.stop()
      const slow = slowOnce || this.slow
      const mine = token
      const isCurrent = () => mine === token
      if (caption !== undefined) this.caption = caption
      this.talking = true
      const settings = useSettingsStore()
      const rate = (slow ? SLOW_RATE_FACTOR : 1) * (config.voice.rate + (settings.rate ?? 0) / 100)
      const pitch = config.voice.pitch + (settings.pitch ?? 0) / 100

      for (const item of items) {
        if (!isCurrent()) return false
        const { url, text } = sourceOf(item)
        if (url) {
          await playAudio(url, { rate: slow ? SLOW_RATE_FACTOR : 1 })
          continue
        }
        if (!text) continue

        // an interface text in English or Spanish is said with a voice of that language, not by the Hungarian server voice
        const voice = lang ?? spokenLanguage(text)
        let spoken = false
        if (this.serverTts && !voice) {
          spoken = await playAudio(serverUrl(text), { rate: slow ? SLOW_RATE_FACTOR : 1 })
          if (isCurrent()) this.ttsFailures = spoken ? 0 : this.ttsFailures + 1
        }
        if (!spoken && isCurrent()) await speakWebSpeech(text, { ...config.voice, ...(voice ? { lang: VOICE_LANG[voice] } : {}), rate, pitch, isCurrent })
      }

      if (isCurrent()) this.talking = false
      return isCurrent()
    },

    /** Says an interface text (not game content): in the language of the interface, whatever voice there is for it. */
    speakUi(items, options = {}) {
      const lang = currentLanguage.value
      return this.speak(items, { ...options, lang: lang === 'hu' ? null : lang })
    },

    /** Fetches the audio of `items` in the background, so it plays instantly later. */
    preload(items) {
      if (!config.preloadAudio) return
      const urls = items.map(sourceOf).map(({ url, text }) => url ?? (text && this.serverTts && !spokenLanguage(text) ? serverUrl(text) : null))
      preloadAudio(urls)
    },

    /** @param {Mood} mood @param {number} [ms] back to idle after this long */
    setMood(mood, ms) {
      cancelMood()
      this.mood = 'idle'
      // Next frame, so the same mood twice in a row restarts its animation.
      moodFrame = requestAnimationFrame(() => {
        this.mood = mood
        if (ms) moodTimer = setTimeout(() => (this.mood = 'idle'), ms)
      })
    },

    celebrate() {
      this.setMood('happy', MOOD_MS.happy)
    },

    comfort() {
      this.setMood('sad', MOOD_MS.sad)
    },

    hop() {
      this.setMood('hop', MOOD_MS.hop)
    },
  },
})
