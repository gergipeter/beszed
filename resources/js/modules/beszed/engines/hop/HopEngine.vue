<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { t } from '../../i18n'
import { tone } from '../../services/audio/sfx'
import { buzz } from '../../services/touch/feel'
import { engineEmits, engineProps } from '../contract'

/**
 * Béka ugrál: an animal hops along a number line of numbered lily pads.
 *   hop   the child taps "Ugrás" once per hop (forward or back), then "Kész"
 *   land  the animal hops by itself; the child taps the pad it lands on
 * Every pad is a note of the C major scale, so the number line sounds like a scale.
 * A wrong answer puts the animal back at its start; after MAX_ERRORS the round is skipped.
 * data: HopData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const SEMITONES = [0, 2, 4, 5, 7, 9, 11]
const MAX_ERRORS = 3
const HOP_MS = 560
const REPLAY_DELAY_MS = 1900

const max = computed(() => props.data.max)
const dir = computed(() => Math.sign(props.data.moves[0]))
const end = computed(() => props.data.start + props.data.moves.reduce((a, b) => a + b, 0))

/** The pads' places, in % of the pond: one row up to 5, two rows up to 10. */
function place(i) {
  if (max.value <= 5) return { x: 9 + i * (82 / max.value), y: 60 }
  return i <= 5 ? { x: 9 + i * 16.4, y: 30 } : { x: 17.2 + (i - 6) * 16.4, y: 76 }
}
const pads = computed(() => Array.from({ length: max.value + 1 }, (_, i) => ({ n: i, ...place(i) })))

const freq = n => 261.63 * 2 ** ((SEMITONES[n % 7] + 12 * Math.floor(n / 7)) / 12)

const pos = ref(props.data.start)
const hops = ref(0)
/** 'wait' → 'watch' (it hops by itself) → 'play' → 'done' */
const phase = ref(props.data.mode === 'hop' ? 'play' : 'wait')
const busy = ref(false)
const splash = ref(null)
const cheer = ref(false)
const errors = ref(0)
const frog = ref(null)
const { shaking, shake } = useShake()
const { later } = useTimers()
const wait = ms => new Promise(resolve => later(resolve, ms))
/** Bumped to stop a sequence that is still running (replay, unmount). */
let run = 0

/** One hop from the current pad to `to`: a squash, an arc through the air, a landing squash, a ring on the water. */
async function hopTo(to) {
  const el = frog.value
  const from = pos.value
  const a = place(from)
  const b = place(to)
  const lift = Math.max(a.y, b.y) > 50 && Math.min(a.y, b.y) < 50 ? 26 : 22
  const tilt = Math.sign(b.x - a.x) * 10
  const at = p => ({ left: `${p.x}%`, top: `${p.y}%` })
  const anim = el?.animate(
    [
      { ...at(a), transform: 'translate(-50%, -78%) scale(1.18, 0.82)' },
      { ...at({ x: (a.x + b.x) / 2, y: Math.min(a.y, b.y) - lift }), transform: `translate(-50%, -78%) scale(0.9, 1.16) rotate(${tilt}deg)`, offset: 0.5 },
      { ...at(b), transform: 'translate(-50%, -78%) scale(1.2, 0.8)', offset: 0.85 },
      { ...at(b), transform: 'translate(-50%, -78%) scale(1, 1)' },
    ],
    { duration: HOP_MS, easing: 'ease-in-out', fill: 'forwards' },
  )
  tone(freq(from), 0.12)
  await (anim ? anim.finished.catch(() => {}) : wait(HOP_MS))
  pos.value = to
  await nextTick()
  anim?.cancel()
  splash.value = to
  tone(freq(to), 0.32)
  buzz(10)
  later(() => splash.value === to && (splash.value = null), 700)
}

async function hopSequence() {
  const mine = ++run
  phase.value = 'watch'
  busy.value = true
  await wait(650)
  for (const m of props.data.moves) {
    for (let k = 0; k < Math.abs(m); k++) {
      if (mine !== run) return
      await hopTo(pos.value + Math.sign(m))
      await wait(180)
    }
    await wait(420)
  }
  if (mine === run) {
    busy.value = false
    phase.value = 'play'
  }
}

watch(
  () => props.promptDone,
  done => {
    if (props.data.mode !== 'land') return
    if (done && !props.locked) {
      pos.value = props.data.start
      hopSequence()
    } else if (!done) {
      run++
      busy.value = false
      pos.value = props.data.start
      phase.value = 'wait'
    }
  },
  { immediate: true },
)
onBeforeUnmount(() => run++)

