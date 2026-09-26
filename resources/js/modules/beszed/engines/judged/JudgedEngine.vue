<script setup>
import { ref } from 'vue'
import { assessPronunciation } from '../../api'
import BzButton from '../../components/ui/BzButton.vue'
import PictureCard from '../../components/ui/PictureCard.vue'
import { useRecorder } from '../../composables/useRecorder'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { useMetaStore } from '../../stores/meta'
import { errorMessage } from '../../utils/errors'
import { engineEmits, engineProps } from '../contract'

/**
 * The child repeats a sentence out loud. When server pronunciation assessment
 * is configured it scores the attempt automatically; otherwise (or if it fails,
 * or the mic isn't available) the parent judges it by ear, as before.
 * "Darabonként" says it chunk by chunk, then all in one go.
 * data: JudgedData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const CHUNKS_DELAY_MS = 2400

const chunk = ref(0)
const showSkip = ref(false)
const { later } = useTimers()

const meta = useMetaStore()
const { supported: micSupported, recordingKey, start, stop } = useRecorder()
const assessing = ref(false)
const assessError = ref('')

function scoreOf(tries) {
  return tries <= 1 ? t('judged.approved') : t('judged.together')
}

async function submitAttempt(blob, ext) {
  assessing.value = true
  assessError.value = ''
  try {
    const result = await assessPronunciation(props.data.text, blob, `mondd.${ext}`)
    if (!result.available) {
      assessError.value = t('judged.micUnavailable', { folder: ICONS.folder })
      return
    }
    emit('answer', { correct: result.correct, say: scoreOf(result.tries), tries: result.tries })
  } catch (e) {
    assessError.value = errorMessage(e, t('judged.micUnavailable', { folder: ICONS.folder }))
  } finally {
    assessing.value = false
  }
}

async function toggleRecording() {
  if (props.locked || assessing.value) return
  if (recordingKey.value) return stop()
  assessError.value = ''
  const started = await start('mondd', submitAttempt)
  if (!started) assessError.value = t('judged.micUnavailable', { folder: ICONS.folder })
}

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
  <template v-if="meta.serverStt && micSupported">
    <div class="bz-row">
      <BzButton
        variant="primary"
        :icon="recordingKey ? ICONS.stop : ICONS.mic"
        :pulse="Boolean(recordingKey)"
        :disabled="assessing"
        @click="toggleRecording"
      >
        {{ assessing ? t('judged.scoring') : recordingKey ? t('judged.recording') : t('judged.recordAttempt') }}
      </BzButton>
    </div>
    <p v-if="assessError" class="parent-hint">{{ assessError }}</p>
  </template>

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
