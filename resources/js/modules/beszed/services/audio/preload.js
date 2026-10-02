import { config } from '../../config/options'
import { loadAudioBlob } from './blobCache'

/**
 * Warms the browser's HTTP cache with audio that is about to be played. For
 * server TTS this also makes the server synthesise the sentence ahead of time,
 * so the next round starts talking without waiting on the TTS API.
 * Responses are `Cache-Control: immutable`, so each URL is fetched once per visit.
 * In the native app (`config.blobAudio`) it fills the in-memory audio cache the player reads instead.
 */
const MAX_PARALLEL = 2

const requested = new Set()
const queue = []
let active = 0

function pump() {
  while (active < MAX_PARALLEL && queue.length) {
    const url = queue.shift()
    active++
    const fetched = config.blobAudio
      ? loadAudioBlob(url)
      : fetch(url, { credentials: 'include' }).then(res => res.ok && res.blob())
    fetched
      .catch(() => {})
      .finally(() => {
        active--
        pump()
      })
  }
}

/** Queues `urls` for fetching, skipping any requested before. */
export function preloadAudio(urls) {
  for (const url of urls) {
    if (!url || requested.has(url)) continue
    requested.add(url)
    queue.push(url)
  }
  pump()
}
