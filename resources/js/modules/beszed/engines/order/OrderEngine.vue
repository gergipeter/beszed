<script setup>
import { computed, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useDrag } from '../../composables/useDrag'
import { useShake } from '../../composables/useShake'
import { t } from '../../i18n'
import { beep } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'

/**
 * Put pictures in order (Kicsitől a nagyig, Mi történt előbb?): drag each one
 * to the next empty slot (it pulls like a magnet) or just tap it; the right
 * next one moves up into the row, and Csillám says its label (a story step). A wrong one shakes and Csillám says why. The win
 * is graded by mistakes, like sorting.
 * data: OrderData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

/** ids already in the row, in order */
const placed = ref([])
let mistakes = 0
const { shaking, shake } = useShake()

const byId = computed(() => Object.fromEntries(props.data.items.map(item => [item.id, item])))
const slots = computed(() => props.data.order.map((id, i) => (i < placed.value.length ? byId.value[placed.value[i]] : null)))
/** Sizes to order (the pictures have a scale): they stand on a wooden shelf. */
const sizes = computed(() => props.data.items.some(item => item.scale))
const grade = () => (mistakes === 0 ? 1 : mistakes <= 2 ? 2 : 3)
/** A picture's own size, for the size-ordering game; the others fill their tile. */
const sized = item => (item.scale ? { '--scale': item.scale } : null)

/** @returns {boolean} it was the right next one */
function tap(item) {
  if (props.locked) return false
  if (item.id !== props.data.order[placed.value.length]) {
    mistakes++
    shake(item.id)
    emit('say', props.data.wrong)
    return false
  }
  placed.value = [...placed.value, item.id]
  beep(440 + placed.value.length * 90, 0.1)
  if (placed.value.length === props.data.order.length) {
    emit('answer', { correct: true, say: props.data.onCorrect, tries: grade() })
  } else if (item.label) {
    emit('say', item.label)
  }
  return true
}

// dragged onto the next empty slot: the same as tapping it
const row = ref(null)
const drag = useDrag({ root: row, onDrop: item => tap(item) })
</script>

<template>
  <ol ref="row" class="row" :class="{ 'row--arrows': data.arrows, 'row--shelf': sizes }" :style="{ '--n': data.order.length }" :aria-label="t('order.row')">
    <li
      v-for="(item, i) in slots"
      :key="i"
      class="slot"
      :class="{ 'slot--filled': item, 'slot--next': drag.over.value && i === placed.length }"
      :data-drop="i === placed.length ? 'next' : undefined"
    >
      <Transition name="land">
        <span v-if="item" class="placed">
          <EmojiArt class="art" :style="sized(item)" :char="item.emoji" />
          <small v-if="item.label" class="label">{{ item.label }}</small>
        </span>
        <span v-else class="number" aria-hidden="true">{{ i + 1 }}</span>
      </Transition>
    </li>
  </ol>

  <!-- A placed picture leaves its gap, so the others don't jump around under the child's finger. -->
  <div class="pool" :style="{ '--n': data.order.length }">
    <button
      v-for="item in data.items"
      :key="item.id"
      type="button"
      class="piece bz-draggable"
      data-peek
      :class="{ 'piece--gone': placed.includes(item.id), 'piece--shake': shaking === item.id }"
      :disabled="placed.includes(item.id)"
      :aria-label="item.label || t('order.piece')"
      @pointerdown="drag.start($event, item)"
      @click="tap(item)"
    >
      <EmojiArt class="art" :style="sized(item)" :char="item.emoji" />
      <small v-if="item.label" class="label">{{ item.label }}</small>
    </button>
  </div>
</template>

<style scoped>
.row {
  display: grid;
  grid-template-columns: repeat(var(--n), 1fr);
  gap: clamp(6px, 2vw, 14px);
  width: 100%;
  max-width: calc(var(--n) * 130px);
  margin: 0;
  padding: 0;
  list-style: none;
}
.slot {
  position: relative;
  display: grid;
  place-items: center;
  aspect-ratio: 3 / 4;
  border: 4px dashed color-mix(in srgb, var(--bz-guide) 55%, transparent);
  border-radius: var(--bz-radius);
  background: color-mix(in srgb, var(--bz-card) 60%, transparent);
}
/* the slot a dragged picture is about to fall into */
.slot--next {
  border-color: var(--bz-leaf);
  transform: scale(1.06);
  transition: transform 0.25s var(--bz-spring);
}
.slot--filled {
  border-style: solid;
  border-color: var(--bz-leaf);
  background: var(--bz-card);
}
/* story order: little arrows between the slots, "first → then → last" */
.row--arrows .slot + .slot::before {
  content: '›';
  position: absolute;
  left: calc(-0.5 * clamp(6px, 2vw, 14px) - 0.3em);
  top: 50%;
  transform: translateY(-50%);
  color: var(--bz-guide);
  font-size: 26px;
  font-weight: 800;
}
/* sizes: the slots are places on a wooden shelf, the pictures stand on it */
.row--shelf {
  position: relative;
  padding-bottom: 14px;
}
.row--shelf::after {
  content: '';
  position: absolute;
  left: -10px;
  right: -10px;
  bottom: 0;
  height: 16px;
  border-radius: 6px;
  background: linear-gradient(to bottom, #b77a4f, #8a5a3c);
  box-shadow: 0 6px 0 rgba(59, 31, 74, 0.2);
}
.row--shelf .slot {
  place-items: end center;
  padding-bottom: 6px;
  border-bottom-style: none;
  border-radius: var(--bz-radius) var(--bz-radius) 4px 4px;
}
.number {
  font-size: 30px;
  font-weight: 800;
  color: var(--bz-guide);
  opacity: 0.6;
}
.placed,
.piece {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
}
.art {
  /* --scale shrinks the size-ordering pictures; story pictures keep the full size */
  font-size: calc(clamp(34px, 12vw, 64px) * var(--scale, 1));
  line-height: 1;
}
.label {
  padding: 0 4px;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.15;
  text-align: center;
  color: var(--bz-muted);
}
.pool {
  display: grid;
  grid-template-columns: repeat(var(--n), 1fr);
  gap: clamp(6px, 2vw, 14px);
  width: 100%;
  max-width: calc(var(--n) * 130px);
}
.piece {
  aspect-ratio: 3 / 4;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
  transition: transform 0.08s;
}
.piece:active {
  transform: translateY(4px);
}
.piece--gone {
  visibility: hidden;
}
.piece--shake {
  animation: shake 0.45s;
}
.land-enter-active {
  transition: transform 0.32s cubic-bezier(0.2, 1.5, 0.4, 1), opacity 0.2s;
}
.land-enter-from {
  transform: translateY(40px) scale(0.6);
  opacity: 0;
}
.land-leave-active {
  display: none;
}
@keyframes shake {
  20% {
    transform: translateX(-10px);
  }
  40% {
    transform: translateX(10px);
  }
  60% {
    transform: translateX(-6px);
  }
  80% {
    transform: translateX(6px);
  }
}
</style>
