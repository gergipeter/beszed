import { fetchBlob } from '../../api/client.js'

/**
 * Native app: audio that needs the Bearer token (the server voice, the parent's recordings) is fetched through the
 * authenticated client and played from an object URL (`config.blobAudio`). An <audio> element cannot send the header.
 *
 * Cached by URL (a sentence's audio never changes; a re-recorded line has a new `?v=`), least recently used out first,
 * and every evicted or cleared object URL is revoked so the memory goes back. A failed fetch is not remembered.
 * Fetches that are still running are shared, so the preloader and the player never download one URL twice.
 *
 * @param {{ fetchBlob: (url: string) => Promise<Blob>, createUrl?: (blob: Blob) => string, revokeUrl?: (url: string) => void, max?: number }} options
 */
export function createBlobCache({ fetchBlob: fetchOne, createUrl = blob => URL.createObjectURL(blob), revokeUrl = url => URL.revokeObjectURL(url), max = 60 }) {
  /** url → Promise<object URL>; a Map iterates oldest first, so re-inserting on use keeps the order "least recently used first" */
  const entries = new Map()

  const evict = () => {
    while (entries.size > max) {
      const [oldest, pending] = entries.entries().next().value
      entries.delete(oldest)
      pending.then(revokeUrl, () => {})
    }
  }

  return {
    /** The object URL of the audio at `url`. Rejects when it cannot be fetched. */
    load(url) {
      let pending = entries.get(url)
      if (pending) {
        entries.delete(url)
      } else {
        pending = fetchOne(url).then(createUrl)
        pending.catch(() => {
          if (entries.get(url) === pending) entries.delete(url)
        })
      }
      entries.set(url, pending)
      evict()
      return pending
    },

    /** Forgets everything (sign-out: the recordings are the family's own voices). */
    clear() {
      for (const pending of entries.values()) pending.then(revokeUrl, () => {})
      entries.clear()
    },

    get size() {
      return entries.size
    },
  }
}

const cache = createBlobCache({ fetchBlob })

/** @returns {Promise<string>} something an <audio> element can play: the audio at `url`, from memory */
export const loadAudioBlob = url => cache.load(url)

export const clearAudioCache = () => cache.clear()
