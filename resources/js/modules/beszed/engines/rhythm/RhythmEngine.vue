<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { t } from '../../i18n'
import { tone } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'

/**
 * Ritmus: a pattern of short and long beats is played on the drum (it pulses);
 * then the child taps the same rhythm. data.pattern holds the gaps between
 * beats in beat units (1 short, 2 long); the last number is the last beat's own
 * length and is not needed. The child's taps are judged by their gaps relative
 * to the first one, so a slower or faster child still gets it right; the
 * tolerance is wider at level 1. A miss replays the pattern; after MAX_ERRORS the round is skipped.
 * data: RhythmData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const DRUM_HZ = 196
const MAX_ERRORS = 3
const START_MS = 500
const REPLAY_DELAY_MS = 1900
/** A pause this long between taps starts the child's try over. */
const RESET_MS = 3500

/** 'wait' (prompt) → 'watch' (the pattern plays) → 'play' (the child's turn) → 'done' */
const phase = ref('wait')
const pulse = ref(false)
const taps = ref([])
const errors = ref(0)
const { shaking, shake } = useShake()
const { later } = useTimers()
const wait = ms => new Promise(resolve => later(resolve, ms))
/** Bumped to stop a pattern that is still playing (replay, unmount). */
let run = 0

function beat(ms = 140) {
  pulse.value = true
  tone(DRUM_HZ, 0.16)
  later(() => (pulse.value = false), ms)
}

async function playPattern() {
  const mine = ++run
  phase.value = 'watch'
  taps.value = []
  await wait(START_MS)
  const { pattern, beatMs } = props.data
  for (const gap of pattern) {
    if (mine !== run) return
    beat()
    await wait(gap * beatMs)
  }
  if (mine === run) phase.value = 'play'
}

watch(
  () => props.promptDone,
  done => {
    if (done && !props.locked) playPattern()
    else if (!done) {
      run++
      phase.value = 'wait'
    }
  },
  { immediate: true },
)
onBeforeUnmount(() => run++)

/** Do the child's gaps match the pattern's, relative to the first gap? */
function matches(times) {
  const want = props.data.pattern.slice(0, -1)
  const got = times.slice(1).map((time, i) => time - times[i])
  if (got.length !== want.length) return false
  const tolerance = props.data.level === 1 ? 0.5 : props.data.level === 2 ? 0.4 : 0.32
  const unit = got.reduce((sum, g, i) => sum + g / want[i], 0) / got.length
  return got.every((g, i) => Math.abs(g / (want[i] * unit) - 1) <= tolerance)
}

function tap() {
  if (props.locked || phase.value === 'watch' || phase.value === 'done') return
  beat(110)
  if (phase.value !== 'play') return
  const now = performance.now()
  const last = taps.value.at(-1)
  if (last !== undefined && now - last > RESET_MS) taps.value = []
  taps.value = [...taps.value, now]
  if (taps.value.length < props.data.pattern.length) return

  if (matches(taps.value)) {
    phase.value = 'done'
    emit('answer', { correct: true, say: props.data.onCorrect })
    return
  }
  errors.value++
  shake('drum')
  phase.value = 'wait'
  if (errors.value >= MAX_ERRORS) {
    emit('skip', t('rhythm.tooHard'))
    return
  }
  emit('answer', { correct: false, say: t('rhythm.wrong') })
  later(() => emit('replay', props.data.replayParts), REPLAY_DELAY_MS)
}

function hearAgain() {
  if (props.locked || phase.value === 'watch') return
  playPattern()
}
</script>

<template>
  <p class="status" aria-live="polite">
    <template v-if="phase === 'watch'">{{ t('rhythm.watch') }}</template>
    <template v-else-if="phase === 'play'">{{ t('rhythm.yourTurn') }}</template>
    <template v-else>&nbsp;</template>
  </p>

  <ol class="dots" :aria-label="t('rhythm.progress', { done: taps.length, total: data.pattern.length })">
    <li
      v-for="(gap, i) in data.pattern"
      :key="i"
      class="dot"
      :class="{ 'dot--long': gap > 1, 'dot--done': i < taps.length }"
    />
  </ol>

  <button
    type="button"
    class="drum"
    :class="{ 'drum--pulse': pulse, 'drum--shake': shaking === 'drum' }"
    :aria-label="t('rhythm.drum')"
    data-no-feel
    @pointerdown.prevent="tap"
  >
    🥁
  </button>

  <button type="button" class="again" @click="hearAgain">🔊 {{ t('rhythm.again') }}</button>
</template>

<style scoped>
.status {
  min-height: 1.3em;
  margin: -6px 0 0;
  font-size: var(--bz-text-md);
  font-weight: 800;
  color: var(--bz-muted);
}
.dots {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.dot {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--bz-soft);
  transition: background 0.15s ease;
}
/* a long beat is drawn as a longer pill */
.dot--long {
  width: 44px;
  border-radius: 12px;
}
.dot--done {
  background: var(--bz-leaf);
}
.drum {
  display: grid;
  place-items: center;
  width: min(56vw, 34vh, 240px);
  aspect-ratio: 1;
  border-radius: 50%;
  background: color-mix(in srgb, var(--bz-coral) 45%, var(--bz-card));
  box-shadow: var(--bz-shadow-lg);
  font-size: min(24vw, 12vh, 100px);
  line-height: 1;
  touch-action: manipulation;
  transition: transform 0.08s ease;
}
.drum--pulse {
  transform: scale(1.08);
  background: color-mix(in srgb, var(--bz-sun) 60%, var(--bz-card));
}
.drum--shake {
  animation: shake 0.35s ease;
}
.again {
  padding: 8px 18px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-weight: 800;
  box-shadow: var(--bz-shadow-sm);
}
@keyframes shake {
  25% {
    transform: translateX(-8px);
  }
  75% {
    transform: translateX(8px);
  }
}
</style>
