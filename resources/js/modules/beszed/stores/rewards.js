import { defineStore } from 'pinia'
import { completeSession, fetchRewards, saveScene, wearAccessory } from '../api'

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
    badges: state => state.summary?.badges ?? [],
    earnedBadges: state => (state.summary?.badges ?? []).filter(b => b.earned_at),
    earnedCount: state => (state.summary?.badges ?? []).filter(b => b.earned_at).length,
    accessories: state => state.summary?.accessories ?? [],
    worn: state => state.summary?.worn ?? {},
    scene: state => state.summary?.scene ?? { background: null, stickers: [] },
    backgrounds: state => state.summary?.backgrounds ?? [],
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
      return result
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
