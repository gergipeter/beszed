import silentWav from '../../assets/audio/silent.wav'
import { config } from '../../config/options'
import { loadAudioBlob } from './blobCache'

/**
 * One shared <audio> element for everything Csillám says. iOS only lets an
 * element play after it has been started from a tap, so we keep reusing the one
 * that `unlockAudio()` blessed.
 */
let element = null
/** Settles the playback in progress; set while something is playing. */
let settle = null
let unlocked = false
let volume = 1

function audio() {
  if (!element) {
    element = new Audio()
    element.volume = volume
  }
  return element
}

/** 0–1; applied immediately, including to whatever is playing right now. */
export function setVolume(v) {
  volume = Math.min(1, Math.max(0, v))
  if (element) element.volume = volume
}

/**
 * Plays `url`. Resolves true when it ends, false on error, when the browser
 * refuses, or when interrupted by another `playAudio()` / `stopAudio()`.
 * @param {string} url
 * @param {{ rate?: number }} [options] Playback rate, e.g. 0.7 for a slower replay.
 * @returns {Promise<boolean>}
 */
export function playAudio(url, { rate = 1 } = {}) {
  settle?.(false)
  const el = audio()

  return new Promise(resolve => {
    const finish = ok => {
      if (settle !== finish) return
      settle = null
      clearTimeout(timeout)
      el.removeEventListener('ended', onEnded)
      el.removeEventListener('error', onError)
      resolve(ok)
    }
    const onEnded = () => finish(true)
    const onError = () => finish(false)
    // Some browsers never fire `ended` for broken streams; never hang the game on that.
    const timeout = setTimeout(() => finish(true), config.timing.playbackTimeoutMs)

    const begin = src => {
      if (settle !== finish) return // interrupted while the audio was being fetched
      // A new src reloads the element, which resets playbackRate to the default: set it after, and as the default too.
      el.defaultPlaybackRate = rate
      el.src = src
      el.playbackRate = rate
      el.play().then(
        () => (unlocked = true),
        () => finish(false),
      )
    }

    settle = finish
    el.addEventListener('ended', onEnded)
    el.addEventListener('error', onError)
    // Native app: fetched with the Bearer token and played from memory (the same element, so it stays unlocked).
    if (config.blobAudio) loadAudioBlob(url).then(begin, () => finish(false))
    else begin(url)
  })
}

export function stopAudio() {
  settle?.(false)
  element?.pause()
}

/** Call from inside a tap. Harmless to call again; does nothing once unlocked. */
export function unlockAudio() {
  const el = audio()
  // Already playing means it was started from a gesture: nothing to do, and don't cut it off.
  if (unlocked || !el.paused) return
  el.src = silentWav
  el.play().then(
    () => (unlocked = true),
    () => {},
  )
}
