<script setup>
import { ref } from 'vue'
import RecordingRow from '../components/recordings/RecordingRow.vue'
import BzNotice from '../components/ui/BzNotice.vue'
import EmojiArt from '../components/ui/EmojiArt.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useModuleContext } from '../composables/useModuleContext'
import { useRecorder } from '../composables/useRecorder'
import { ICONS } from '../config/icons'
import { t } from '../i18n'
import { useGuideStore } from '../stores/guide'
import { useMetaStore } from '../stores/meta'
import { useRecordingsStore } from '../stores/recordings'
import { errorMessage } from '../utils/errors'
import { fileExtension } from '../utils/text'

/** Parents record Csillám's lines in their own voice. */
const { childId } = useModuleContext()
const meta = useMetaStore()
const recordings = useRecordingsStore()
const guide = useGuideStore()
const { supported, recordingKey, start, stop } = useRecorder()

const busyKey = ref(null)
const message = ref('')

async function save(key, blob, ext) {
  busyKey.value = key
  message.value = ''
  try {
    await recordings.upload(key, blob, `${key}.${ext}`)
    guide.speak([{ rec: key }])
  } catch (e) {
    message.value = errorMessage(e, t('recordings.uploadFailed'))
  } finally {
    busyKey.value = null
  }
}

async function toggle(key) {
  if (recordingKey.value === key) return stop()
  guide.stop()
  message.value = ''
  const started = await start(key, (blob, ext) => save(key, blob, ext))
  if (!started) message.value = t('recordings.micUnavailable', { folder: ICONS.folder })
}

function upload(key, file) {
  save(key, file, fileExtension(file.name, 'm4a'))
}

async function remove(key) {
  guide.stop()
  message.value = ''
  try {
    await recordings.remove(key)
  } catch (e) {
    message.value = errorMessage(e, t('recordings.deleteFailed'))
  }
}
</script>

<template>
  <PageHeader :title="t('recordings.title')" :back-to="{ name: 'beszed.hub', params: { childId } }" />

  <BzNotice class="love">
    <EmojiArt :char="ICONS.family" /> {{ t('recordings.loveVoice') }}
  </BzNotice>
  <BzNotice>{{ t('recordings.intro', { record: ICONS.record, stop: ICONS.stop }) }}</BzNotice>
  <BzNotice v-if="!supported" tone="warn">{{ t('recordings.unsupported', { folder: ICONS.folder }) }}</BzNotice>
  <BzNotice v-if="message" tone="warn">{{ message }}</BzNotice>

  <RecordingRow
    v-for="line in meta.lines"
    :key="line.key"
    :line="line"
    :recorded="recordings.has(line.key)"
    :recording="recordingKey === line.key"
    :busy="busyKey === line.key"
    @toggle="toggle(line.key)"
    @play="guide.speak([{ rec: line.key }])"
    @remove="remove(line.key)"
    @file="file => upload(line.key, file)"
  />
</template>
