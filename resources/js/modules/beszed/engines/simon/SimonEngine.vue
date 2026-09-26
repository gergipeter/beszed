<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { t } from '../../i18n'
import { tone } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'

/**
 * Állatkórus (a Simon game): after the prompt the animals sing their notes one
 * after another, lighting up; then the child taps them in the same order. A
 * wrong tap replays the tune (via the prompt, so the replay button does the
 * same); after MAX_ERRORS the round is skipped so the level can drop.
 * data: SimonData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

/** Each pad's note (C major chord, C5–C6) and colour, by position, so both can be learnt. */
const NOTES = [523.25, 659.25, 783.99, 1046.5]
const COLORS = ['var(--bz-coral)', 'var(--bz-sun)', 'var(--bz-leaf)', 'var(--bz-chart)']
const SING_MS = 520
const GAP_MS = 220
const START_MS = 450
const MAX_ERRORS = 3
const REPLAY_DELAY_MS = 1900

/** 'wait' (prompt) → 'watch' (the tune plays) → 'play' (the child's turn) */
const phase = ref('wait')
const lit = ref(null)
const step = ref(0)
const errors = ref(0)
const { shaking, shake } = useShake()
const { later } = useTimers()
const wait = ms => new Promise(resolve => later(resolve, ms))
/** Bumped to stop a tune that is still playing (replay, unmount). */
let run = 0

function sing(id, ms) {
  const index = props.data.pads.findIndex(p => p.id === id)
  lit.value = id
  tone(NOTES[index % NOTES.length], ms / 1000)
  later(() => {
    if (lit.value === id) lit.value = null
  }, ms)
}

async function playTune() {
  const mine = ++run
  phase.value = 'watch'
  step.value = 0
  await wait(START_MS)
  for (const id of props.data.order) {
    if (mine !== run) return
    sing(id, SING_MS)
    await wait(SING_MS + GAP_MS)
  }
  if (mine === run) phase.value = 'play'
}

// The tune starts once Csillám has finished the prompt (also after a replay).
watch(
  () => props.promptDone,
  done => {
    if (done && !props.locked) playTune()
    else if (!done) {
      run++
      phase.value = 'wait'
    }
  },
  { immediate: true },
)
onBeforeUnmount(() => run++)

function tap(pad) {
  if (props.locked || phase.value !== 'play') return
  sing(pad.id, 260)
  if (pad.id === props.data.order[step.value]) {
    step.value++
    if (step.value === props.data.order.length) {
      phase.value = 'done'
      emit('answer', { correct: true, say: props.data.onCorrect })
    }
    return
  }

  errors.value++
  shake(pad.id)
  phase.value = 'wait'
  if (errors.value >= MAX_ERRORS) {
    emit('skip', t('simon.tooHard'))
    return
  }
  emit('answer', { correct: false, say: t('simon.wrong') })
  later(() => emit('replay', props.data.replayParts), REPLAY_DELAY_MS)
}
</script>

<template>
  <p class="status" aria-live="polite">
    <template v-if="phase === 'watch'">{{ t('simon.watch') }}</template>
    <template v-else-if="phase === 'play'">{{ t('simon.yourTurn') }}</template>
    <template v-else>&nbsp;</template>
  </p>

  <div class="pads">
    <button
      v-for="(pad, i) in data.pads"
      :key="pad.id"
      type="button"
      class="pad"
      :class="{ 'pad--lit': lit === pad.id, 'pad--shake': shaking === pad.id, 'pad--idle': phase !== 'play' }"
      :style="{ '--pad': COLORS[i % COLORS.length] }"
      :aria-label="pad.label"
      @click="tap(pad)"
    >
      <EmojiArt class="art" :char="pad.emoji" />
    </button>
  </div>

  <ol class="dots" :aria-label="t('simon.progress', { done: step, total: data.order.length })">
    <li v-for="(id, i) in data.order" :key="i" class="dot" :class="{ 'dot--done': i < step }" />
  </ol>
</template>

<style scoped>
.status {
  min-height: 1.3em;
  margin: -6px 0 0;
  font-size: var(--bz-text-md);
  font-weight: 800;
  color: var(--bz-muted);
}
.pads {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: clamp(12px, 4vw, 22px);
  width: 100%;
  max-width: 420px;
}
.pad {
  position: relative;
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  border-radius: 50%;
  background: color-mix(in srgb, var(--pad) 45%, var(--bz-card));
  box-shadow: var(--bz-shadow-lg);
  transition: transform 0.12s ease;
}
/* the glow is its own layer, so lighting up only animates opacity/transform */
.pad::after {
  content: '';
  position: absolute;
  inset: -8px;
  border-radius: 50%;
  background: radial-gradient(circle, color-mix(in srgb, var(--pad) 70%, #fff) 40%, transparent 72%);
  opacity: 0;
  transition: opacity 0.12s ease;
  pointer-events: none;
}
.pad--lit {
  transform: scale(1.08);
}
.pad--lit::after {
  opacity: 0.9;
}
.pad--idle {
  cursor: default;
}
.pad--shake {
  animation: shake 0.45s;
}
.art {
  position: relative;
  z-index: 1;
  font-size: clamp(54px, 16vw, 92px);
  line-height: 1;
}
.dots {
  display: flex;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--bz-guide) 35%, transparent);
  transition: transform 0.2s;
}
.dot--done {
  background: var(--bz-leaf);
  transform: scale(1.2);
}
@keyframes shake {
  20% {
    transform: translateX(-10px);
  }
  40% {
    transform: translateX(10px);
  }
  60% {
    transform: translateX(-6px);
  }
  80% {
    transform: translateX(6px);
  }
}
</style>
