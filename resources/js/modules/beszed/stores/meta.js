import { defineStore } from 'pinia'
import { fetchMeta } from '../api'

/** In-flight request, so parallel callers share one fetch. */
let pending = null

/** Game catalogue and module texts from `GET /meta`; loaded once per visit. */
export const useMetaStore = defineStore('beszed/meta', {
  state: () => ({
    /** @type {import('../types').Meta | null} */
    meta: null,
  }),

  getters: {
    loaded: state => state.meta !== null,
    games: state => state.meta?.games ?? [],
    lines: state => state.meta?.lines ?? [],
    serverTts: state => Boolean(state.meta?.serverTts),
    serverStt: state => Boolean(state.meta?.serverStt),
    game: state => id => state.meta?.games.find(g => g.id === id) ?? null,
    /** Built-in `praise` / `retry` sentences. */
    phrases: state => kind => state.meta?.[kind] ?? [],
  },

  actions: {
    load() {
      if (this.meta) return Promise.resolve(this.meta)
      pending ??= fetchMeta()
        .then(meta => (this.meta = meta))
        .finally(() => (pending = null))
      return pending
    },
  },
})
