<script setup>
import { ref } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import PictureCard from '../../components/ui/PictureCard.vue'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { engineEmits, engineProps } from '../contract'

/**
 * The child repeats a sentence out loud; the parent judges it (Mondd utánam).
 * "Darabonként" says it chunk by chunk, then all in one go.
 * data: JudgedData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const CHUNKS_DELAY_MS = 2400

const chunk = ref(0)
const showSkip = ref(false)
const { later } = useTimers()

function sayNextChunk() {
  const chunks = props.data.chunks
  if (chunk.value < chunks.length) {
    emit('say', chunks[chunk.value++])
    return
  }
  chunk.value = 0
  emit('say', t('judged.oneGo', { text: props.data.text }))
}

function approve() {
  if (!props.locked) emit('answer', { correct: true, say: t('judged.approved') })
}

function notYet() {
  if (props.locked) return
  showSkip.value = true
  chunk.value = 0
  emit('answer', { correct: false, say: t('judged.together') })
  later(sayNextChunk, CHUNKS_DELAY_MS)
}
</script>

<template>
  <PictureCard :emoji="data.emoji" :label="data.levelLabel" />
  <div class="bz-row">
    <BzButton :icon="ICONS.speaker" @click="emit('say', data.text)">{{ t('judged.again') }}</BzButton>
    <BzButton :icon="ICONS.turtle" @click="sayNextChunk">
      {{ chunk ? `${chunk}/${data.chunks.length}` : t('judged.byChunks') }}
    </BzButton>
  </div>
  <p class="parent-hint">{{ t('judged.parentHint') }}</p>
  <div class="bz-row">
    <BzButton variant="primary" :icon="ICONS.thumbsUp" @click="approve">{{ t('judged.approve') }}</BzButton>
    <BzButton :icon="ICONS.again" @click="notYet">{{ t('judged.notYet') }}</BzButton>
    <BzButton v-if="showSkip" @click="emit('skip')">{{ t('common.next') }}</BzButton>
  </div>
</template>

<style scoped>
.parent-hint {
  margin: 0;
  font-size: var(--bz-text-sm);
  color: var(--bz-muted);
  text-align: center;
}
</style>
