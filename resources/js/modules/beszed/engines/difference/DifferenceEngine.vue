<script setup>
import { ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useShake } from '../../composables/useShake'
import { t } from '../../i18n'
import { engineEmits, engineProps } from '../contract'

/**
 * Spot the difference (Mi a különbség?): two panels of pictures, alike but for
 * one cell. Tapping that cell on either panel wins and rings it on both; any
 * other cell shakes and counts as a try.
 * data: DifferenceData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const SIDES = ['left', 'right']

const found = ref(false)
const { shaking, shake } = useShake()

function tap(side, i) {
  if (props.locked || found.value) return
  if (i === props.data.diff) {
    found.value = true
    emit('answer', { correct: true, say: props.data.onCorrect })
    return
  }
  shake(`${side}-${i}`)
  emit('answer', { correct: false, say: props.data.onWrong })
}
</script>

<template>
  <div class="panels" :style="{ '--cols': data.cols }">
    <div v-for="side in SIDES" :key="side" class="panel" role="group" :aria-label="t(`difference.${side}`)">
      <button
        v-for="(char, i) in data[side]"
        :key="i"
        type="button"
        class="cell"
        :class="{ 'cell--found': found && i === data.diff, 'cell--shake': shaking === `${side}-${i}` }"
        :aria-label="t('difference.cell', { n: i + 1 })"
        @click="tap(side, i)"
      >
        <EmojiArt class="art" :char="char" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.panels {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: clamp(10px, 3vw, 22px);
  width: 100%;
  max-width: 720px;
}
.panel {
  display: grid;
  grid-template-columns: repeat(var(--cols), 1fr);
  gap: clamp(4px, 1.4vw, 10px);
  padding: clamp(8px, 2vw, 14px);
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
}
.cell {
  position: relative;
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  border-radius: var(--bz-radius-sm);
  background: var(--bz-soft);
  transition: transform 0.08s;
}
.cell:active {
  transform: scale(0.94);
}
.art {
  font-size: clamp(26px, 7vw, 54px);
  line-height: 1;
}
/* the ring around the difference: a separate layer, so only opacity/transform animate */
.cell::after {
  content: '';
  position: absolute;
  inset: -3px;
  border: 5px solid var(--bz-leaf);
  border-radius: 50%;
  opacity: 0;
  transform: scale(1.4);
  pointer-events: none;
}
.cell--found::after {
  opacity: 1;
  transform: scale(1);
  transition: opacity 0.2s, transform 0.35s cubic-bezier(0.2, 1.5, 0.4, 1);
}
.cell--shake {
  animation: shake 0.45s;
}
@keyframes shake {
  20% {
    transform: translateX(-6px);
  }
  40% {
    transform: translateX(6px);
  }
  60% {
    transform: translateX(-4px);
  }
  80% {
    transform: translateX(4px);
  }
}
</style>