function fail(say) {
  errors.value++
  if (errors.value >= MAX_ERRORS) {
    phase.value = 'done'
    emit('skip', t('hop.tooHard'))
    return false
  }
  emit('answer', { correct: false, say })
  return true
}

async function jump() {
  if (props.locked || busy.value || phase.value !== 'play') return
  const next = pos.value + dir.value
  if (next < 0 || next > max.value) {
    shake('frog')
    return
  }
  busy.value = true
  hops.value++
  await hopTo(next)
  busy.value = false
}

async function ready() {
  if (props.locked || busy.value || phase.value !== 'play' || hops.value === 0) return
  if (hops.value === Math.abs(props.data.moves[0]) && pos.value === end.value) {
    phase.value = 'done'
    cheer.value = true
    emit('answer', { correct: true, say: props.data.onCorrect })
    return
  }
  if (!fail(t('hop.wrongCount'))) return
  // back to the start, in one hop
  busy.value = true
  hops.value = 0
  await hopTo(props.data.start)
  busy.value = false
}

function pick(n) {
  if (props.locked || busy.value || phase.value !== 'play' || props.data.mode !== 'land') return
  if (n === end.value) {
    phase.value = 'done'
    cheer.value = true
    tone(freq(n), 0.5)
    emit('answer', { correct: true, say: props.data.onCorrect })
    return
  }
  shake(`p${n}`)
  phase.value = 'wait'
  if (fail(props.data.onWrong)) later(() => emit('replay', props.data.replayParts), REPLAY_DELAY_MS)
}
</script>

<template>
  <p class="status" aria-live="polite">
    <template v-if="data.mode === 'land' && phase === 'watch'">{{ t('hop.watch') }}</template>
    <template v-else-if="data.mode === 'land' && phase === 'play'">{{ t('hop.whichPad') }}</template>
    <template v-else-if="data.mode === 'hop'">{{ t('hop.hops', { count: hops }) }}</template>
    <template v-else>&nbsp;</template>
  </p>

  <div class="pond" :class="{ 'pond--cheer': cheer }" data-no-feel>
    <span class="shimmer" aria-hidden="true" />
    <button
      v-for="p in pads"
      :key="p.n"
      type="button"
      class="pad"
      :class="{
        'pad--start': p.n === data.start,
        'pad--splash': splash === p.n,
        'pad--pick': data.mode === 'land' && phase === 'play',
        'pad--shake': shaking === `p${p.n}`,
      }"
      :style="{ left: `${p.x}%`, top: `${p.y}%`, '--d': `${p.n * 40}ms` }"
      :aria-label="String(p.n)"
      @click="pick(p.n)"
    >
      <b>{{ p.n }}</b>
      <i class="ring" aria-hidden="true" />
    </button>

    <div
      ref="frog"
      class="frog"
      :class="{ 'frog--shake': shaking === 'frog', 'frog--cheer': cheer, 'frog--left': dir < 0 && data.mode === 'hop' }"
      :style="{ left: `${place(pos).x}%`, top: `${place(pos).y}%` }"
      aria-hidden="true"
    >
      <EmojiArt :char="data.emoji" />
    </div>
  </div>

  <div v-if="data.mode === 'hop'" class="actions" data-no-feel>
    <button type="button" class="jump" :disabled="busy || phase !== 'play'" @click="jump">
      <EmojiArt :char="data.emoji" /> {{ t('hop.jump') }}
    </button>
    <button type="button" class="done" :disabled="busy || phase !== 'play' || hops === 0" @click="ready">
      ✔ {{ t('hop.ready') }}
    </button>
  </div>
</template>

