<script setup>
import { computed, ref, watch } from 'vue'
import { useModuleContext } from '../../composables/useModuleContext'
import { useRewardsStore } from '../../stores/rewards'
import { useDevOverridesStore } from '../../stores/devOverrides'
import { t } from '../../i18n'
import BzButton from '../ui/BzButton.vue'
import BzNotice from '../ui/BzNotice.vue'

const { childId } = useModuleContext()
const rewards = useRewardsStore()
const devOverrides = useDevOverridesStore()

const show = ref(false)
const currentDifficulty = ref('medium')
const currentLevel = ref(5)
const currentStars = ref(0)
const flipBackMult = ref(1)
const autoWinCount = ref(null)
const mockSession = ref({
  correct: true,
  tries: 3,
})

const player = computed(() => rewards.player)
const difficultyOptions = ['easy', 'medium', 'hard']
const levels = Array.from({ length: 10 }, (_, i) => i + 1)

// Sync with dev overrides store
watch(() => devOverrides.state.memoryDifficulty, (val) => {
  if (val) currentDifficulty.value = val
}, { immediate: true })

watch(currentDifficulty, (val) => {
  devOverrides.setMemoryDifficulty(val === 'medium' ? null : val)
})

watch(flipBackMult, (val) => {
  devOverrides.setFlipBackMultiplier(val)
})

watch(autoWinCount, (val) => {
  devOverrides.setAutoWinAfter(val)
})

function togglePanel() {
  show.value = !show.value
}

async function setPlayerLevel() {
  if (!player.value) return
  localStorage.setItem(`dev-level-${childId.value}`, currentLevel.value)
  alert(`Level set to ${currentLevel.value}. Reload to apply.`)
}

async function addStars() {
  if (!player.value) return
  localStorage.setItem(`dev-stars-${childId.value}`, currentStars.value)
  alert(`Stars set to ${currentStars.value}. Reload to apply.`)
}

function copyDifficultySetting() {
  const setting = `difficulty: '${currentDifficulty.value}'`
  navigator.clipboard.writeText(setting)
  alert('Copied to clipboard: ' + setting)
}
</script>

<template>
  <div class="dev-root">
    <button class="dev-toggle" @click="togglePanel" :title="show ? 'Hide dev panel' : 'Show dev panel'">
      ⚙️ DEV
    </button>

    <div v-if="show" class="dev-panel">
      <h3>🎮 Game Dev Mode</h3>

      <!-- Difficulty Control -->
      <section class="dev-section">
        <h4>Difficulty Level (Párkereső)</h4>
        <div class="control-group">
          <select v-model="currentDifficulty" class="select">
            <option value="easy">easy ({{ t('memory.easy') }})</option>
            <option value="medium">medium ({{ t('memory.medium') }})</option>
            <option value="hard">hard ({{ t('memory.hard') }})</option>
          </select>
        </div>
        <p class="hint">🔴 Active: {{ devOverrides.state.memoryDifficulty || 'auto (by level)' }}</p>
      </section>

      <!-- Player Level Control -->
      <section class="dev-section">
        <h4>Player Level</h4>
        <div class="control-group">
          <select v-model.number="currentLevel" class="select">
            <option v-for="lvl in levels" :key="lvl" :value="lvl">
              Level {{ lvl }}
            </option>
          </select>
          <button @click="setPlayerLevel" class="btn-primary">Set Level</button>
        </div>
        <p class="hint">Current: Level {{ player?.level ?? '?' }} | Stars: {{ player?.stars ?? '?' }}</p>
      </section>

      <!-- Stars Control -->
      <section class="dev-section">
        <h4>Stars (XP)</h4>
        <div class="control-group">
          <input v-model.number="currentStars" type="number" min="0" max="999" class="input" />
          <button @click="addStars" class="btn-primary">Set Stars</button>
        </div>
        <p class="hint">Stars needed per level: n=5·n·(n-1)</p>
      </section>

      <!-- Game Timing -->
      <section class="dev-section">
        <h4>Game Timing</h4>
        <label class="row-label">
          <span>Flip-back Multiplier</span>
          <input v-model.number="flipBackMult" type="range" min="0.1" max="3" step="0.1" class="slider" />
          <code>{{ flipBackMult.toFixed(1) }}x</code>
        </label>
        <p class="hint">
          Easy: {{ (1400 * flipBackMult).toFixed(0) }}ms |
          Medium: {{ (1100 * flipBackMult).toFixed(0) }}ms |
          Hard: {{ (800 * flipBackMult).toFixed(0) }}ms
        </p>
      </section>

      <!-- Cheats -->
      <section class="dev-section">
        <h4>Game Cheats</h4>
        <label class="row-label">
          <span>Auto-win after correct</span>
          <input v-model.number="autoWinCount" type="number" min="1" max="20" placeholder="None" class="input" />
        </label>
        <p class="hint">Automatically finish after N correct answers</p>
      </section>

      <!-- Info -->
      <section class="dev-section">
        <h4>Debug Info</h4>
        <code class="debug-info">
          childId: {{ childId }}<br />
          level: {{ player?.level }}<br />
          stars: {{ player?.stars }}<br />
          streak: {{ player?.streak }}<br />
          sessions: {{ player?.sessions }}
        </code>
      </section>

      <button @click="togglePanel" class="btn-close">Close Panel</button>
    </div>
  </div>
