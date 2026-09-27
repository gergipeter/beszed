<script setup>
import { computed, ref } from 'vue'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { beep, fanfare, sparkle } from '../../services/audio/sfx'
import { burst } from '../../services/effects/burst'
import { buzz } from '../../services/touch/feel'
import EmojiArt from '../ui/EmojiArt.vue'
import SceneBackdrop from './SceneBackdrop.vue'

/**
 * The sticker picture: pick a drawn background, then put earned stickers on it.
 * Drag a sticker from the tray straight onto the picture (or tap it to drop it
 * in the middle); drag placed ones around, two fingers pinch and turn them.
 * Tapping a placed sticker makes it wiggle and Csillám says its name; "Életre
 * kel!" makes the whole picture dance for a moment. Positions are saved
 * (debounced) through `@change`.
 */
const props = defineProps({
  /** @type {import('vue').PropType<import('../../types').Scene>} */
  scene: { type: Object, required: true },
  /** @type {import('vue').PropType<import('../../types').Badge[]>} */
  earnedBadges: { type: Array, required: true },
  /** @type {import('vue').PropType<import('../../types').Background[]>} */
  backgrounds: { type: Array, required: true },
  maxStickers: { type: Number, default: 24 },
})
const emit = defineEmits(['change', 'say'])
const { later } = useTimers()

const board = ref(null)
/** Local working copy so dragging feels instant; pushed out (debounced) via `change`. */
const background = ref(props.scene.background)
let nextKey = 0
const keyed = ref(props.scene.stickers.map(s => ({ scale: 1, ...s, key: nextKey++ })))
const selected = ref(null)
/** The sticker just stuck on (it squashes into place), and the one just tapped (it wiggles). */
const landed = ref(null)
const tapped = ref(null)
/** "Életre kel!": the picture dances. */
const alive = ref(false)

const badgeById = computed(() => Object.fromEntries(props.earnedBadges.map(b => [b.id, b])))
const full = computed(() => keyed.value.length >= props.maxStickers)
const selectedSticker = computed(() => keyed.value.find(s => s.key === selected.value) || null)

let saveTimer = null
function scheduleSave() {
  const stickers = keyed.value.map(({ key, ...s }) => s)
  clearTimeout(saveTimer)
  saveTimer = setTimeout(() => emit('change', { background: background.value, stickers }), 400)
}

function chooseBackground(id) {
  background.value = background.value === id ? null : id
  beep(520, 0.06)
  scheduleSave()
}

function boardPoint(clientX, clientY) {
  const rect = board.value.getBoundingClientRect()
  const x = ((clientX - rect.left) / rect.width) * 100
  const y = ((clientY - rect.top) / rect.height) * 100
  return { x: Math.max(2, Math.min(98, x)), y: Math.max(2, Math.min(98, y)) }
}
const insideBoard = (x, y) => {
  const r = board.value?.getBoundingClientRect()
  return Boolean(r) && x >= r.left && x <= r.right && y >= r.top && y <= r.bottom
}

/** Sticks `badge` on at `point` (% of the picture): it squashes into place with a pop. */
function place(badge, point) {
  if (full.value) return
  const key = nextKey++
  keyed.value.push({ key, badge: badge.id, ...point, rotate: Math.round(Math.random() * 30 - 15), scale: 1 })
  selected.value = key
  landed.value = key
  later(() => {
    if (landed.value === key) landed.value = null
  }, 600)
  beep(420)
  buzz(10)
  scheduleSave()
}

function removeSticker(key) {
  keyed.value = keyed.value.filter(s => s.key !== key)
  if (selected.value === key) selected.value = null
  scheduleSave()
}

function nudgeScale(delta) {
  const item = selectedSticker.value
  if (!item) return
  item.scale = Math.round(Math.max(0.5, Math.min(2.5, item.scale + delta)) * 20) / 20
  scheduleSave()
}

function nudgeRotate(delta) {
  const item = selectedSticker.value
  if (!item) return
  item.rotate = Math.max(-180, Math.min(180, item.rotate + delta))
  scheduleSave()
}

// --- the tray: tap drops a sticker in the middle, a drag carries it to where it's let go ---
const MOVE_SLOP = 8
let trayDrag = null
let ghost = null
const aiming = ref(false)

