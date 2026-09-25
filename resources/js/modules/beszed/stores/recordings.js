import { defineStore } from 'pinia'
import { deleteRecording, fetchRecordings, uploadRecording } from '../api'

let pending = null

/** The parent's own-voice lines. Csillám plays these instead of TTS. */
export const useRecordingsStore = defineStore('beszed/recordings', {
  state: () => ({
    /** line_key → audio URL */
    urls: /** @type {Record<string, string>} */ ({}),
    loaded: false,
  }),

  getters: {
    url: state => key => state.urls[key] ?? null,
    has: state => key => Object.hasOwn(state.urls, key),
    keysStartingWith: state => prefix => Object.keys(state.urls).filter(k => k.startsWith(prefix)),
  },

  actions: {
    /** Never rejects: without recordings Csillám simply uses the synthetic voice. */
    load() {
      if (this.loaded) return Promise.resolve()
      pending ??= fetchRecordings()
        .then(urls => {
          this.urls = urls
          this.loaded = true
        })
        .catch(() => {})
        .finally(() => (pending = null))
      return pending
    },

    async upload(key, blob, filename) {
      const { url } = await uploadRecording(key, blob, filename)
      this.urls = { ...this.urls, [key]: url }
    },

    async remove(key) {
      await deleteRecording(key)
      const { [key]: _removed, ...rest } = this.urls
      this.urls = rest
    },
  },
})
