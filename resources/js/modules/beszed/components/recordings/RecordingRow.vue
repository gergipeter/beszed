<script setup>
import { ref } from 'vue'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import BzButton from '../ui/BzButton.vue'
import EmojiArt from '../ui/EmojiArt.vue'

/** One recordable line: its text, and record / play / delete / upload-a-file. */
defineProps({
  /** @type {import('vue').PropType<import('../../types').Line>} */
  line: { type: Object, required: true },
  recorded: { type: Boolean, default: false },
  recording: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle', 'play', 'remove', 'file'])

const fileInput = ref(null)

function onFile(event) {
  const input = event.target
  const file = input.files?.[0]
  if (file) emit('file', file)
  input.value = ''
}
</script>

<template>
  <div class="row" :class="{ 'row--recorded': recorded }">
    <div class="text">
      <b class="label">
        {{ line.label }}
        <span v-if="recorded" class="status"><EmojiArt :char="ICONS.done" /> {{ t('recordings.recorded') }}</span>
      </b>
      <span>{{ line.text }}</span>
    </div>

    <div class="actions">
      <BzButton
        size="sm"
        :variant="recording ? 'danger' : 'soft'"
        :pulse="recording"
        :disabled="busy"
        :icon="recording ? ICONS.stop : busy ? ICONS.busy : ICONS.record"
        :aria-label="recording ? t('recordings.stop') : t('recordings.record')"
        @click="emit('toggle')"
      />
      <BzButton
        size="sm"
        variant="soft"
        :disabled="!recorded"
        :icon="ICONS.play"
        :aria-label="t('recordings.play')"
        @click="emit('play')"
      />
      <BzButton
        size="sm"
        variant="soft"
        :disabled="!recorded"
        :icon="ICONS.trash"
        :aria-label="t('recordings.delete')"
        @click="emit('remove')"
      />
      <BzButton
        size="sm"
        variant="soft"
        :disabled="busy"
        :icon="ICONS.folder"
        :aria-label="t('recordings.pickFile')"
        @click="fileInput.click()"
      />
      <input ref="fileInput" type="file" accept="audio/*" hidden @change="onFile" />
    </div>
  </div>
</template>

<style scoped>
.row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin: 10px 0;
  padding: 10px 12px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
}
.text {
  flex: 1 1 220px;
  display: flex;
  flex-direction: column;
  font-size: var(--bz-text);
  line-height: 1.35;
}
.label {
  font-size: 14px;
  color: var(--bz-muted);
}
.status {
  font-weight: 700;
  color: var(--bz-leaf);
}
.actions {
  display: flex;
  gap: 6px;
}
</style>