</template>

<style scoped>
.dev-root {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 9999;
}

.dev-toggle {
  padding: 8px 12px;
  border: 2px solid #666;
  border-radius: 8px;
  background: #222;
  color: #0f0;
  font-weight: bold;
  cursor: pointer;
  font-size: 12px;
  transition: all 0.2s;
}

.dev-toggle:hover {
  background: #333;
  border-color: #0f0;
}

.dev-panel {
  position: fixed;
  bottom: 70px;
  right: 20px;
  width: 320px;
  max-height: 600px;
  overflow-y: auto;
  padding: 16px;
  border: 2px solid #0f0;
  border-radius: 8px;
  background: #1a1a1a;
  color: #0f0;
  font-family: 'Courier New', monospace;
  font-size: 12px;
  box-shadow: 0 0 20px rgba(0, 255, 0, 0.2);
}

.dev-panel h3 {
  margin: 0 0 16px;
  font-size: 14px;
  text-transform: uppercase;
  border-bottom: 2px solid #0f0;
  padding-bottom: 8px;
}

.dev-panel h4 {
  margin: 0 0 8px;
  font-size: 12px;
  text-transform: uppercase;
  color: #00ff00;
}

.dev-section {
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #333;
}

.dev-section:last-child {
  border-bottom: none;
}

.control-group {
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
}

.select,
.input {
  flex: 1;
  padding: 6px 8px;
  border: 1px solid #0f0;
  border-radius: 4px;
  background: #0a0a0a;
  color: #0f0;
  font-family: inherit;
  font-size: 11px;
}

.select:focus,
.input:focus {
  outline: none;
  border-color: #0f0;
  box-shadow: 0 0 8px rgba(0, 255, 0, 0.3);
}

.btn-primary,
.btn-secondary {
  padding: 6px 12px;
  border: 1px solid #0f0;
  border-radius: 4px;
  background: #0a0a0a;
  color: #0f0;
  font-family: inherit;
  font-size: 11px;
  cursor: pointer;
  font-weight: bold;
  white-space: nowrap;
}

.btn-primary:hover {
  background: #0f0;
  color: #000;
}

.btn-secondary:hover {
  background: #333;
}

.btn-close {
  width: 100%;
  margin-top: 12px;
  padding: 8px;
  border: 1px solid #0f0;
  background: #0a0a0a;
  color: #0f0;
  cursor: pointer;
  font-weight: bold;
  border-radius: 4px;
}

.btn-close:hover {
  background: #0f0;
  color: #000;
}

.hint {
  margin: 6px 0 0;
  font-size: 10px;
  color: #888;
  font-style: italic;
}

.params {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.params label {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
}

.params code {
  background: #0a0a0a;
  padding: 2px 6px;
  border-radius: 3px;
  color: #0f0;
}

.debug-info {
  display: block;
  padding: 8px;
  background: #0a0a0a;
  border: 1px solid #333;
  border-radius: 4px;
  line-height: 1.6;
  font-size: 10px;
}

label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  margin-bottom: 8px;
}

label input[type='checkbox'] {
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.row-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  margin-bottom: 8px;
}

.slider {
  flex: 0 1 80px;
  height: 16px;
  cursor: pointer;
}

.slider::-webkit-slider-thumb {
  background: #0f0;
  border-radius: 2px;
}

.slider::-moz-range-thumb {
  background: #0f0;
  border-radius: 2px;
}

code {
  background: #0a0a0a;
  padding: 2px 6px;
  border-radius: 3px;
  color: #0f0;
}
</style>
