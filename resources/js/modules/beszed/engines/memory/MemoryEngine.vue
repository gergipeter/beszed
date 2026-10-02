<script setup>
import { computed, reactive, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { beep } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'
import { useDevOverridesStore } from '../../stores/devOverrides'

/**
 * Memory (Párkereső): flip two cards; a pair stays open. Every flipped card
 * says its word, so the game also builds vocabulary. The win is graded by
 * mismatches relative to the number of pairs.
 * data: MemoryData - can include difficulty: 'easy' | 'medium' | 'hard'
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)
const devOverrides = useDevOverridesStore()

// Difficulty settings: affects flip-back timing and grading threshold
const DIFFICULTY_SETTINGS = {
  easy: { flipBackMs: 1400, mismatchThresholds: [999, 999] },
  medium: { flipBackMs: 1100, mismatchThresholds: [1, 2] },
  hard: { flipBackMs: 800, mismatchThresholds: [0, 1] },
}

const difficulty = computed(() => {
  // Override from dev panel if set
  if (devOverrides.state.memoryDifficulty) return devOverrides.state.memoryDifficulty
  return props.data.difficulty || 'medium'
})
const settings = computed(() => DIFFICULTY_SETTINGS[difficulty.value])
const FLIP_BACK_MS = computed(() => {
  const base = settings.value.flipBackMs
  return Math.round(base * devOverrides.state.flipBackMultiplier)
})
const WIN_DELAY_MS = 700

/** ids of the face-up, not yet matched cards (0–2) */
const open = ref([])
const matched = reactive(new Set())
let mismatches = 0
const { later } = useTimers()

const pairs = computed(() => props.data.cards.length / 2)
const columns = computed(() => (props.data.cards.length <= 6 ? 3 : 4))
const byId = computed(() => Object.fromEntries(props.data.cards.map(c => [c.id, c])))
const faceUp = card => matched.has(card.pair) || open.value.includes(card.id)
const grade = () => {
  const [t1, t2] = settings.value.mismatchThresholds
  return mismatches <= t1 ? 1 : mismatches <= t2 ? 2 : 3
}

function flip(card) {
  if (props.locked || open.value.length === 2 || faceUp(card)) return
  open.value = [...open.value, card.id]
  emit('say', card.label)
  if (open.value.length < 2) return

  const [a, b] = open.value.map(id => byId.value[id])
  if (a.pair === b.pair) {
    matched.add(a.pair)
    open.value = []
    beep(640, 0.1)
    if (matched.size === pairs.value) {
      later(() => emit('answer', { correct: true, say: props.data.onCorrect, tries: grade() }), WIN_DELAY_MS)
    }
    return
  }
  mismatches++
  later(() => (open.value = []), FLIP_BACK_MS.value)
}
</script>

<template>
  <div class="memory-container">
    <div class="difficulty-label">{{ t(`memory.${difficulty}`) }}</div>
    <div class="cards" :class="`cards--${columns}`">
    <button
      v-for="(card, i) in data.cards"
      :key="card.id"
      type="button"
      class="card"
      :class="{ 'card--up': faceUp(card), 'card--matched': matched.has(card.pair) }"
      :aria-label="faceUp(card) ? card.label : t('memory.card', { n: i + 1 })"
      @click="flip(card)"
    >
      <span class="inner">
        <span class="face face--back" aria-hidden="true"><EmojiArt :char="ICONS.sparkles" /></span>
        <span class="face face--front" aria-hidden="true">
          <EmojiArt class="art" :char="card.emoji" />
          <small class="label">{{ card.label }}</small>
        </span>
      </span>
    </button>
    </div>
  </div>
</template>

<style scoped>
/* the full width of the scene: the cards have no content of their own to size the grid by (the faces are
   absolutely placed), so a shrink-to-fit container squeezed them to a few dozen pixels */
.memory-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  width: 100%;
}

.difficulty-label {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--bz-muted);
  opacity: 0.8;
}

.cards {
  display: grid;
  gap: 10px;
  width: 100%;
  max-width: 600px;
}
.cards--3 {
  grid-template-columns: repeat(3, 1fr);
  max-width: 480px;
}
.cards--4 {
  grid-template-columns: repeat(4, 1fr);
}
.card {
  aspect-ratio: 3 / 4;
  perspective: 800px;
}
.inner {
  position: relative;
  display: block;
  width: 100%;
  height: 100%;
  transform-style: preserve-3d;
  transition: transform 0.4s cubic-bezier(0.3, 1.25, 0.5, 1);
  will-change: transform;
}
.card--up .inner {
  transform: rotateY(180deg);
}
.face {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  border-radius: var(--bz-radius);
  -webkit-backface-visibility: hidden; /* iOS Safari */
  backface-visibility: hidden;
  box-shadow: var(--bz-shadow);
}
.face--back {
  background:
    radial-gradient(circle at 30% 25%, rgba(255, 255, 255, 0.55), transparent 45%),
    linear-gradient(135deg, color-mix(in srgb, var(--bz-guide) 75%, #fff), color-mix(in srgb, var(--bz-coral) 55%, #fff));
  font-size: clamp(32px, 9vw, 52px);
}
.face--front {
  background: var(--bz-card);
  transform: rotateY(180deg);
}
.art {
  font-size: clamp(38px, 11vw, 68px);
  line-height: 1.05;
}
.label {
  font-size: clamp(14px, 3.4vw, 17px);
  font-weight: 700;
  color: var(--bz-muted);
}
.card--matched .face--front {
  background: color-mix(in srgb, var(--bz-leaf) 16%, var(--bz-card));
  box-shadow: inset 0 0 0 3px var(--bz-leaf), var(--bz-shadow);
}
</style>
