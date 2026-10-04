<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { beep, pluck, tone } from '../../services/audio/sfx'
import { buzz } from '../../services/touch/feel'
import { engineEmits, engineProps } from '../contract'
import ArrowIcon from './ArrowIcon.vue'
import { SIDES, centres, gradeOf, neighbour, place } from './grid'

/**
 * Kis robot (grid engine, mode program): four big arrows, fel / le / balra / jobbra (absolute moves; each
 * one says its word).
 *   direct   each arrow moves the robot at once; an obstacle or the edge is a bump, the robot stays
 *   program  the arrows line up commands in a strip (tap one to take it out); "Indulj!" runs them step by
 *            step. A bump, or stopping short of the goal, sends the robot back to the start and the strip
 *            stays, so the child can fix it. Reaching the goal wins.
 * After a few bumps or failed runs faint footprints show one shortest way.
 * data: GridProgramData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const STEP_MS = 620
const BACK_MS = 1300
const WIN_DELAY_MS = 380

const cols = computed(() => props.data.cols)
const rows = computed(() => props.data.rows)
const blocks = computed(() => new Set(props.data.blocks))
const cells = computed(() => Array.from({ length: cols.value * rows.value }, (_, i) => i))

const at = ref(props.data.start)
const program = ref([])
const running = ref(false)
const current = ref(-1)
/** The command that bumped, or program.length when the robot stopped short. */
const failedAt = ref(null)
const won = ref(false)
const fails = ref(0)
const bumps = ref(0)
const steps = ref(0)
let alive = true
onBeforeUnmount(() => (alive = false))

const { shaking, shake } = useShake()
/** The robot's own bump / "not there yet" wobble, apart from the shaking strip or obstacle. */
const { shaking: wobble, shake: shakeBot } = useShake()
const { later } = useTimers()
const wait = ms => new Promise(resolve => later(resolve, ms))

const full = computed(() => program.value.length >= props.data.maxSteps)
const mistakes = computed(() =>
  props.data.direct ? bumps.value + Math.floor(Math.max(0, steps.value - props.data.best) / 2) : fails.value,
)
const showHint = computed(() => !won.value && mistakes.value >= props.data.hintAfter)
const hintPath = computed(() => centres(props.data.path, cols.value))
const moveOf = id => props.data.moves.find(m => m.id === id)

/** Where `id` takes the robot from `cell`: { to } or { bump: 'edge' | 'block' }. */
function target(cell, id) {
  const to = neighbour(cell, SIDES[id], cols.value, rows.value)
  if (to === null) return { bump: 'edge' }
  if (blocks.value.has(to)) return { bump: 'block', to }
  return { to }
}

function bump(id, kind) {
  shakeBot(`bump-${id}`)
  beep(120, 0.18)
  buzz([20, 40, 20])
  emit('say', kind === 'edge' ? props.data.onBumpEdge : props.data.onBumpBlock)
  if (kind === 'block') shake(`block-${neighbour(at.value, SIDES[id], cols.value, rows.value)}`)
}

function win() {
  won.value = true
  running.value = false
  tone(1046.5, 0.3)
  later(() => emit('answer', { correct: true, say: props.data.onCorrect, tries: gradeOf(mistakes.value, props.data.grade) }), WIN_DELAY_MS)
}

const frozen = () => props.locked || won.value || running.value

function press(id) {
  if (frozen()) return
  const move = moveOf(id)
  if (props.data.direct) {
    const next = target(at.value, id)
    if (next.bump) {
      bumps.value++
      bump(id, next.bump)
      return
    }
    at.value = next.to
    steps.value++
    pluck(523.25 + steps.value * 40)
    if (at.value === props.data.goal) {
      win()
      return
    }
    emit('say', move.say)
    return
  }
  if (full.value) {
    shake('strip')
    beep(160, 0.12)
    return
  }
  failedAt.value = null
  program.value = [...program.value, id]
  pluck(587.33 + program.value.length * 30)
  emit('say', move.say)
}