<style scoped>
.status {
  min-height: 1.3em;
  margin: -6px 0 0;
  font-size: var(--bz-text-md);
  font-weight: 800;
  color: var(--bz-muted);
}
.pond {
  position: relative;
  width: 100%;
  max-width: 520px;
  aspect-ratio: 16 / 10;
  border-radius: 28px;
  overflow: hidden;
  background: linear-gradient(180deg, #7fd3f0 0%, #4fb4dc 60%, #3a9ac8 100%);
  box-shadow: inset 0 -10px 0 rgba(0, 0, 0, 0.08), var(--bz-shadow-lg);
}
/* light moving over the water */
.shimmer {
  position: absolute;
  inset: -20%;
  background:
    radial-gradient(ellipse at 20% 30%, rgba(255, 255, 255, 0.28), transparent 35%),
    radial-gradient(ellipse at 70% 65%, rgba(255, 255, 255, 0.2), transparent 40%);
  animation: drift 7s ease-in-out infinite alternate;
  pointer-events: none;
}
.pad {
  position: absolute;
  display: grid;
  place-items: center;
  width: 15%;
  aspect-ratio: 1.15;
  transform: translate(-50%, -50%);
  border-radius: 50% 50% 48% 52% / 54% 50% 50% 46%;
  background: radial-gradient(circle at 35% 30%, #9be38a, #52b84a 70%);
  box-shadow: 0 4px 0 rgba(20, 90, 40, 0.45);
  animation: pad-in 0.5s var(--bz-spring) backwards;
  animation-delay: var(--d);
  touch-action: manipulation;
}
/* the lily pad's notch */
.pad::before {
  content: '';
  position: absolute;
  top: 0;
  left: 50%;
  width: 14%;
  height: 46%;
  background: #4fb4dc;
  transform: translateX(-50%) rotate(8deg);
  transform-origin: 50% 100%;
  clip-path: polygon(0 0, 100% 0, 50% 100%);
  opacity: 0.85;
}
.pad b {
  position: relative;
  font-size: clamp(14px, 4vw, 22px);
  font-weight: 900;
  color: #174d22;
  text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5);
}
.pad--start {
  box-shadow:
    0 0 0 3px rgba(255, 255, 255, 0.7),
    0 4px 0 rgba(20, 90, 40, 0.45);
}
.pad--pick {
  cursor: pointer;
  animation: none;
}
.pad--pick:active {
  transform: translate(-50%, -46%) scale(0.94);
}
.ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 3px solid rgba(255, 255, 255, 0);
  pointer-events: none;
}
.pad--splash .ring {
  animation: ring 0.7s ease-out;
}
.pad--splash {
  animation: dip 0.45s var(--bz-spring);
}
.pad--shake {
  animation: shake 0.4s ease;
}
.frog {
  position: absolute;
  z-index: 2;
  transform: translate(-50%, -78%);
  font-size: clamp(34px, 10vw, 54px);
  line-height: 1;
  filter: drop-shadow(0 4px 2px rgba(0, 0, 0, 0.25));
  pointer-events: none;
}
.frog--left {
  scale: -1 1;
}
.frog--shake {
  animation: frog-shake 0.4s ease;
}
.frog--cheer {
  animation: cheer 0.7s ease-in-out 2;
}
.actions {
  display: flex;
  gap: 12px;
  width: 100%;
  max-width: 520px;
}
.jump,
.done {
  flex: 1;
  padding: 16px 10px;
  border-radius: 24px;
  font-size: var(--bz-text-md);
  font-weight: 900;
  box-shadow: var(--bz-shadow-lg);
  transition: transform 0.2s var(--bz-spring), opacity 0.2s;
}
.jump {
  flex: 1.6;
  background: var(--bz-leaf);
  color: var(--bz-on-accent);
}
.done {
  background: var(--bz-sun);
}
.jump:active:not(:disabled),
.done:active:not(:disabled) {
  transform: scale(0.94);
}
.jump:disabled,
.done:disabled {
  opacity: 0.55;
}
@keyframes drift {
  to {
    transform: translate(6%, 4%) scale(1.08);
  }
}
@keyframes pad-in {
  from {
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.3);
  }
}
@keyframes dip {
  40% {
    transform: translate(-50%, -38%) scale(0.94);
  }
}
@keyframes ring {
  from {
    transform: scale(0.8);
    border-color: rgba(255, 255, 255, 0.95);
  }
  to {
    transform: scale(2.1);
    border-color: rgba(255, 255, 255, 0);
  }
}
@keyframes shake {
  25% {
    transform: translate(-56%, -50%);
  }
  75% {
    transform: translate(-44%, -50%);
  }
}
@keyframes frog-shake {
  25% {
    translate: -8px 0;
  }
  75% {
    translate: 8px 0;
  }
}
@keyframes cheer {
  40% {
    translate: 0 -22px;
    rotate: -12deg;
  }
  70% {
    translate: 0 -8px;
    rotate: 10deg;
  }
}
@media (prefers-reduced-motion: reduce) {
  .shimmer {
    animation: none;
  }
}
</style>
