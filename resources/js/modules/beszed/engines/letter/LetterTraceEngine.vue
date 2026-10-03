<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { engineEmits, engineProps } from '../contract'
import { glyphStrokes } from './glyphs'

/**
 * Write a letter with a finger (Betűíró): the dotted letter lies in a square, its strokes numbered
 * in writing order, and the child traces them. A letter passes when most of it is covered and the
 * pen stayed near it; the order is not enforced, only shown. Points are normalised (0–1).
 * data: LetterData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

/** A letter point counts as covered when a drawn point is this close (share of the square). */
const HIT = 0.085
/** Share of the letter to cover, and of the pen's points that must lie near it. */
const COVER = 0.72
const NEAR = 0.7
const PEN_WIDTH = 10

const wrap = ref(null)
const canvas = ref(null)

let ctx = null
let size = 0
const strokes = glyphStrokes(props.data.glyph)
const all = strokes.flat()
/** @type {Array<Array<[number, number]>>} */
let drawn = []
let drawing = false
let colors = { guide: '#9C8AAE', pen: '#FF6F61', text: '#3b1f4a' }
let observer = null

const px = ([x, y]) => [x * size, y * size]

function polyline(points) {
  ctx.beginPath()
  points.forEach((p, i) => {
    const [x, y] = px(p)
    if (i) ctx.lineTo(x, y)
    else ctx.moveTo(x, y)
  })
  ctx.stroke()
}

function redraw() {
  ctx.clearRect(0, 0, size, size)
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.setLineDash([2, 15])
  ctx.lineWidth = 11
  ctx.strokeStyle = colors.guide
  strokes.forEach(polyline)
  ctx.setLineDash([])
  ctx.lineWidth = PEN_WIDTH
  ctx.strokeStyle = colors.pen
  drawn.forEach(polyline)
  // the numbered start of each stroke
  ctx.font = `800 ${Math.round(size * 0.06)}px system-ui, sans-serif`
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  strokes.forEach((stroke, i) => {
    const [x, y] = px(stroke[0])
    ctx.fillStyle = colors.text
    ctx.beginPath()
    ctx.arc(x, y, size * 0.036, 0, Math.PI * 2)
    ctx.fill()
    ctx.fillStyle = '#fff'
    ctx.fillText(String(i + 1), x, y + 1)
  })
}

function layout() {
  const w = Math.min(wrap.value.clientWidth, 360)
  if (!w || w === size) return
  size = w
  const dpr = window.devicePixelRatio || 1
  const el = canvas.value
  el.width = size * dpr
  el.height = size * dpr
  el.style.width = `${size}px`
  el.style.height = `${size}px`
  ctx = el.getContext('2d')
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  redraw()
}

function pointOf(event) {
  const rect = canvas.value.getBoundingClientRect()
  return [(event.clientX - rect.left) / size, (event.clientY - rect.top) / size]
}

function onDown(event) {
  if (props.locked) return
  drawing = true
  drawn.push([pointOf(event)])
  canvas.value.setPointerCapture?.(event.pointerId)
}

function onMove(event) {
  if (!drawing) return
  const stroke = drawn[drawn.length - 1]
  const [x0, y0] = px(stroke[stroke.length - 1])
  const point = pointOf(event)
  stroke.push(point)
  const [x1, y1] = px(point)
  ctx.lineWidth = PEN_WIDTH
  ctx.strokeStyle = colors.pen
  ctx.beginPath()
  ctx.moveTo(x0, y0)
  ctx.lineTo(x1, y1)
  ctx.stroke()
}

const onUp = () => {
  drawing = false
}

function clear() {
  drawn = []
  redraw()
}

const near = (a, b, r) => (a[0] - b[0]) ** 2 + (a[1] - b[1]) ** 2 < r * r

function done() {
  if (props.locked) return
  const pen = drawn.flat()
  if (!pen.length) return emit('answer', { correct: false, say: t('letter.almost') })
  const covered = all.filter(p => pen.some(q => near(p, q, HIT))).length / all.length
  const tidy = pen.filter(q => all.some(p => near(p, q, HIT * 1.6))).length / pen.length
  const passed = covered >= COVER && tidy >= NEAR
  emit('answer', passed ? { correct: true, say: props.data.onCorrect } : { correct: false, say: t('letter.almost') })
  if (!passed) clear()
}

onMounted(() => {
  const css = getComputedStyle(wrap.value)
  colors = {
    guide: css.getPropertyValue('--bz-guide').trim() || colors.guide,
    pen: css.getPropertyValue('--bz-coral').trim() || colors.pen,
    text: css.getPropertyValue('--bz-ink').trim() || colors.text,
  }
  layout()
  if ('ResizeObserver' in window) {
    observer = new ResizeObserver(layout)
    observer.observe(wrap.value)
  }
})
onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <div ref="wrap" class="letter">
    <canvas
      ref="canvas"
      :aria-label="t('letter.canvas', { name: data.name })"
      @pointerdown="onDown"
      @pointermove="onMove"
      @pointerup="onUp"
      @pointercancel="onUp"
    />
  </div>
  <div class="bz-row">
    <BzButton :icon="ICONS.speaker" @click="emit('replay')">{{ t('letter.again') }}</BzButton>
    <BzButton :icon="ICONS.reset" @click="clear">{{ t('trace.clear') }}</BzButton>
    <BzButton variant="primary" :icon="ICONS.done" @click="done">{{ t('common.done') }}</BzButton>
  </div>
</template>

<style scoped>
.letter {
  display: grid;
  place-items: center;
  width: 100%;
  max-width: 360px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  touch-action: none;
}
canvas {
  display: block;
  touch-action: none;
}
</style>
