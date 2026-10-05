<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { engineEmits, engineProps } from '../contract'
import { samplePath } from './paths'

/**
 * Follow the dotted path with a finger, from the bee to the flower (Méhecske útja).
 * Points are kept normalised (0–1), so the drawing survives rotation / resizing.
 * data: TraceData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const START = '🐝'
const END = '🌸'
/** A path point counts as covered when a drawn point is this close (px). */
const HIT_RADIUS = 30
/** Share of the path that has to be covered. */
const PASS_RATIO = 0.75
const PEN_WIDTH = 9
const GUIDE_WIDTH = 10

const wrap = ref(null)
const canvas = ref(null)
const markers = ref({ start: [0, 0], end: [0, 0] })

let ctx = null
let width = 0
let height = 0
let guidePoints = []
/** @type {Array<Array<[number, number]>>} normalised strokes */
let strokes = []
let drawing = false
let colors = { guide: '#9C8AAE', pen: '#FF6F61' }
let observer = null

const toPx = ([x, y]) => [x * width, y * height]

function polyline(points) {
  ctx.beginPath()
  points.forEach((point, i) => {
    const [x, y] = toPx(point)
    if (i) ctx.lineTo(x, y)
    else ctx.moveTo(x, y)
  })
  ctx.stroke()
}

function redraw() {
  ctx.clearRect(0, 0, width, height)
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.setLineDash([2, 16])
  ctx.lineWidth = GUIDE_WIDTH
  ctx.strokeStyle = colors.guide
  polyline(guidePoints)
  ctx.setLineDash([])
  ctx.lineWidth = PEN_WIDTH
  ctx.strokeStyle = colors.pen
  strokes.forEach(polyline)
}

/** Sizes the canvas to its box (sharp on retina) and redraws; only when the width changed. */
function layout() {
  const w = wrap.value.clientWidth
  if (!w || w === width) return
  width = w
  height = Math.round(Math.min(340, Math.max(230, w * 0.55)))
  const dpr = window.devicePixelRatio || 1
  const el = canvas.value
  wrap.value.style.height = `${height}px`
  el.width = width * dpr
  el.height = height * dpr
  el.style.width = `${width}px`
  el.style.height = `${height}px`
  ctx = el.getContext('2d')
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  markers.value = { start: toPx(guidePoints[0]), end: toPx(guidePoints[guidePoints.length - 1]) }
  redraw()
}

function pointOf(event) {
  const rect = canvas.value.getBoundingClientRect()
  return [(event.clientX - rect.left) / width, (event.clientY - rect.top) / height]
}

function onDown(event) {
  if (props.locked) return
  drawing = true
  strokes.push([pointOf(event)])
  canvas.value.setPointerCapture?.(event.pointerId)
}

function onMove(event) {
  if (!drawing) return
  const stroke = strokes[strokes.length - 1]
  const [x0, y0] = toPx(stroke[stroke.length - 1])
  const point = pointOf(event)
  stroke.push(point)
  const [x1, y1] = toPx(point)
  ctx.lineWidth = PEN_WIDTH
  ctx.strokeStyle = colors.pen
  ctx.beginPath()
  ctx.moveTo(x0, y0)
  ctx.lineTo(x1, y1)
  ctx.stroke()
}

function onUp() {
  drawing = false
}

function clear() {
  strokes = []
  redraw()
}

function done() {
  if (props.locked) return
  const drawn = strokes.flat().map(toPx)
  const r2 = HIT_RADIUS ** 2
  const covered = guidePoints
    .map(toPx)
    .filter(([x, y]) => drawn.some(([a, b]) => (a - x) ** 2 + (b - y) ** 2 < r2)).length
  const passed = covered / guidePoints.length >= PASS_RATIO
  if (!passed) clear()
  emit('answer', passed ? { correct: true, say: props.data.onCorrect } : { correct: false, say: t('trace.almost') })
}

onMounted(() => {
  const css = getComputedStyle(wrap.value)
  colors = {
    guide: css.getPropertyValue('--bz-guide').trim() || colors.guide,
    pen: css.getPropertyValue('--bz-coral').trim() || colors.pen,
  }
  guidePoints = samplePath(props.data.path, props.data.difficulty)
  layout()
  if ('ResizeObserver' in window) {
    observer = new ResizeObserver(layout)
    observer.observe(wrap.value)
  }
})

onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <div ref="wrap" class="trace">
    <canvas
      ref="canvas"
      @pointerdown="onDown"
      @pointermove="onMove"
      @pointerup="onUp"
      @pointercancel="onUp"
    />
    <EmojiArt class="marker" :style="{ left: `${markers.start[0]}px`, top: `${markers.start[1]}px` }" :char="START" />
    <EmojiArt class="marker" :style="{ left: `${markers.end[0]}px`, top: `${markers.end[1]}px` }" :char="END" />
  </div>
  <div class="bz-row">
    <BzButton :icon="ICONS.reset" @click="clear">{{ t('trace.clear') }}</BzButton>
    <BzButton variant="primary" :icon="ICONS.done" @click="done">{{ t('common.done') }}</BzButton>
  </div>
</template>

<style scoped>
.trace {
  position: relative;
  width: 100%;
  overflow: hidden;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  touch-action: none;
}
canvas {
  display: block;
  touch-action: none;
}
.marker {
  position: absolute;
  transform: translate(-50%, -50%);
  font-size: 40px;
  pointer-events: none;
}
</style>
