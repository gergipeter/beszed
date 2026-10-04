<script setup>
import { computed, nextTick, reactive, ref } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { pluck, sparkle } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'
import { PICTURES } from './pictures'

/**
 * Színező: a line picture from pictures.js, paint pots under it. Csillám says what to colour ("Színezd a
 * tetőt pirosra!"): tap a pot (it lifts), then the part. A wrong colour or part shakes and Csillám says the
 * step again (a try); after two misses in a step the part to colour glows and its pot bobs. When the steps
 * are done every pot is offered and the rest is coloured freely, until the child taps Kész. The win is
 * graded by the misses in the steps. Fills change at once; only transform/opacity animate.
 * data: ColorData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

/** Pitch of each pot's little pluck (paint-box order, low to high). */
const NOTE = { piros: 262, narancs: 294, sarga: 330, zold: 349, kek: 392, lila: 440, rozsaszin: 494, barna: 523, szurke: 587, fekete: 659 }

const picture = PICTURES[props.data.picture] ?? { regions: [] }
/** region id → colour id */
const fills = reactive({})
/** regions of the current step already done */
const done = reactive({})
const step = ref(0)
const pot = ref(null)
const mistakes = ref(0)
const stepMistakes = ref(0)
const popping = ref(null)
const doneButton = ref(null)
const { shaking, shake } = useShake()
const { later } = useTimers()

const steps = computed(() => props.data.steps ?? [])
const current = computed(() => steps.value[step.value] ?? null)
const free = computed(() => step.value >= steps.value.length)
const pots = computed(() => (free.value ? props.data.freePots : props.data.pots))
const listening = computed(() => !free.value && !props.promptDone)
const remaining = computed(() => (current.value ? current.value.fills.filter(f => !done[f.region]) : []))
/** After two misses in a step: the part (and pot) to use next. */
const hint = computed(() => (stepMistakes.value >= 2 ? remaining.value[0] ?? null : null))

const fillOf = id => (fills[id] ? `var(--paint-${fills[id]})` : 'var(--szinezo-paper, #fffdf8)')
const nameOf = id => props.data.regions?.[id] ?? id

function choose(id) {
  if (props.locked) return
  pot.value = id
  pluck(NOTE[id] ?? 392)
  // while steps are given, the pot stays silent so Csillám's sentence isn't cut off
  if (free.value) emit('say', props.data.colors?.[id] ?? id)
}

function pop(id) {
  popping.value = null
  requestAnimationFrame(() => (popping.value = id))
  later(() => popping.value === id && (popping.value = null), 450)
}

function miss(id, say) {
  mistakes.value++
  stepMistakes.value++
  shake(id)
  emit('answer', { correct: false, say })
}

function paint(id) {
  if (props.locked || listening.value) return
  if (!pot.value) {
    shake('pots')
    emit('say', props.data.pickFirst)
    return
  }
  if (free.value) {
    fills[id] = pot.value
    pop(id)
    pluck(NOTE[pot.value] ?? 392)
    return
  }

  const match = remaining.value.find(f => f.region === id && f.color === pot.value)
  if (match) {
    fills[id] = pot.value
    done[id] = true
    pop(id)
    pluck(NOTE[pot.value] ?? 392)
    if (!remaining.value.length) later(advance, 350)
    return
  }
  // a part already coloured right, tapped again with its own colour: nothing to judge
  if (fills[id] === pot.value) return

  const part = remaining.value.find(f => f.region === id)
  if (part) return miss(id, part.wrongColor)
  const wanted = remaining.value.find(f => f.color === pot.value) ?? remaining.value[0]
  miss(id, wanted.wrongPart)
}

