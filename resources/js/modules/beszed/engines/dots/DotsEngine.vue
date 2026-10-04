<script setup>
import { computed, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { t } from '../../i18n'
import { sparkle } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'

/**
 * Pontról pontra (dot to dot): numbered (or lettered) dots placed by the
 * server, 0–1 on a square board. Tap them in order, or drag a finger through
 * them: each joined dot says its number and draws the line from the one
 * before. A wrong dot shakes and Csillám asks which comes next (and the next
 * dot pulses for a moment). After the last dot the outline closes, fills and
 * the picture pops out with its name. Graded by wrong taps (tries 1–3).
 *
 * A tap goes to the nearest dot within a circle at least MIN_HIT px across;
 * the server keeps dots far enough apart that these circles never overlap on a phone.
 * data: DotsData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

/** Dot radius in board units (the board is 100 × 100). */
const R = 5
const MIN_HIT = 48
const HINT_MS = 2600
const REVEAL_MS = 1300

const board = ref(null)
/** How many dots are joined (the next one to tap is dots[joined]). */
const joined = ref(0)
const closed = ref(false)
const hinting = ref(false)
const finger = ref(null)
let dragging = false
let wrong = 0
const { shaking, shake } = useShake()
const { later } = useTimers()

const pts = computed(() => props.data.dots.map(d => [d.x * 100, d.y * 100]))
const line = computed(() =>
  pts.value
    .slice(0, joined.value)
    .map(p => p.join(','))
    .join(' '),
)
const shape = computed(() => pts.value.map(p => p.join(',')).join(' '))
const last = computed(() => (joined.value ? pts.value[joined.value - 1] : null))
/** Where the picture appears: the middle of the outline's box. */
const centre = computed(() => {
  const xs = pts.value.map(p => p[0])
  const ys = pts.value.map(p => p[1])
  return [(Math.min(...xs) + Math.max(...xs)) / 2, (Math.min(...ys) + Math.max(...ys)) / 2]
})
const pulsing = i => !closed.value && i === joined.value && (props.data.pulse || hinting.value)
const grade = () => (wrong === 0 ? 1 : wrong <= 2 ? 2 : 3)
const fontSize = label => (label.length >= 3 ? 3.6 : label.length === 2 ? 4.4 : 5.2)

function toBoard(event) {
  const rect = board.value.getBoundingClientRect()
  return { x: ((event.clientX - rect.left) / rect.width) * 100, y: ((event.clientY - rect.top) / rect.height) * 100, scale: 100 / rect.width }
}

/** The dot under the finger, or -1. */
function hit({ x, y, scale }) {
  const radius = Math.max(R * 1.3, (MIN_HIT / 2) * scale)
  let best = -1
  let bestD = radius
  pts.value.forEach(([px, py], i) => {
    const d = Math.hypot(x - px, y - py)
    if (d < bestD) [best, bestD] = [i, d]
  })
  return best
}

function join() {
  const dot = props.data.dots[joined.value]
  joined.value++
  hinting.value = false
  if (joined.value < props.data.dots.length) {
    emit('say', dot.say)
    return
  }
  // the last one: the outline closes and the picture comes out
  dragging = false
  finger.value = null
  closed.value = true
  sparkle()
  later(() => emit('answer', { correct: true, say: props.data.onCorrect, tries: grade() }), REVEAL_MS)
}

function miss(i) {
  wrong++
  shake(i)
  hinting.value = true
  emit('say', props.data.dots[joined.value].hint)
  later(() => (hinting.value = false), HINT_MS)
}

function onDown(event) {
  if (props.locked || closed.value || event.button > 0) return
  const at = toBoard(event)
  const i = hit(at)
  dragging = true
  board.value.setPointerCapture?.(event.pointerId)
  if (i === joined.value) join()
  // the last joined dot is a fine place to start a drag from; any other dot is a wrong tap
  else if (i >= 0 && i !== joined.value - 1) miss(i)
  finger.value = joined.value && !closed.value ? at : null
}

function onMove(event) {
  if (!dragging || props.locked || closed.value) return
  const at = toBoard(event)
  // sliding over other dots on the way is fine: only the next one counts
  if (hit(at) === joined.value) join()
  finger.value = joined.value && !closed.value ? at : null
}

function onUp() {
  dragging = false
  finger.value = null
}

function tapKey(i) {
  if (props.locked || closed.value) return
  if (i === joined.value) join()
  else if (i >= joined.value) miss(i)
}
</script>

<template>
  <div class="dots-game">
    <div
      ref="board"
      class="board"
      :class="{ 'board--closed': closed }"
      role="group"
      :aria-label="t('dots.board')"
      @pointerdown.prevent="onDown"
      @pointermove="onMove"
      @pointerup="onUp"
      @pointercancel="onUp"
    >
      <svg viewBox="0 0 100 100" class="svg" aria-hidden="true">
        <polygon v-if="closed" class="fill" :points="shape" />
        <polyline class="line" :points="line" />
        <line v-if="closed" class="line" :x1="pts.at(-1)[0]" :y1="pts.at(-1)[1]" :x2="pts[0][0]" :y2="pts[0][1]" />
        <line v-if="finger && last" class="band" :x1="last[0]" :y1="last[1]" :x2="finger.x" :y2="finger.y" />
        <g
          v-for="(d, i) in data.dots"
          :key="i"
          class="dot"
          :class="{
            'dot--done': i < joined,
            'dot--next': pulsing(i),
            'dot--first': i === 0 && joined === 0,
            'dot--shake': shaking === i,
          }"
          :style="{ transformOrigin: `${pts[i][0]}px ${pts[i][1]}px` }"
        >
          <circle :cx="pts[i][0]" :cy="pts[i][1]" :r="R" />
          <text :x="pts[i][0]" :y="pts[i][1]" :font-size="fontSize(d.label)" dominant-baseline="central" text-anchor="middle">{{ d.label }}</text>
        </g>
      </svg>
      <!-- there from the start (hidden), so the picture has loaded by the time it pops out -->
      <div v-show="closed" class="reveal" :style="{ left: `${centre[0]}%`, top: `${centre[1]}%` }">
        <EmojiArt class="reveal-art" :char="data.emoji" :label="data.name" />
        <span class="reveal-name">{{ data.name }}</span>
      </div>
      <!-- keyboards and screen readers: one button per dot -->
      <div class="bz-sr-only">
        <button v-for="(d, i) in data.dots" :key="i" type="button" :disabled="i < joined" @click="tapKey(i)">
          {{ t('dots.dot', { label: d.label }) }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dots-game {
  display: flex;
  justify-content: center;
  width: 100%;
}
.board {
  position: relative;
  width: min(100%, 560px, calc(100dvh - var(--bz-stage-room, 330px) - 10px));
  min-width: min(100%, 260px);
  aspect-ratio: 1;
  border-radius: var(--bz-radius-lg);
  background:
    radial-gradient(circle, var(--bz-guide) 0 0.6px, transparent 0.8px) 0 0 / 5% 5%,
    var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
  touch-action: none;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
  container-type: inline-size;
}
.svg {
  display: block;
  width: 100%;
  height: 100%;
  overflow: visible;
}
.line,
.band {
  fill: none;
  stroke: var(--bz-coral);
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.band {
  stroke-dasharray: 1.5 2.5;
  opacity: 0.7;
}
.fill {
  fill: var(--bz-sun);
  opacity: 0.45;
  animation: fill-in 0.6s ease-out;
}
@keyframes fill-in {
  from {
    opacity: 0;
  }
}
.dot circle {
  fill: var(--bz-chart);
  stroke: var(--bz-card);
  stroke-width: 1;
}
.dot text {
  fill: #fff;
  font-weight: 800;
  font-family: var(--bz-font);
  pointer-events: none;
}
.dot--first circle {
  fill: var(--bz-leaf-deep);
}
.dot--done circle {
  fill: var(--bz-coral-deep);
}
.dot--next {
  animation: pulse 0.9s ease-in-out infinite alternate;
}
@keyframes pulse {
  to {
    transform: scale(1.3);
  }
}
.dot--shake {
  animation: shake 0.45s;
}
@keyframes shake {
  20% {
    transform: translateX(-2px);
  }
  40% {
    transform: translateX(2px);
  }
  60% {
    transform: translateX(-1.4px);
  }
  80% {
    transform: translateX(1.4px);
  }
}
.board--closed .dot {
  opacity: 0;
  transition: opacity 0.5s 0.3s;
}
.reveal {
  position: absolute;
  display: flex;
  flex-direction: column;
  align-items: center;
  transform: translate(-50%, -50%);
  pointer-events: none;
}
.reveal-art {
  font-size: 34cqw;
  filter: drop-shadow(0 4px 0 rgba(0, 0, 0, 0.15));
  animation: pop-out 0.6s var(--bz-spring, ease-out) 0.25s both;
}
.reveal-name {
  margin-top: 4px;
  padding: 2px 14px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  color: var(--bz-ink);
  font-weight: 800;
  font-size: var(--bz-text-md);
  box-shadow: var(--bz-shadow-sm);
  animation: pop-out 0.4s ease-out 0.6s both;
}
@keyframes pop-out {
  from {
    opacity: 0;
    transform: scale(0.2);
  }
}
</style>
