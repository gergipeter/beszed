<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import '../styles/index.css'
import BzButton from '../components/ui/BzButton.vue'
import BzNotice from '../components/ui/BzNotice.vue'
import { useAsync } from '../composables/useAsync'
import { createModuleContext, provideModuleContext } from '../composables/useModuleContext'
import { t } from '../i18n'
import { useGuideStore } from '../stores/guide'
import { useMetaStore } from '../stores/meta'
import { useRecordingsStore } from '../stores/recordings'

/**
 * Shell of every Beszéd page: loads the module's styles and data once, tells
 * the pages whose session this is, and unlocks audio on the first tap.
 */
const props = defineProps({
  childId: { type: Number, required: true },
  childName: { type: String, default: '' },
  guideName: { type: String, default: '' },
})

provideModuleContext(createModuleContext(props))

const meta = useMetaStore()
const recordings = useRecordingsStore()
const guide = useGuideStore()

const { error, run: boot } = useAsync(() => Promise.all([meta.load(), recordings.load()]), {
  fallback: t('hub.loadFailed'),
})

// iOS only plays audio after a tap. Pages unlock on their own buttons too; this
// covers opening a game URL directly.
const unlock = () => guide.unlock()

onMounted(() => {
  boot()
  document.addEventListener('click', unlock, { capture: true, once: true })
})

onBeforeUnmount(() => {
  document.removeEventListener('click', unlock, { capture: true })
  guide.reset()
})
</script>

<template>
  <main class="bz">
    <BzNotice v-if="error" tone="warn">
      {{ error }}
      <BzButton size="sm" @click="boot">{{ t('common.retry') }}</BzButton>
    </BzNotice>
    <RouterView v-else-if="meta.loaded" />
    <p v-else class="bz-loading" aria-busy="true">{{ t('common.loading') }}</p>
  </main>
</template>