function advance() {
  if (props.locked) return
  for (const id of Object.keys(done)) delete done[id]
  stepMistakes.value = 0
  step.value++
  if (free.value) {
    sparkle()
    emit('say', props.data.free)
    // all ten pots now: on a small phone the Kész button may have moved off screen
    nextTick(() => doneButton.value?.$el?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' }))
  } else {
    emit('say', current.value.lead ?? current.value.say)
  }
}

function again() {
  if (current.value) emit('say', current.value.say)
}

function finish() {
  if (props.locked || !free.value) return
  const tries = mistakes.value === 0 ? 1 : mistakes.value <= 2 ? 2 : 3
  emit('answer', { correct: true, say: props.data.onCorrect, tries })
}

function onKey(event, id) {
  if (event.key !== 'Enter' && event.key !== ' ') return
  event.preventDefault()
  paint(id)
}
</script>

<template>
  <div class="color" :class="{ 'color--free': free }">
    <svg class="sheet" viewBox="0 0 300 300" role="group" :aria-label="t('color.picture', { name: data.name })">
      <rect class="paper" x="1.5" y="1.5" width="297" height="297" rx="24" />
      <g
        v-for="r in picture.regions"
        :key="r.id"
        class="region"
        :class="{ 'region--shake': shaking === r.id, 'region--pop': popping === r.id }"
        role="button"
        tabindex="0"
        :aria-label="nameOf(r.id)"
        @click="paint(r.id)"
        @keydown="onKey($event, r.id)"
      >
        <path v-for="(d, i) in r.paths" :key="i" class="shape" :d="d" :style="{ fill: fillOf(r.id) }" />
        <path v-if="r.lines" class="line" :d="r.lines" />
        <path v-if="r.ink" class="ink" :d="r.ink" />
      </g>
      <path v-if="picture.lines" class="line" :d="picture.lines" />
      <path v-if="picture.ink" class="ink" :d="picture.ink" />
      <template v-if="hint">
        <g v-for="r in picture.regions.filter(x => x.id === hint.region)" :key="`hint-${r.id}`" class="glow" aria-hidden="true">
          <path v-for="(d, i) in r.paths" :key="i" :d="d" />
        </g>
      </template>
    </svg>

    <div class="side">
      <p v-if="!free" class="task" aria-live="polite">{{ listening ? t('color.listen') : current?.say }}</p>
      <p v-else class="bz-sr-only" aria-live="polite">{{ t('color.free') }}</p>
      <div v-if="!free" class="progress" aria-hidden="true">
        <span v-for="(s, i) in steps" :key="i" class="dot" :class="{ 'dot--done': i < step, 'dot--now': i === step }" />
      </div>
      <div class="pots" :class="{ 'pots--shake': shaking === 'pots' }" role="radiogroup" :aria-label="t('color.pots')">
        <button
          v-for="id in pots"
          :key="id"
          type="button"
          role="radio"
          class="pot"
          :class="{ 'pot--on': pot === id, 'pot--hint': hint && hint.color === id && pot !== id }"
          :style="{ '--pot': `var(--paint-${id})` }"
          :aria-checked="pot === id"
          :aria-label="t('color.pot', { name: data.colors?.[id] ?? id })"
          @click="choose(id)"
        >
          <span class="paint" />
        </button>
      </div>
      <div class="bz-row">
        <BzButton v-if="!free" :icon="ICONS.speaker" @click="again">{{ t('color.again') }}</BzButton>
        <BzButton v-else ref="doneButton" variant="primary" :icon="ICONS.done" @click="finish">{{ t('color.done') }}</BzButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.color {
  /* the paint box: bright, told apart easily, the same by day and night */
  --paint-piros: #ef4a3c;
  --paint-narancs: #ff9a2e;
  --paint-sarga: #ffd93b;
  --paint-zold: #4cb944;
  --paint-kek: #3d8beb;
  --paint-lila: #9b5de5;
  --paint-rozsaszin: #ff8fc7;
  --paint-barna: #9a6236;
  --paint-szurke: #9aa0a8;
  --paint-fekete: #3a3540;
  --outline: #2a2140;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  width: 100%;
}
/* at night the white sheet would glare: a softer paper, the same dark lines (inherited from the layout) */
:global(.bz[data-daytime='night']) {
  --szinezo-paper: #e6e1ef;
}
.sheet {
  display: block;
  width: min(100%, 460px, calc(100dvh - var(--bz-stage-room, 330px) - 190px));
  min-width: 240px;
  aspect-ratio: 1;
  border-radius: 24px;
  box-shadow: var(--bz-shadow);
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
  user-select: none;
}
.paper {
  fill: var(--szinezo-paper, #fffdf8);
  stroke: rgba(42, 33, 64, 0.12);
  stroke-width: 3;
}
.region {
  cursor: pointer;
  outline: none;
  transform-box: fill-box;
  transform-origin: center;
}
.region:focus-visible .shape {
  stroke: var(--bz-coral-deep);
}
.shape {
  stroke: var(--outline);
  stroke-width: 3;
  stroke-linejoin: round;
}
.line {
  fill: none;
  stroke: var(--outline);
  stroke-width: 2.4;
  stroke-linecap: round;
  stroke-linejoin: round;
  pointer-events: none;
}
.ink {
  fill: var(--outline);
  pointer-events: none;
}
.region--pop {
  animation: pop 0.42s var(--bz-spring);
}
.region--shake {
  animation: shake 0.45s;
}
.glow path {
  fill: none;
  stroke: var(--bz-coral);
  stroke-width: 7;
  stroke-linejoin: round;
  pointer-events: none;
  animation: glow 1s ease-in-out infinite alternate;
}

.side {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  width: 100%;
  max-width: 460px;
}
.task {
  min-height: 1.3em;
  margin: 0;
  padding: 4px 14px;
  border-radius: var(--bz-radius-pill);
  background: rgba(255, 255, 255, 0.78);
  color: #3b1f4a;
  font-size: var(--bz-text-sm);
  font-weight: 800;
  text-align: center;
}
.progress {
  display: flex;
  gap: 6px;
}
.dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.7);
  box-shadow: inset 0 0 0 2px rgba(42, 33, 64, 0.35);
}
.dot--now {
  box-shadow: inset 0 0 0 3px var(--bz-coral-deep);
}
.dot--done {
  background: var(--bz-leaf);
  box-shadow: none;
}
.pots {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 4px 6px;
  padding-top: 10px;
}
.pots--shake {
  animation: shake 0.45s;
}
.pot {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: none;
  transition: transform 0.3s var(--bz-spring);
  -webkit-tap-highlight-color: transparent;
}
.paint {
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background:
    radial-gradient(circle at 34% 30%, rgba(255, 255, 255, 0.6) 0 16%, transparent 17%),
    var(--pot);
  border: 3px solid var(--outline);
  box-shadow:
    0 0 0 3px #fff,
    0 5px 0 3px rgba(42, 33, 64, 0.2);
}
.pot--on {
  transform: translateY(-10px) scale(1.12);
}
.pot--on .paint {
  box-shadow:
    0 0 0 3px #fff,
    0 0 0 6px var(--outline),
    0 12px 0 3px rgba(42, 33, 64, 0.18);
}
.pot--hint {
  animation: bob 0.9s ease-in-out infinite alternate;
}

/* a tablet or a phone on its side: the picture left, the paint box right */
@media (orientation: landscape) and (min-width: 640px) {
  .color {
    flex-direction: row;
    justify-content: center;
    align-items: center;
    gap: 22px;
  }
  .sheet {
    width: min(58vw, 500px, calc(100dvh - var(--bz-stage-room, 330px) - 10px));
  }
  .side {
    width: auto;
    max-width: 260px;
  }
  .pots {
    max-width: 200px;
  }
}

@keyframes pop {
  40% {
    transform: scale(1.06);
  }
}
@keyframes shake {
  20% {
    transform: translateX(-7px);
  }
  40% {
    transform: translateX(7px);
  }
  60% {
    transform: translateX(-4px);
  }
  80% {
    transform: translateX(4px);
  }
}
@keyframes glow {
  from {
    opacity: 0.25;
  }
  to {
    opacity: 0.95;
  }
}
@keyframes bob {
  to {
    transform: translateY(-6px);
  }
}
@media (prefers-reduced-motion: reduce) {
  .glow path {
    animation: none;
    opacity: 0.8;
  }
}
</style>