function remove(i) {
  if (frozen()) return
  program.value = program.value.filter((_, k) => k !== i)
  failedAt.value = null
  beep(300, 0.07)
}

function clear() {
  if (frozen()) return
  program.value = []
  failedAt.value = null
  beep(240, 0.1)
}

/** Runs the strip step by step; a bump or a short stop sends the robot back to the start. */
async function run() {
  if (frozen() || !program.value.length) return
  running.value = true
  failedAt.value = null
  at.value = props.data.start
  await wait(150)
  for (let i = 0; i < program.value.length; i++) {
    if (!alive || props.locked) return
    current.value = i
    const id = program.value[i]
    const next = target(at.value, id)
    if (next.bump) {
      bump(id, next.bump)
      return fail(i)
    }
    at.value = next.to
    tone(392 + i * 49, 0.14)
    if (at.value === props.data.goal) {
      current.value = -1
      win()
      return
    }
    emit('say', moveOf(id).say)
    await wait(STEP_MS)
  }
  emit('say', props.data.onShort)
  shakeBot('robot')
  return fail(program.value.length)
}

async function fail(i) {
  fails.value++
  failedAt.value = i
  await wait(BACK_MS)
  if (!alive) return
  current.value = -1
  at.value = props.data.start
  running.value = false
}

/** One empty slot shows where the next command goes (none once the strip is full). */
const slots = computed(() => (full.value ? 0 : 1))
</script>