function trayDown(event, badge) {
  if (full.value || event.button > 0) return
  trayDrag = { badge, x0: event.clientX, y0: event.clientY, moved: false }
  event.currentTarget.setPointerCapture?.(event.pointerId)
}
function trayMove(event) {
  if (!trayDrag) return
  if (!trayDrag.moved) {
    if (Math.hypot(event.clientX - trayDrag.x0, event.clientY - trayDrag.y0) < MOVE_SLOP) return
    trayDrag.moved = true
    ghost = event.currentTarget.querySelector('.tray-art')?.cloneNode(true)
    if (ghost) {
      ghost.classList.add('bz-sticker-ghost')
      document.body.appendChild(ghost)
    }
  }
  if (ghost) ghost.style.transform = `translate(${event.clientX}px, ${event.clientY}px) translate(-50%, -50%) scale(1.5) rotate(-8deg)`
  aiming.value = insideBoard(event.clientX, event.clientY)
}
function trayUp(event) {
  const drag = trayDrag
  trayDrag = null
  ghost?.remove()
  ghost = null
  aiming.value = false
  if (!drag) return
  if (!drag.moved) return place(drag.badge, { x: 38 + Math.random() * 24, y: 38 + Math.random() * 24 })
  if (insideBoard(event.clientX, event.clientY)) place(drag.badge, boardPoint(event.clientX, event.clientY))
}
function trayCancel() {
  trayDrag = null
  ghost?.remove()
  ghost = null
  aiming.value = false
}

// --- placed stickers: one finger moves, two pinch (scale) and twist (rotate); a tap says its name ---
const pointers = new Map()
let dragKey = null
let gestureStart = null
let moved = false
let downAt = null

function pointsAngleDist(a, b) {
  const dx = b.x - a.x
  const dy = b.y - a.y
  return { dist: Math.hypot(dx, dy), angle: (Math.atan2(dy, dx) * 180) / Math.PI }
}

function startDrag(key, event) {
  selected.value = key
  dragKey = key
  moved = false
  downAt = { x: event.clientX, y: event.clientY }
  event.target.setPointerCapture?.(event.pointerId)
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })
}
/** Tapping the empty picture deselects; a second finger while a sticker is held starts a pinch/turn. */
function onBoardPointerDown(event) {
  if (dragKey === null) {
    if (event.target === board.value || event.target.closest('.scene-art')) selected.value = null
    return
  }
  if (pointers.has(event.pointerId)) return
  board.value?.setPointerCapture?.(event.pointerId)
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })
  const item = keyed.value.find(s => s.key === dragKey)
  if (pointers.size === 2 && item) {
    gestureStart = { ...pointsAngleDist(...pointers.values()), scale: item.scale, rotate: item.rotate }
    moved = true
  }
}
function onDrag(event) {
  if (dragKey === null || !board.value || !pointers.has(event.pointerId)) return
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })
  const item = keyed.value.find(s => s.key === dragKey)
  if (!item) return

  if (pointers.size === 2 && gestureStart) {
    const now = pointsAngleDist(...pointers.values())
    item.scale = Math.max(0.5, Math.min(2.5, gestureStart.scale * (now.dist / (gestureStart.dist || 1))))
    item.rotate = Math.max(-180, Math.min(180, gestureStart.rotate + (now.angle - gestureStart.angle)))
    return
  }
  if (!moved && Math.hypot(event.clientX - downAt.x, event.clientY - downAt.y) < MOVE_SLOP) return
  moved = true
  const p = boardPoint(event.clientX, event.clientY)
  item.x = p.x
  item.y = p.y
}
function endDrag(event) {
  pointers.delete(event.pointerId)
  if (pointers.size < 2) gestureStart = null
  if (pointers.size > 0 || dragKey === null) return
  const key = dragKey
  dragKey = null
  if (moved) {
    sparkle()
    scheduleSave()
    return
  }
  // a tap: the sticker wiggles and Csillám names it
  tapped.value = null
  requestAnimationFrame(() => (tapped.value = key))
  later(() => {
    if (tapped.value === key) tapped.value = null
  }, 700)
  const badge = badgeById.value[keyed.value.find(s => s.key === key)?.badge]
  if (badge) emit('say', badge.name)
}

/** "Életre kel!": every sticker dances, one after another, and the picture sparkles. */
function bringToLife() {
  if (alive.value || !keyed.value.length) return
  selected.value = null
  alive.value = true
  fanfare()
  const box = board.value?.getBoundingClientRect()
  if (box) burst({ x: box.left + box.width / 2, y: box.top + box.height / 2 }, { pieces: 20, reach: box.width / 2 })
  later(() => (alive.value = false), 4200)
}
</script>

