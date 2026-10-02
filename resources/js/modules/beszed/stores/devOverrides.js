import { defineStore } from 'pinia'
import { reactive } from 'vue'

/**
 * Development/testing overrides for game customization.
 * Allows manual control of difficulty, player level, stars, etc.
 * These are client-side only and reset on page reload.
 */
export const useDevOverridesStore = defineStore('beszed/dev-overrides', () => {
  const state = reactive({
    // Difficulty override for memory game
    memoryDifficulty: null, // 'easy', 'medium', 'hard', or null for auto
    // Multiplier for flip-back times
    flipBackMultiplier: 1,
    // Instantly win after N correct answers
    autoWinAfter: null, // number or null
    // Add stars to next game
    starBonus: 0,
    // Show debug info
    showDebug: false,
  })

  function setMemoryDifficulty(difficulty) {
    state.memoryDifficulty = difficulty
  }

  function setFlipBackMultiplier(multiplier) {
    state.flipBackMultiplier = Math.max(0.1, multiplier)
  }

  function setAutoWinAfter(count) {
    state.autoWinAfter = count
  }

  function setStarBonus(bonus) {
    state.starBonus = Math.max(0, bonus)
  }

  function toggleDebug() {
    state.showDebug = !state.showDebug
  }

  function reset() {
    state.memoryDifficulty = null
    state.flipBackMultiplier = 1
    state.autoWinAfter = null
    state.starBonus = 0
    state.showDebug = false
  }

  return {
    state,
    setMemoryDifficulty,
    setFlipBackMultiplier,
    setAutoWinAfter,
    setStarBonus,
    toggleDebug,
    reset,
  }
})