<template>
  <div class="robot" :class="{ 'robot--program': !data.direct }" data-no-feel>
    <div class="board" :style="{ '--cols': cols, '--rows': rows }" role="img" :aria-label="t('grid.board')">
      <span
        v-for="c in cells"
        :key="c"
        class="tile"
        :class="{ 'tile--dark': (c % cols + Math.floor(c / cols)) % 2, 'tile--start': c === data.start }"
      >
        <span v-if="blocks.has(c)" class="block" :class="{ 'block--shake': shaking === `block-${c}` }"><EmojiArt :char="data.obstacle" /></span>
      </span>
      <svg v-if="showHint" class="hint" :viewBox="`0 0 ${cols} ${rows}`" preserveAspectRatio="none" aria-hidden="true">
        <path :d="hintPath" />
      </svg>
      <span class="piece goal" :class="{ 'goal--won': won }" :style="place(data.goal, cols)" aria-hidden="true">
        <EmojiArt :char="data.target" />
      </span>
      <span class="piece bot" :class="{ 'bot--won': won, 'bot--run': running }" :style="place(at, cols)" aria-hidden="true">
        <span class="bot-in" :class="wobble && `bot-in--${wobble}`">
          <EmojiArt :char="data.hero" />
        </span>
      </span>
    </div>

    <div class="controls">
      <div
        v-if="!data.direct"
        class="strip"
        :class="{ 'strip--shake': shaking === 'strip' }"
        role="group"
        :aria-label="t('grid.program')"
      >
        <button
          v-for="(id, i) in program"
          :key="`${i}-${id}`"
          type="button"
          class="cmd"
          :class="{ 'cmd--now': current === i, 'cmd--bad': failedAt === i }"
          :aria-label="t('grid.remove', { n: i + 1, dir: moveOf(id).label })"
          :disabled="running || won"
          @click="remove(i)"
        >
          <ArrowIcon :dir="id" />
        </button>
        <span v-for="s in slots" :key="`s${s}`" class="slot" :class="{ 'slot--bad': s === 1 && failedAt === program.length }" aria-hidden="true" />
        <button v-if="program.length > 1 && !running && !won" type="button" class="clear" :aria-label="t('grid.clear')" @click="clear">
          <EmojiArt :char="ICONS.trash" />
        </button>
      </div>

      <div class="pad" role="group" :aria-label="t('grid.moves')">
        <button
          v-for="m in data.moves"
          :key="m.id"
          type="button"
          class="key"
          :class="`key--${m.id}`"
          :disabled="locked || won || running || (!data.direct && full)"
          :aria-label="m.label"
          @click="press(m.id)"
        >
          <ArrowIcon :dir="m.id" />
          <small>{{ m.label }}</small>
        </button>
        <button
          v-if="!data.direct"
          type="button"
          class="go"
          :disabled="locked || won || running || !program.length"
          @click="run"
        >
          <EmojiArt :char="ICONS.play" />
          <small>{{ t('grid.go') }}</small>
        </button>
        <span v-else class="pad-mid" aria-hidden="true"><EmojiArt :char="data.hero" /></span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.robot {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  width: 100%;
}
/* the board: square, smaller than the stage so the strip and the arrows fit under it */
.board {
  position: relative;
  display: grid;
  grid-template-columns: repeat(var(--cols), 1fr);
  grid-template-rows: repeat(var(--rows), 1fr);
  width: min(100%, 440px, calc(100dvh - var(--bz-stage-room, 330px) - 230px));
  min-width: min(100%, 230px);
  aspect-ratio: 1;
  padding: 2.5%;
  border-radius: var(--bz-radius-lg);
  background: #6f8fb8;
  box-shadow:
    inset 0 0 0 4px rgba(255, 255, 255, 0.35),
    var(--bz-shadow-lg);
  container-type: size;
}
.robot--program .board {
  width: min(100%, 440px, calc(100dvh - var(--bz-stage-room, 330px) - 300px));
}
.tile {
  position: relative;
  display: grid;
  place-items: center;
  margin: 3%;
  border-radius: 18%;
  background: #e9f1ff;
}
.tile--dark {
  background: #d6e3f8;
}
.tile--start {
  background: #fff1b8;
}
.block {
  font-size: calc(62cqw / var(--cols));
  line-height: 1;
}
.block--shake {
  animation: shake 0.4s ease;
}
.hint {
  position: absolute;
  inset: 2.5%;
  width: 95%;
  height: 95%;
  pointer-events: none;
}
.hint path {
  fill: none;
  stroke: var(--bz-leaf-deep);
  stroke-width: 0.12;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 0.01 0.28;
  opacity: 0.75;
}
/* one cell big; moves with transform only */
.piece {
  position: absolute;
  left: 2.5%;
  top: 2.5%;
  display: grid;
  place-items: center;
  width: calc(95% / var(--cols));
  height: calc(95% / var(--rows));
  font-size: calc(66cqw / var(--cols));
  line-height: 1;
  transform: translate(calc(var(--x) * 100%), calc(var(--y) * 100%));
  pointer-events: none;
}
.goal :deep(.emoji) {
  animation: bob 1.6s ease-in-out infinite alternate;
}
.goal--won {
  animation: cheer 0.7s ease-in-out 2;
}
.bot {
  z-index: 2;
  font-size: calc(72cqw / var(--cols));
  filter: drop-shadow(0 3px 2px rgba(0, 0, 0, 0.3));
  transition: transform 0.36s cubic-bezier(0.3, 1.3, 0.5, 1);
}
.bot--won {
  z-index: 3;
  animation: cheer 0.7s ease-in-out 2;
}
.bot-in {
  display: block;
}
.bot-in--bump-up {
  animation: bump-up 0.4s ease-out;
}
.bot-in--bump-down {
  animation: bump-down 0.4s ease-out;
}
.bot-in--bump-left {
  animation: bump-left 0.4s ease-out;
}
.bot-in--bump-right {
  animation: bump-right 0.4s ease-out;
}
.bot-in--robot {
  animation: shake 0.45s ease;
}