<template>
  <div class="scene-editor">
    <div class="backdrops" role="radiogroup" :aria-label="t('rewards.sceneBackdrop')">
      <button
        v-for="bg in backgrounds"
        :key="bg.id"
        type="button"
        class="backdrop-pick"
        :class="{ 'backdrop-pick--on': background === bg.id }"
        role="radio"
        :aria-checked="background === bg.id"
        :aria-label="bg.name"
        @click="chooseBackground(bg.id)"
      >
        <span class="thumb"><SceneBackdrop :scene="bg.id" mini /></span>
        <small class="thumb-name">{{ bg.name }}</small>
      </button>
    </div>

    <div
      ref="board"
      class="board"
      :class="{ 'board--aiming': aiming, 'board--alive': alive }"
      @pointermove="onDrag"
      @pointerup="endDrag"
      @pointercancel="endDrag"
      @pointerdown="onBoardPointerDown"
    >
      <SceneBackdrop :scene="background" />
      <p v-if="!keyed.length" class="board-hint">{{ t('rewards.sceneEmpty') }}</p>
      <button
        v-for="(s, i) in keyed"
        :key="s.key"
        type="button"
        class="placed"
        :class="{ 'placed--selected': selected === s.key }"
        :style="{
          left: `${s.x}%`,
          top: `${s.y}%`,
          transform: `translate(-50%, -50%) rotate(${s.rotate}deg) scale(${s.scale})`,
          '--i': i,
        }"
        :aria-label="
          selected === s.key
            ? t('rewards.sceneSelected', { name: badgeById[s.badge]?.name || '' })
            : t('rewards.sceneRemove', { name: badgeById[s.badge]?.name || '' })
        "
        @pointerdown.stop="startDrag(s.key, $event)"
        @dblclick="removeSticker(s.key)"
      >
        <span class="wobble" :class="{ 'wobble--landed': landed === s.key, 'wobble--tapped': tapped === s.key }">
          <EmojiArt class="placed-art" :char="badgeById[s.badge]?.emoji || '⭐'" />
        </span>
      </button>

      <div v-if="selectedSticker && !alive" class="sticker-controls" :style="{ left: `${selectedSticker.x}%`, top: `${selectedSticker.y}%` }">
        <button type="button" class="ctrl" :aria-label="t('rewards.sceneSmaller')" @click.stop="nudgeScale(-0.15)"><EmojiArt :char="ICONS.zoomOut" /></button>
        <button type="button" class="ctrl" :aria-label="t('rewards.sceneRotateLeft')" @click.stop="nudgeRotate(-15)"><EmojiArt :char="ICONS.rotateLeft" /></button>
        <button type="button" class="ctrl ctrl--danger" :aria-label="t('rewards.sceneDelete')" @click.stop="removeSticker(selectedSticker.key)"><EmojiArt :char="ICONS.trash" /></button>
        <button type="button" class="ctrl" :aria-label="t('rewards.sceneRotateRight')" @click.stop="nudgeRotate(15)"><EmojiArt :char="ICONS.rotateRight" /></button>
        <button type="button" class="ctrl" :aria-label="t('rewards.sceneBigger')" @click.stop="nudgeScale(0.15)"><EmojiArt :char="ICONS.zoomIn" /></button>
      </div>
    </div>


    <div class="tray">
      <button
        v-for="badge in earnedBadges"
        :key="badge.id"
        type="button"
        class="tray-item"
        :disabled="full"
        :aria-label="badge.name"
        @pointerdown="trayDown($event, badge)"
        @pointermove="trayMove"
        @pointerup="trayUp"
        @pointercancel="trayCancel"
      >
        <EmojiArt class="tray-art" :char="badge.emoji" />
      </button>
      <p v-if="!earnedBadges.length" class="tray-empty">{{ t('rewards.trayEmpty') }}</p>
    </div>
    <div class="scene-actions">
      <button type="button" class="alive-btn" :disabled="!keyed.length || alive" @click="bringToLife">
        <EmojiArt :char="ICONS.magic" /> {{ t('rewards.bringToLife') }}
      </button>
    </div>
    <p class="tray-hint">{{ full ? t('rewards.sceneFull') : t('rewards.sceneHint') }}</p>
  </div>
</template>

