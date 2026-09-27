<script setup>
import { computed, nextTick, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useDrag } from '../../composables/useDrag'
import { t } from '../../i18n'
import { beep } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'

/**
 * Picture puzzle (Kirakó): drag a piece onto another, or tap two pieces, to
 * swap them until the picture is whole. A piece in its right place locks. The win is graded by wasted swaps
 * (swaps that put neither piece in place).
 * data: PuzzleData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

/** order[position] = the piece lying there */
const order = ref([...props.data.pieces])
const selected = ref(null)
let wasted = 0

const solved = computed(() => order.value.every((piece, pos) => piece === pos))
const grade = () => (wasted <= 1 ? 1 : wasted <= 3 ? 2 : 3)

function tap(pos) {
  if (props.locked || solved.value || order.value[pos] === pos) return
  const from = selected.value
  if (from === null || from === pos) {
    selected.value = from === pos ? null : pos
    beep(480, 0.05)
    return
  }

  swap(from, pos)
}

function swap(from, pos) {
  const next = [...order.value]
  ;[next[from], next[pos]] = [next[pos], next[from]]
  order.value = next
  selected.value = null

  const placed = Number(next[from] === from) + Number(next[pos] === pos)
  if (!placed) wasted++
  beep(placed ? 640 : 300, 0.09)
  if (solved.value) emit('answer', { correct: true, say: props.data.onCorrect, tries: grade() })
}

/**
 * Dragged onto another piece: they swap. The dragged one is already where it
 * lands, so that swap doesn't slide (the other one pops into its new place).
 */
const board = ref(null)
const slide = ref(true)
const drag = useDrag({
  root: board,
  onDrop: async (from, to) => {
    const pos = Number(to)
    if (props.locked || solved.value || pos === from || order.value[pos] === pos) return false
    slide.value = false
    swap(from, pos)
    await nextTick()
    slide.value = true
    return true
  },
})
function grab(event, pos) {
  if (!props.locked && !solved.value && order.value[pos] !== pos) drag.start(event, pos)
}

/** Shows the part of the picture that belongs to `piece`. */
function slice(piece) {
  const { cols, rows } = props.data
  return {
    width: `${cols * 100}%`,
    height: `${rows * 100}%`,
    left: `${-(piece % cols) * 100}%`,
    top: `${-Math.floor(piece / cols) * 100}%`,
  }
}
</script>

<template>
  <div class="preview" role="img" :aria-label="t('puzzle.preview')"><EmojiArt :char="data.emoji" /></div>

  <div ref="board" class="board-wrap">
    <TransitionGroup tag="div" :name="slide ? 'swap' : 'none'" class="board" :style="{ '--cols': data.cols, '--rows': data.rows }">
      <button
        v-for="(piece, pos) in order"
        :key="piece"
        type="button"
        class="piece bz-draggable"
        :class="{ 'piece--selected': selected === pos, 'piece--placed': piece === pos && !solved }"
        :data-drop="piece === pos ? undefined : String(pos)"
        :aria-label="t('puzzle.piece', { n: pos + 1 })"
        :aria-pressed="selected === pos"
        @pointerdown="grab($event, pos)"
        @click="tap(pos)"
      >
        <span class="slice" :style="slice(piece)"><EmojiArt :char="data.emoji" /></span>
      </button>
    </TransitionGroup>
    <!-- Solved: the whole picture fades in over the pieces (opacity/transform only). -->
    <Transition name="reveal">
      <div v-if="solved" class="reveal" aria-hidden="true"><EmojiArt :char="data.emoji" /></div>
    </Transition>
  </div>
</template>

<style scoped>
.preview {
  padding: 6px 14px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  font-size: 54px;
  line-height: 1;
  box-shadow: var(--bz-shadow-sm);
}
.board-wrap {
  position: relative;
  width: min(100%, 380px);
  aspect-ratio: 1;
  /* cq units below are relative to the board */
  container-type: size;
}
.board {
  display: grid;
  grid-template-columns: repeat(var(--cols), 1fr);
  grid-template-rows: repeat(var(--rows), 1fr);
  gap: 5px;
  width: 100%;
  height: 100%;
  padding: 8px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
}
.piece {
  position: relative;
  overflow: hidden;
  border-radius: 12px;
  background: var(--bz-soft);
  transition: transform 0.12s ease;
}
/* selection ring is an overlay faded with opacity, not an animated box-shadow */
.piece::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  box-shadow: inset 0 0 0 4px var(--bz-coral);
  opacity: 0;
  transition: opacity 0.12s;
}
.piece--selected {
  z-index: 1;
  transform: scale(0.93);
}
.piece--selected::after {
  opacity: 1;
}
.piece--placed {
  cursor: default;
}
.piece--placed::after {
  box-shadow: inset 0 0 0 3px color-mix(in srgb, var(--bz-leaf) 70%, transparent);
  opacity: 1;
}
.slice {
  position: absolute;
  display: grid;
  place-items: center;
  font-size: 80cqmin;
  line-height: 1;
  pointer-events: none;
}
.swap-move {
  transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.reveal {
  position: absolute;
  inset: 8px;
  display: grid;
  place-items: center;
  border-radius: 20px;
  background: var(--bz-soft);
  font-size: 80cqmin;
  line-height: 1;
}
.reveal-enter-active {
  transition: opacity 0.45s ease, transform 0.45s cubic-bezier(0.2, 1.4, 0.4, 1);
}
.reveal-enter-from {
  opacity: 0;
  transform: scale(0.96);
}
</style>