.controls {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  width: 100%;
  max-width: 440px;
}
.strip {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 6px;
  width: 100%;
  min-height: 60px;
  padding: 8px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-sm);
}
.strip--shake {
  animation: shake 0.4s ease;
}
.cmd,
.slot {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
}
.cmd {
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  font-size: 26px;
  box-shadow: 0 3px 0 rgba(59, 31, 74, 0.2);
  transition: transform 0.2s var(--bz-spring);
}
.cmd:active:not(:disabled) {
  transform: scale(0.9);
}
.cmd:disabled {
  opacity: 1;
}
.cmd--now {
  transform: translateY(-5px) scale(1.12);
  box-shadow: 0 0 0 3px var(--bz-leaf-deep);
}
.cmd--bad {
  background: var(--bz-coral);
  box-shadow: 0 0 0 3px var(--bz-coral-deep);
}
.slot {
  border: 3px dashed color-mix(in srgb, var(--bz-muted) 45%, transparent);
}
.slot--bad {
  border-color: var(--bz-coral-deep);
  border-style: solid;
}
.clear {
  display: grid;
  place-items: center;
  margin-left: 4px;
  border-radius: 50%;
  font-size: 22px;
}
/* a cross of arrows; Indulj in the middle */
.pad {
  display: grid;
  grid-template-areas:
    '. up .'
    'left mid right'
    '. down .';
  grid-template-columns: repeat(3, var(--key));
  grid-template-rows: repeat(3, var(--key));
  gap: 8px;
  --key: clamp(56px, 15.5vw, 74px);
}
.key,
.go {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1px;
  border-radius: 20px;
  font-weight: 900;
  box-shadow: var(--bz-shadow);
  touch-action: manipulation;
  transition: transform 0.25s var(--bz-spring);
}
.key {
  background: var(--bz-card);
  color: var(--bz-ink);
  font-size: 34px;
}
.key small,
.go small {
  font-size: 14px;
  line-height: 1;
}
.key--up {
  grid-area: up;
}
.key--down {
  grid-area: down;
}
.key--left {
  grid-area: left;
}
.key--right {
  grid-area: right;
}
.go {
  grid-area: mid;
  background: var(--bz-leaf-deep);
  color: var(--bz-on-accent);
  font-size: 28px;
}
.pad-mid {
  grid-area: mid;
  display: grid;
  place-items: center;
  font-size: 34px;
  opacity: 0.5;
}
.key:active:not(:disabled),
.go:active:not(:disabled) {
  transform: scale(0.92);
}
.key:disabled,
.go:disabled {
  opacity: 0.45;
}

/* a tablet (or any wide, not so tall screen): the board on the left, the strip and the arrows beside it */
@media (min-width: 700px) and (orientation: landscape) {
  .robot {
    flex-direction: row;
    justify-content: center;
    align-items: center;
    gap: 28px;
  }
  .board,
  .robot--program .board {
    width: min(52vw, 460px, calc(100dvh - var(--bz-stage-room, 330px)));
  }
  .controls {
    width: auto;
    max-width: 380px;
  }
  .strip {
    width: 340px;
  }
}

@keyframes bump-up {
  40% {
    transform: translateY(-22%) scale(1.08, 0.88);
  }
}
@keyframes bump-down {
  40% {
    transform: translateY(22%) scale(1.08, 0.88);
  }
}
@keyframes bump-left {
  40% {
    transform: translateX(-22%) scale(0.88, 1.08);
  }
}
@keyframes bump-right {
  40% {
    transform: translateX(22%) scale(0.88, 1.08);
  }
}
@keyframes shake {
  25% {
    transform: translateX(-6px);
  }
  75% {
    transform: translateX(6px);
  }
}
@keyframes bob {
  to {
    transform: translateY(-8%) scale(1.06);
  }
}
@keyframes cheer {
  40% {
    rotate: -12deg;
    scale: 1.3;
  }
  70% {
    rotate: 10deg;
    scale: 1.15;
  }
}
@media (prefers-reduced-motion: reduce) {
  .goal :deep(.emoji) {
    animation: none;
  }
}
</style>