<style scoped>
.scene-editor {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
/* the backgrounds as little pictures to pick from */
.backdrops {
  display: flex;
  gap: 8px;
  padding: 2px 2px 6px;
  overflow-x: auto;
  scrollbar-width: none;
}
.backdrop-pick {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  transition: transform 0.38s var(--bz-spring);
}
.backdrop-pick:active {
  transform: scale(0.92);
  transition-duration: 0.07s;
}
.thumb {
  position: relative;
  display: block;
  width: 76px;
  aspect-ratio: 4 / 3;
  overflow: hidden;
  border: 4px solid var(--bz-card);
  border-radius: 14px;
  box-shadow: var(--bz-shadow-sm);
}
.backdrop-pick--on .thumb {
  border-color: var(--bz-sun);
  transform: translateY(-3px);
}
.thumb-name {
  font-size: 13px;
  font-weight: 800;
}
.board {
  position: relative;
  width: 100%;
  aspect-ratio: 4 / 3;
  border: 6px solid var(--bz-card);
  border-radius: var(--bz-radius-lg);
  overflow: hidden;
  box-shadow: var(--bz-shadow-lg);
  touch-action: none;
  background: var(--bz-soft);
  transition: box-shadow 0.2s;
}
/* a sticker carried over from the tray: the picture lights up to take it */
.board--aiming {
  box-shadow:
    0 0 0 6px var(--bz-sun),
    var(--bz-shadow-lg);
}
.board-hint {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  margin: 0;
  padding: 0 20px;
  text-align: center;
  color: #3b1f4a;
  font-weight: 800;
  text-shadow: 0 1px 0 rgba(255, 255, 255, 0.8);
  pointer-events: none;
}
.placed {
  position: absolute;
  display: grid;
  place-items: center;
  width: 15%;
  aspect-ratio: 1;
  cursor: grab;
  touch-action: none;
}
.placed:active {
  cursor: grabbing;
}
.wobble {
  display: grid;
  place-items: center;
}
/* a real sticker: white die-cut edge and a soft shadow */
.placed-art {
  font-size: clamp(26px, 7vw, 46px);
  filter: drop-shadow(2px 0 0 #fff) drop-shadow(-2px 0 0 #fff) drop-shadow(0 2px 0 #fff) drop-shadow(0 -2px 0 #fff)
    drop-shadow(0 4px 3px rgba(0, 0, 0, 0.25));
}
.placed--selected .placed-art {
  filter: drop-shadow(2px 0 0 #fff) drop-shadow(-2px 0 0 #fff) drop-shadow(0 2px 0 #fff) drop-shadow(0 -2px 0 #fff)
    drop-shadow(0 0 5px var(--bz-sun)) drop-shadow(0 4px 3px rgba(0, 0, 0, 0.25));
}
.wobble--landed {
  animation: land 0.55s var(--bz-spring);
}
.wobble--tapped {
  animation: wiggle 0.6s ease-in-out;
}
.board--alive .wobble {
  animation: dance 0.9s ease-in-out infinite alternate;
  animation-delay: calc(var(--i) * -0.23s);
}
.sticker-controls {
  position: absolute;
  z-index: 5;
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
  transform: translate(-50%, calc(-100% - 34px));
  white-space: nowrap;
  pointer-events: auto;
}
.ctrl {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--bz-soft);
  font-size: 15px;
}
.ctrl--danger {
  background: color-mix(in srgb, var(--bz-coral) 25%, var(--bz-soft));
}
.scene-actions {
  display: flex;
  justify-content: center;
}
.alive-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 22px;
  border-radius: var(--bz-radius-pill);
  background: linear-gradient(135deg, #b9a6ff, #ff9ec7);
  color: #fff;
  font-size: var(--bz-text-md);
  font-weight: 800;
  text-shadow: 0 2px 0 rgba(59, 31, 74, 0.25);
  box-shadow: var(--bz-shadow);
  transition: transform 0.38s var(--bz-spring);
}
.alive-btn:active:not(:disabled) {
  transform: scale(0.92);
  transition-duration: 0.07s;
}
.alive-btn:disabled {
  opacity: 0.5;
}
.tray-hint {
  margin: 0;
  text-align: center;
  color: var(--bz-muted);
  font-size: var(--bz-text-sm);
}
/* right under the picture, one row that swipes sideways: a short way up for a sticker */
.tray {
  display: flex;
  gap: 10px;
  padding: 12px;
  overflow-x: auto;
  scrollbar-width: none;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
}
.tray-item {
  flex: none;
  display: grid;
  place-items: center;
  width: 58px;
  height: 58px;
  border-radius: 50%;
  background: var(--bz-soft);
  font-size: 32px;
  box-shadow: var(--bz-shadow-sm);
  /* the page may scroll sideways off a tray sticker, not up and down: that's a drag to the picture */
  touch-action: pan-x;
  transition: transform 0.38s var(--bz-spring);
}
.tray-item:active:not(:disabled) {
  transform: scale(0.9);
  transition-duration: 0.07s;
}
.tray-item:disabled {
  opacity: 0.4;
}
.tray-empty {
  margin: 0;
  color: var(--bz-muted);
}
@keyframes land {
  from {
    transform: scale(1.8);
    opacity: 0.4;
  }
  50% {
    transform: scale(0.8);
    opacity: 1;
  }
}
@keyframes wiggle {
  20% {
    transform: rotate(-14deg) scale(1.15);
  }
  45% {
    transform: rotate(12deg) scale(1.15);
  }
  70% {
    transform: rotate(-6deg);
  }
}
@keyframes dance {
  from {
    transform: translateY(0) rotate(-10deg);
  }
  to {
    transform: translateY(-18%) rotate(10deg) scale(1.08);
  }
}
</style>
