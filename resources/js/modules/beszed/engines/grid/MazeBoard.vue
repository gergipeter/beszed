<script setup>
import { computed, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { beep, pluck, sparkle } from '../../services/audio/sfx'
import { buzz } from '../../services/touch/feel'
import { engineEmits, engineProps } from '../contract'
import { SIDES, centres, gradeOf, neighbour, place, xy } from './grid'

/**
 * Labirintus (grid engine, mode maze): drag the hero with a finger along the corridors — it hops from cell
 * centre to cell centre and can't cross a wall — or tap a cell in a straight open line to walk there. A push
 * into a wall is a soft bump; stepping off the way into a side path is a wrong turn. Both grade the win. On
 * level 3 a star waits on the way (in a perfect maze every walk to the goal passes it). After a few wrong
 * turns, footprints show the next steps. Arrow keys work too.
 * data: GridMazeData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const WIN_DELAY_MS = 380
const WALK_MS = 150
const BUMP_FLASH_MS = 450
/** How far (in cells) the finger must go past the hero's centre before it moves or bumps. */
const REACH = 0.58

const cols = computed(() => props.data.cols)
const rows = computed(() => props.data.rows)
const onWay = computed(() => new Set(props.data.path))

const at = ref(props.data.start)
/** The hero's own way so far, with steps taken back removed: drawn as a trail. */
const route = ref([props.data.start])
const starTaken = ref(false)
const won = ref(false)
const moved = ref(false)
const detours = ref(0)
let bumps = 0
let bumpSaid = false
let deadEndSaid = false

const dragging = ref(false)
const walking = ref(false)
/** Leaning towards the finger, in cells (a little way along an open corridor, hardly at all into a wall). */
const lean = ref({ x: 0, y: 0 })
const bumped = ref(null)
let bumpKey = 0
let pushBlocked = false

const field = ref(null)
const { shaking, shake } = useShake()
const { later } = useTimers()
const wait = ms => new Promise(resolve => later(resolve, ms))

const isOpen = (cell, side) => !(props.data.walls[cell] & side.bit) && neighbour(cell, side, cols.value, rows.value) !== null
const openings = cell => Object.values(SIDES).filter(side => isOpen(cell, side)).length

/** The maze's walls as one SVG path: each cell draws its top and left wall, the last row/column also the far ones. */
const wallPath = computed(() => {
  const d = []
  props.data.walls.forEach((mask, cell) => {
    const { x, y } = xy(cell, cols.value)
    if (mask & SIDES.up.bit) d.push(`M${x} ${y}h1`)
    if (mask & SIDES.left.bit) d.push(`M${x} ${y}v1`)
    if (x === cols.value - 1 && mask & SIDES.right.bit) d.push(`M${x + 1} ${y}v1`)
    if (y === rows.value - 1 && mask & SIDES.down.bit) d.push(`M${x} ${y + 1}h1`)
  })
  return d.join('')
})
const trail = computed(() => centres(route.value, cols.value))

/** The bumped wall, flashed for a moment. */
const bumpLine = computed(() => {
  if (!bumped.value) return null
  const { x, y } = xy(bumped.value.cell, cols.value)
  return {
    up: { x1: x, y1: y, x2: x + 1, y2: y },
    right: { x1: x + 1, y1: y, x2: x + 1, y2: y + 1 },
    down: { x1: x, y1: y + 1, x2: x + 1, y2: y + 1 },
    left: { x1: x, y1: y, x2: x, y2: y + 1 },
  }[bumped.value.side]
})

/** After a few wrong turns: footprints on the next steps of the way, from the last cell of it the hero was on. */
const hint = computed(() => {
  if (won.value || detours.value < props.data.hintAfter) return []
  const path = props.data.path
  let last = 0
  for (const cell of route.value) {
    const i = path.indexOf(cell)
    if (i > last) last = i
  }
  return path.slice(last + 1, last + 5).filter(c => c !== props.data.goal)
})

function step(side) {
  const from = at.value
  const to = neighbour(from, side, cols.value, rows.value)
  at.value = to
  moved.value = true
  const r = route.value
  route.value = r.length > 1 && r[r.length - 2] === to ? r.slice(0, -1) : [...r, to]
  if (onWay.value.has(from) && !onWay.value.has(to)) detours.value++
  pluck(route.value.length % 2 ? 784 : 659)

  if (to === props.data.star && !starTaken.value) {
    starTaken.value = true
    sparkle()
    emit('say', props.data.onStar)
  }
  if (to === props.data.goal) return win()
  if (!deadEndSaid && !onWay.value.has(to) && openings(to) === 1) {
    deadEndSaid = true
    emit('say', props.data.onDeadEnd)
  }
}

function bump(side) {
  bumps++
  bumped.value = { cell: at.value, side: side.id, key: ++bumpKey }
  shake(`bump-${side.id}`)
  beep(120, 0.16)
  buzz(22)
  const key = bumpKey
  later(() => bumped.value?.key === key && (bumped.value = null), BUMP_FLASH_MS)
  if (!bumpSaid) {
    bumpSaid = true
    emit('say', props.data.onBump)
  }
}

function win() {
  won.value = true
  dragging.value = false
  lean.value = { x: 0, y: 0 }
  const mistakes = detours.value + Math.floor(bumps / 2)
  later(() => emit('answer', { correct: true, say: props.data.onCorrect, tries: gradeOf(mistakes, props.data.grade) }), WIN_DELAY_MS)
}

const frozen = () => props.locked || won.value

/** Pointer position in cells (0…cols, 0…rows) over the maze. */
function toCells(event) {
  const rect = field.value.getBoundingClientRect()
  return { x: ((event.clientX - rect.left) / rect.width) * cols.value, y: ((event.clientY - rect.top) / rect.height) * rows.value }
}

/** Dragging: hop towards the finger, one open side at a time; a push into a wall bumps once. */
function follow(p) {
  for (let guard = 0; guard < cols.value * rows.value && !won.value; guard++) {
    const { x, y } = xy(at.value, cols.value)
    const dx = p.x - (x + 0.5)
    const dy = p.y - (y + 0.5)
    if (Math.max(Math.abs(dx), Math.abs(dy)) < REACH) {
      if (Math.max(Math.abs(dx), Math.abs(dy)) < 0.4) pushBlocked = false
      break
    }
    const horizontal = dx > 0 ? SIDES.right : SIDES.left
    const vertical = dy > 0 ? SIDES.down : SIDES.up
    const [first, second] = Math.abs(dx) >= Math.abs(dy) ? [horizontal, vertical] : [vertical, horizontal]
    const secondFar = Math.abs(first === horizontal ? dy : dx) >= REACH
    const side = isOpen(at.value, first) ? first : secondFar && isOpen(at.value, second) ? second : null
    if (!side) {
      if (!pushBlocked) bump(first)
      pushBlocked = true
      break
    }
    pushBlocked = false
    step(side)
  }
  if (won.value) return
  // lean a little towards the finger: along an open corridor more, into a wall hardly
  const { x, y } = xy(at.value, cols.value)
  const dx = p.x - (x + 0.5)
  const dy = p.y - (y + 0.5)
  const horizontal = Math.abs(dx) >= Math.abs(dy)
  const side = horizontal ? (dx > 0 ? SIDES.right : SIDES.left) : dy > 0 ? SIDES.down : SIDES.up
  const max = isOpen(at.value, side) ? 0.32 : 0.06
  const amount = Math.max(-max, Math.min(max, horizontal ? dx : dy))
  lean.value = horizontal ? { x: amount, y: 0 } : { x: 0, y: amount }
}

/** A tap away from the hero: walk there if it is in a straight line, stopping (with a bump) at a wall. */
async function walkTo(cell) {
  const from = xy(at.value, cols.value)
  const to = xy(cell, cols.value)
  if (from.x !== to.x && from.y !== to.y) {
    shake('hero') // not in a straight line: the hero wiggles, "drag me"
    return
  }
  const side = to.x > from.x ? SIDES.right : to.x < from.x ? SIDES.left : to.y > from.y ? SIDES.down : SIDES.up
  walking.value = true
  while (at.value !== cell && !won.value && !props.locked) {
    if (!isOpen(at.value, side)) {
      bump(side)
      break
    }
    step(side)
    await wait(WALK_MS)
  }
  walking.value = false
}

function down(event) {
  if (frozen() || walking.value || dragging.value) return
  const p = toCells(event)
  const { x, y } = xy(at.value, cols.value)
  if (Math.abs(p.x - (x + 0.5)) <= 0.85 && Math.abs(p.y - (y + 0.5)) <= 0.85) {
    dragging.value = true
    pushBlocked = false
    field.value.setPointerCapture?.(event.pointerId)
    follow(p)
    return
  }
  const cx = Math.min(cols.value - 1, Math.max(0, Math.floor(p.x)))
  const cy = Math.min(rows.value - 1, Math.max(0, Math.floor(p.y)))
  walkTo(cy * cols.value + cx)
}

function move(event) {
  if (!dragging.value || frozen()) return
  follow(toCells(event))
}

function up(event) {
  if (!dragging.value) return
  dragging.value = false
  lean.value = { x: 0, y: 0 }
  field.value?.releasePointerCapture?.(event.pointerId)
}

const KEYS = { ArrowUp: SIDES.up, ArrowDown: SIDES.down, ArrowLeft: SIDES.left, ArrowRight: SIDES.right }
function key(event) {
  const side = KEYS[event.key]
  if (!side) return
  event.preventDefault()
  if (frozen() || walking.value) return
  if (isOpen(at.value, side)) step(side)
  else bump(side)
}

const heroStyle = computed(() => ({ ...place(at.value, cols.value), '--lx': lean.value.x, '--ly': lean.value.y }))
</script>

<template>
  <div class="maze" :class="{ 'maze--won': won }" :style="{ '--cols': cols, '--rows': rows }" data-no-feel>
    <div
      ref="field"
      class="field"
      tabindex="0"
      role="application"
      :aria-label="t('grid.maze')"
      @pointerdown="down"
      @pointermove="move"
      @pointerup="up"
      @pointercancel="up"
      @lostpointercapture="up"
      @keydown="key"
    >
      <svg class="lines" :viewBox="`0 0 ${cols} ${rows}`" preserveAspectRatio="none" overflow="visible" aria-hidden="true">
        <rect class="start" :x="xy(data.start, cols).x + 0.1" :y="xy(data.start, cols).y + 0.1" width="0.8" height="0.8" rx="0.2" />
        <path class="trail" :d="trail" />
        <circle v-for="c in hint" :key="`h${c}`" class="hint" :cx="xy(c, cols).x + 0.5" :cy="xy(c, cols).y + 0.5" r="0.11" />
        <path class="walls" :d="wallPath" />
        <line v-if="bumpLine" :key="bumped.key" class="bump-wall" v-bind="bumpLine" />
      </svg>

      <span class="piece goal" :class="{ 'goal--won': won }" :style="place(data.goal, cols)" aria-hidden="true">
        <EmojiArt :char="data.target" />
      </span>
      <span v-if="data.star !== null && data.star !== undefined" class="piece star" :class="{ 'star--taken': starTaken }" :style="place(data.star, cols)" aria-hidden="true">
        <EmojiArt :char="ICONS.star" />
      </span>
      <span
        class="piece hero"
        :class="{ 'hero--drag': dragging, 'hero--won': won }"
        :style="heroStyle"
        aria-hidden="true"
      >
        <span class="hero-in" :class="[shaking && shaking.startsWith('bump') && `hero-in--${shaking}`, { 'hero-in--wiggle': shaking === 'hero' }]">
          <EmojiArt :char="data.hero" />
        </span>
      </span>
    </div>
  </div>
  <p class="hint-text" aria-live="polite">{{ moved || won ? ' ' : t('grid.mazeHint') }}</p>
</template>

<style scoped>
/* as big as the screen allows, square, short enough to fit under Csillám */
.maze {
  width: min(100%, 560px, calc(100dvh - var(--bz-stage-room, 330px)));
  min-width: min(100%, 260px);
  aspect-ratio: 1;
  padding: 3.5%;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-trail-stone);
  box-shadow:
    inset 0 0 0 4px rgba(255, 255, 255, 0.45),
    var(--bz-shadow-lg);
}
.field {
  position: relative;
  width: 100%;
  height: 100%;
  container-type: size;
  touch-action: none;
  user-select: none;
  -webkit-user-select: none;
  outline-offset: 6px;
  cursor: grab;
}
.lines {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  overflow: visible;
  pointer-events: none;
}
.start {
  fill: var(--bz-sun);
  opacity: 0.45;
}
.trail {
  fill: none;
  stroke: var(--bz-coral);
  stroke-width: 0.16;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 0.02 0.3;
  opacity: 0.85;
}
.hint {
  fill: var(--bz-leaf);
  animation: hint 1.1s ease-in-out infinite alternate;
}
.walls {
  fill: none;
  stroke: var(--bz-forest-deep);
  stroke-width: 0.15;
  stroke-linecap: round;
}
.bump-wall {
  stroke: var(--bz-coral-deep);
  stroke-width: 0.22;
  stroke-linecap: round;
  animation: flash 0.45s ease-out forwards;
}
/* one cell big, moved to its cell with transform only */
.piece {
  position: absolute;
  left: 0;
  top: 0;
  display: grid;
  place-items: center;
  width: calc(100% / var(--cols));
  height: calc(100% / var(--rows));
  font-size: calc(68cqw / var(--cols));
  line-height: 1;
  transform: translate(calc(var(--x) * 100%), calc(var(--y) * 100%));
  pointer-events: none;
}
.goal :deep(.emoji),
.star :deep(.emoji) {
  animation: bob 1.6s ease-in-out infinite alternate;
}
.star {
  font-size: calc(54cqw / var(--cols));
  transition:
    opacity 0.4s,
    scale 0.4s var(--bz-spring);
}
.star--taken {
  opacity: 0;
  scale: 2;
}
.goal--won {
  animation: cheer 0.7s ease-in-out 2;
}
.hero {
  z-index: 2;
  font-size: calc(78cqw / var(--cols));
  translate: calc(var(--lx) * 100%) calc(var(--ly) * 100%);
  transition:
    transform 0.16s ease-out,
    translate 0.12s ease-out;
  filter: drop-shadow(0 3px 2px rgba(0, 0, 0, 0.25));
}
.hero--drag {
  scale: 1.12;
}
.hero--won {
  z-index: 3;
  animation: cheer 0.7s ease-in-out 2;
}
.hero-in {
  display: block;
}
.hero-in--bump-up {
  animation: bump-up 0.3s ease-out;
}
.hero-in--bump-down {
  animation: bump-down 0.3s ease-out;
}
.hero-in--bump-left {
  animation: bump-left 0.3s ease-out;
}
.hero-in--bump-right {
  animation: bump-right 0.3s ease-out;
}
.hero-in--wiggle {
  animation: wiggle 0.45s ease;
}
.hint-text {
  min-height: 1.3em;
  margin: -6px 0 0;
  font-size: var(--bz-text-md);
  font-weight: 800;
  color: var(--bz-muted);
  text-align: center;
}
@keyframes bump-up {
  40% {
    transform: translateY(-14%) scale(1.06, 0.9);
  }
}
@keyframes bump-down {
  40% {
    transform: translateY(14%) scale(1.06, 0.9);
  }
}
@keyframes bump-left {
  40% {
    transform: translateX(-14%) scale(0.9, 1.06);
  }
}
@keyframes bump-right {
  40% {
    transform: translateX(14%) scale(0.9, 1.06);
  }
}
@keyframes wiggle {
  25% {
    transform: rotate(-12deg);
  }
  75% {
    transform: rotate(12deg);
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
@keyframes flash {
  to {
    opacity: 0;
  }
}
@keyframes hint {
  from {
    opacity: 0.35;
  }
  to {
    opacity: 0.9;
  }
}
@media (prefers-reduced-motion: reduce) {
  .goal :deep(.emoji),
  .star :deep(.emoji),
  .hint {
    animation: none;
  }
}
</style>
