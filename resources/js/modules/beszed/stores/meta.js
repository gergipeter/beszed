import { defineStore } from 'pinia'
import { fetchMeta } from '../api'
import { catalog, currentLanguage } from '../i18n'

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
    /** The games with their names in the active language (the server sends Hungarian). */
    games: state =>
      (currentLanguage.value, (state.meta?.games ?? []).map(g => ({
        ...g,
        name: catalog('games', g.id, 'name', g.name),
        skill: catalog('games', g.id, 'skill', g.skill),
        categories: (g.categories ?? []).map(c => ({ ...c, name: catalog('categories', c.id, null, c.name) })),
      }))),
    /** The hub folders, named in the active language. */
    folders: state =>
      (currentLanguage.value, (state.meta?.folders ?? []).map(f => ({
        ...f,
        name: catalog('folders', f.id, 'name', f.name),
        develops: catalog('folders', f.id, 'develops', f.develops),
      }))),
    lines: state => state.meta?.lines ?? [],
    serverTts: state => Boolean(state.meta?.serverTts),
    serverStt: state => Boolean(state.meta?.serverStt),
    game() {
      return id => this.games.find(g => g.id === id) ?? null
    },
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
