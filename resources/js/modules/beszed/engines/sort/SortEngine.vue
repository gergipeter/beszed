<script setup>
import { computed, reactive, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import PictureCard from '../../components/ui/PictureCard.vue'
import { useDrag } from '../../composables/useDrag'
import { useShake } from '../../composables/useShake'
import { t } from '../../i18n'
import { beep } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'

/**
 * Sorting (Válogató): one picture at a time goes into one of two baskets:
 * dragged there (the basket pulls it in like a magnet) or by tapping the basket.
 * A wrong basket shakes, Csillám says why and the picture springs back.
 * The win is graded by mistakes.
 * data: SortData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const index = ref(0)
/** bin id → pictures already in it */
const placed = reactive(Object.fromEntries(props.data.bins.map(b => [b.id, []])))
let mistakes = 0
const { shaking, shake } = useShake()

const current = computed(() => props.data.items[index.value] ?? null)
const grade = () => (mistakes === 0 ? 1 : mistakes <= 2 ? 2 : 3)

/** @returns {boolean} the picture went in */
function drop(bin) {
  const item = current.value
  if (props.locked || !item) return false
  if (item.bin !== bin.id) {
    mistakes++
    shake(bin.id)
    emit('say', item.wrong)
    return false
  }
  placed[bin.id].push(item)
  beep(640, 0.08)
  index.value++
  if (index.value === props.data.items.length) {
    emit('answer', { correct: true, say: props.data.onCorrect, tries: grade() })
  }
  return true
}

// dropped into a basket: it stays where it fell and fades into it
const bins = ref(null)
const drag = useDrag({
  root: bins,
  onDrop: (item, id) => (drop(props.data.bins.find(b => b.id === id)) ? 'keep' : false),
})
</script>

<template>
  <p class="count">{{ t('sort.left', { done: index, total: data.items.length }) }}</p>

  <Transition name="pop" mode="out-in">
    <PictureCard
      v-if="current"
      :key="current.id"
      :emoji="current.emoji"
      :label="current.label"
      pressable
      class="bz-draggable"
      @pointerdown="drag.start($event, current)"
      @click="emit('say', current.label)"
    />
  </Transition>

  <div ref="bins" class="bins">
    <button
      v-for="bin in data.bins"
      :key="bin.id"
      type="button"
      class="bin"
      :class="{ 'bin--shake': shaking === bin.id, 'bin--over': drag.over.value === bin.id }"
      :data-drop="bin.id"
      :aria-label="t('sort.basket', { label: bin.label })"
      @click="drop(bin)"
    >
      <span class="bin-head"><EmojiArt class="bin-icon" :char="bin.emoji" /> {{ bin.label }}</span>
      <TransitionGroup tag="span" name="drop" class="bin-items">
        <EmojiArt v-for="item in placed[bin.id]" :key="item.id" :char="item.emoji" />
      </TransitionGroup>
    </button>
  </div>
</template>

<style scoped>
.count {
  margin: -8px 0 0;
  font-size: var(--bz-text-sm);
  font-weight: 700;
  color: var(--bz-muted);
}
.bins {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  width: 100%;
  max-width: 560px;
}
.bin {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  min-height: 150px;
  padding: 14px 10px 18px;
  border: 4px dashed color-mix(in srgb, var(--bz-guide) 55%, transparent);
  border-radius: 26px 26px 56px 56px;
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
  transition: transform 0.08s;
}
.bin:active {
  transform: translateY(4px);
}
/* a picture dragged near: the basket opens up for it */
.bin--over {
  border-style: solid;
  border-color: var(--bz-leaf);
  transform: scale(1.05);
  transition: transform 0.25s var(--bz-spring);
}
.bin--shake {
  animation: shake 0.45s;
}
.bin-head {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 21px;
  font-weight: 800;
}
.bin-icon {
  font-size: 38px;
}
.bin-items {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 4px;
  font-size: 30px;
}
.pop-enter-active {
  transition: transform 0.3s cubic-bezier(0.2, 1.5, 0.4, 1), opacity 0.2s;
}
.pop-leave-active {
  transition: transform 0.18s ease-in, opacity 0.18s;
}
.pop-enter-from {
  transform: scale(0.4);
  opacity: 0;
}
.pop-leave-to {
  transform: translateY(40px) scale(0.5);
  opacity: 0;
}
.drop-enter-active {
  transition: transform 0.35s cubic-bezier(0.2, 1.5, 0.4, 1);
}
.drop-enter-from {
  transform: translateY(-30px) scale(0.3);
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
