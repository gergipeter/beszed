import { defineStore } from 'pinia'
import { completeSession, fetchRewards, saveScene, wearAccessory } from '../api'
import { catalog, currentLanguage } from '../i18n'

/** Sticker, accessory and background names in the active language (the server sends Hungarian). */
const badge = b => ({ ...b, name: catalog('badges', b.id, 'name', b.name), hint: catalog('badges', b.id, 'hint', b.hint) })
const accessory = a => ({ ...a, name: catalog('accessories', a.id, null, a.name) })
const background = b => ({ ...b, name: catalog('backgrounds', b.id, null, b.name) })

/**
 * The current child's rewards: level, streak, daily goal, medals, stickers and
 * what Csillám wears. Rewards are a bonus layer, so loading never blocks a game.
 */
export const useRewardsStore = defineStore('beszed/rewards', {
  state: () => ({
    childId: null,
    /** @type {import('../types').RewardSummary | null} */
    summary: null,
  }),

  getters: {
    level: state => state.summary?.level ?? null,
    medal: state => gameId => state.summary?.medals?.[gameId] ?? 0,
    badges: state => (currentLanguage.value, (state.summary?.badges ?? []).map(badge)),
    earnedBadges() {
      return this.badges.filter(b => b.earned_at)
    },
    earnedCount() {
      return this.badges.filter(b => b.earned_at).length
    },
    /** The next present on the way (an accessory for Csillám), to look forward to. */
    nextGift: state => {
      const level = state.summary?.level?.number ?? 0
      return (currentLanguage.value, (state.summary?.accessories ?? []).filter(a => a.level > level).sort((a, b) => a.level - b.level).map(accessory)[0] ?? null)
    },
    accessories: state => (currentLanguage.value, (state.summary?.accessories ?? []).map(accessory)),
    worn: state => state.summary?.worn ?? {},
    scene: state => state.summary?.scene ?? { background: null, stickers: [] },
    backgrounds: state => (currentLanguage.value, (state.summary?.backgrounds ?? []).map(background)),
  },

  actions: {
    /** Loads `childId`'s rewards; resolves false (and keeps playing) if the server can't. */
    async load(childId) {
      if (childId !== this.childId) {
        this.childId = childId
        this.summary = null
      }
      try {
        const summary = await fetchRewards(childId)
        if (childId === this.childId) this.summary = summary
        return true
      } catch {
        return false
      }
    },

    /** Records a finished game; returns what it earned (level-up, stickers…). */
    async complete(childId, body) {
      const { result, ...summary } = await completeSession(childId, body)
      if (childId === this.childId) this.summary = summary
      // what this game just earned, named in the language it is shown in
      return {
        ...result,
        new_badges: (result.new_badges ?? []).map(badge),
        unlocked: (result.unlocked ?? []).map(accessory),
      }
    },

    async wear(childId, slot, accessory) {
      const summary = await wearAccessory(childId, slot, accessory)
      if (childId === this.childId) this.summary = summary
    },

    /** Persists the sticker scene (background + placed stickers). */
    async saveScene(childId, scene) {
      const saved = await saveScene(childId, scene)
      if (childId === this.childId) this.summary = { ...this.summary, scene: saved }
    },
  },
})
