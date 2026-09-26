<script setup>
import { computed, ref } from 'vue'
import { t } from '../../i18n'
import { beep, sparkle } from '../../services/audio/sfx'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * The sticker scene: pick a backdrop, then drag earned stickers onto it to build
 * a little picture. Positions are saved (debounced) through `@change`.
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
const emit = defineEmits(['change'])

const board = ref(null)
/** Local working copy so dragging feels instant; pushed out (debounced) via `change`. */
const stickers = ref(props.scene.stickers.map(s => ({ ...s })))
const background = ref(props.scene.background)
let nextKey = 0
const keyed = ref(stickers.value.map(s => ({ ...s, key: nextKey++ })))

const badgeById = computed(() => Object.fromEntries(props.earnedBadges.map(b => [b.id, b])))
const full = computed(() => keyed.value.length >= props.maxStickers)

let saveTimer = null
function scheduleSave() {
  stickers.value = keyed.value.map(({ key, ...s }) => s)
  clearTimeout(saveTimer)
  saveTimer = setTimeout(() => emit('change', { background: background.value, stickers: stickers.value }), 400)
}

function chooseBackground(id) {
  background.value = background.value === id ? null : id
  scheduleSave()
}

function boardPoint(clientX, clientY) {
  const rect = board.value.getBoundingClientRect()
  const x = ((clientX - rect.left) / rect.width) * 100
  const y = ((clientY - rect.top) / rect.height) * 100
  return { x: Math.max(2, Math.min(98, x)), y: Math.max(2, Math.min(98, y)) }
}

/** Tap a tray sticker to drop it in the middle-ish of the board (works without drag). */
function addSticker(badge) {
  if (full.value) return
  const jitter = () => 38 + Math.random() * 24
  keyed.value.push({ key: nextKey++, badge: badge.id, x: jitter(), y: jitter(), rotate: Math.round(Math.random() * 30 - 15) })
  beep(420)
  scheduleSave()
}

function removeSticker(key) {
  keyed.value = keyed.value.filter(s => s.key !== key)
  scheduleSave()
}

let dragKey = null
function startDrag(key, event) {
  dragKey = key
  event.target.setPointerCapture?.(event.pointerId)
}
function onDrag(event) {
  if (dragKey === null || !board.value) return
  const item = keyed.value.find(s => s.key === dragKey)
  if (!item) return
  const p = boardPoint(event.clientX, event.clientY)
  item.x = p.x
  item.y = p.y
}
function endDrag() {
  if (dragKey === null) return
  dragKey = null
  sparkle()
  scheduleSave()
}

/** Dropping a tray sticker straight onto the board (desktop drag-and-drop). */
function onBoardDrop(event) {
  const badgeId = event.dataTransfer?.getData('text/badge')
  if (!badgeId || !badgeById.value[badgeId] || full.value) return
  const p = boardPoint(event.clientX, event.clientY)
  keyed.value.push({ key: nextKey++, badge: badgeId, x: p.x, y: p.y, rotate: Math.round(Math.random() * 30 - 15) })
  beep(420)
  scheduleSave()
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
        @click="chooseBackground(bg.id)"
      >
        <EmojiArt :char="bg.emoji" /> {{ bg.name }}
      </button>
    </div>

    <div
      ref="board"
      class="board"
      :class="`board--${background || 'blank'}`"
      @pointermove="onDrag"
      @pointerup="endDrag"
      @pointercancel="endDrag"
      @dragover.prevent
      @drop.prevent="onBoardDrop"
    >
      <p v-if="!keyed.length" class="board-hint">{{ t('rewards.sceneEmpty') }}</p>
      <button
        v-for="s in keyed"
        :key="s.key"
        type="button"
        class="placed"
        :style="{ left: `${s.x}%`, top: `${s.y}%`, transform: `translate(-50%, -50%) rotate(${s.rotate}deg)` }"
        :aria-label="t('rewards.sceneRemove', { name: badgeById[s.badge]?.name || '' })"
        @pointerdown="startDrag(s.key, $event)"
        @dblclick="removeSticker(s.key)"
      >
        <EmojiArt :char="badgeById[s.badge]?.emoji || '⭐'" />
      </button>
    </div>

    <p class="tray-hint">{{ full ? t('rewards.sceneFull') : t('rewards.sceneHint') }}</p>
    <div class="tray">
      <button
        v-for="badge in earnedBadges"
        :key="badge.id"
        type="button"
        class="tray-item"
        draggable="true"
        :disabled="full"
        @click="addSticker(badge)"
        @dragstart="$event.dataTransfer.setData('text/badge', badge.id)"
      >
        <EmojiArt :char="badge.emoji" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.scene-editor {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.backdrops {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.backdrop-pick {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: 3px solid transparent;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-weight: 700;
  font-size: var(--bz-text-sm);
  box-shadow: var(--bz-shadow-sm);
}
.backdrop-pick--on {
  border-color: var(--bz-leaf);
  background: color-mix(in srgb, var(--bz-leaf) 16%, var(--bz-card));
}
.board {
  position: relative;
  width: 100%;
  aspect-ratio: 4 / 3;
  border-radius: var(--bz-radius-lg);
  overflow: hidden;
  box-shadow: var(--bz-shadow);
  touch-action: none;
  background: var(--bz-soft);
}
.board--blank {
  background: repeating-linear-gradient(45deg, var(--bz-soft), var(--bz-soft) 10px, var(--bz-card) 10px, var(--bz-card) 20px);
}
.board--meadow {
  background: linear-gradient(#cdefae 0%, #cdefae 60%, #eaf7c9 60%, #eaf7c9 100%);
}
.board--sky {
  background: linear-gradient(#2b2a5c, #4b3f80);
}
.board--castle {
  background: linear-gradient(#ffd9ea, #d8c8ff);
}
.board-hint {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  margin: 0;
  padding: 0 20px;
  text-align: center;
  color: var(--bz-muted);
  font-weight: 700;
  pointer-events: none;
}
.placed {
  position: absolute;
  display: grid;
  place-items: center;
  width: 15%;
  aspect-ratio: 1;
  font-size: clamp(22px, 6vw, 40px);
  cursor: grab;
  filter: drop-shadow(0 3px 2px rgba(0, 0, 0, 0.2));
  touch-action: none;
}
.placed:active {
  cursor: grabbing;
}
.tray-hint {
  margin: 0;
  color: var(--bz-muted);
  font-size: var(--bz-text-sm);
}
.tray {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 10px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
}
.tray-item {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: var(--bz-soft);
  font-size: 30px;
  box-shadow: var(--bz-shadow-sm);
}
.tray-item:disabled {
  opacity: 0.4;
}
</style>
