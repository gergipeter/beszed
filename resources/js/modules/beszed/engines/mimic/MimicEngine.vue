<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import PictureCard from '../../components/ui/PictureCard.vue'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { beep } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'

/**
 * Mouth exercises in front of a mirror (Szájtorna). The picture swaps between the
 * exercise's moves by itself, slowly, as the thing to copy; the child (with a
 * parent) taps once after each repetition. Nobody is graded: finishing is the win.
 * data: MimicData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const SWAP_MS = 1800

const step = ref(0)
const done = ref(0)
const move = computed(() => props.data.moves[step.value % props.data.moves.length])

let timer = null
onMounted(() => {
  timer = setInterval(() => {
    if (!props.locked) step.value++
  }, SWAP_MS)
})
onBeforeUnmount(() => clearInterval(timer))

function rep() {
  if (props.locked || done.value >= props.data.reps) return
  done.value++
  beep(520, 0.1)
  if (done.value >= props.data.reps) emit('answer', { correct: true, say: props.data.onCorrect, tries: 1 })
}
</script>

<template>
  <p class="mirror">{{ t('mimic.mirror') }}</p>
  <PictureCard :key="step" :emoji="move.emoji" :label="move.label" pressable @click="emit('say', move.label)" />
  <div class="dots" role="img" :aria-label="t('mimic.progress', { done, total: data.reps })">
    <span v-for="n in data.reps" :key="n" class="dot" :class="{ 'dot--on': n <= done }" />
  </div>
  <div class="bz-row">
    <BzButton :icon="ICONS.speaker" @click="emit('replay')">{{ t('mimic.again') }}</BzButton>
    <BzButton variant="primary" :icon="ICONS.done" :disabled="locked" @click="rep">{{ t('mimic.rep') }}</BzButton>
  </div>
</template>

<style scoped>
.mirror {
  margin: 0;
  font-size: var(--bz-text-sm);
  color: var(--bz-muted);
  text-align: center;
}
.dots {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
}
.dot {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--bz-sun);
  opacity: 0.35;
  transition: transform 0.15s, opacity 0.15s;
}
.dot--on {
  opacity: 1;
  transform: scale(1.15);
}
</style>
