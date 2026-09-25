import { defineStore } from 'pinia'
import { ttsUrl } from '../api'
import { config } from '../config/options'
import { playAudio, stopAudio, unlockAudio } from '../services/audio/player'
import { preloadAudio } from '../services/audio/preload'
import { cancelWebSpeech, primeWebSpeech, speakWebSpeech } from '../services/audio/webSpeech'
import { useMetaStore } from './meta'
import { useRecordingsStore } from './recordings'

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
    ttsFailures: 0,
  }),

  getters: {
    /** Server voice is on, and hasn't failed too often in a row this visit. */
    serverTts: state => useMetaStore().serverTts && state.ttsFailures < config.ttsMaxFailures,
  },

  actions: {
    /** Call from a tap so iOS allows audio and speech for the rest of the visit. */
    unlock() {
      unlockAudio()
      primeWebSpeech(config.voice.lang)
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
     * @param {{ caption?: string }} [options]
     * @returns {Promise<boolean>} true if everything was said, false if interrupted.
     */
    async speak(items, { caption } = {}) {
      this.stop()
      const mine = token
      const isCurrent = () => mine === token
      if (caption !== undefined) this.caption = caption
      this.talking = true

      for (const item of items) {
        if (!isCurrent()) return false
        const { url, text } = sourceOf(item)
        if (url) {
          await playAudio(url)
          continue
        }
        if (!text) continue

        let spoken = false
        if (this.serverTts) {
          spoken = await playAudio(ttsUrl(text))
          if (isCurrent()) this.ttsFailures = spoken ? 0 : this.ttsFailures + 1
        }
        if (!spoken && isCurrent()) await speakWebSpeech(text, { ...config.voice, isCurrent })
      }

      if (isCurrent()) this.talking = false
      return isCurrent()
    },

    /** Fetches the audio of `items` in the background, so it plays instantly later. */
    preload(items) {
      if (!config.preloadAudio) return
      const urls = items.map(sourceOf).map(({ url, text }) => url ?? (text && this.serverTts ? ttsUrl(text) : null))
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
