<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import '../styles/index.css'
import { flushOutbox, pendingCount } from '../api'
import BzButton from '../components/ui/BzButton.vue'
import BzNotice from '../components/ui/BzNotice.vue'
import { useAsync } from '../composables/useAsync'
import { useDaytime } from '../composables/useDaytime'
import { useMotionPref } from '../composables/useMotionPref'
import { usePinchZoom } from '../composables/usePinchZoom'
import { createModuleContext, provideModuleContext } from '../composables/useModuleContext'
import { t } from '../i18n'
import { installTouchFeel } from '../services/touch/feel'
import { installPeek } from '../services/touch/peek'
import { useGuideStore } from '../stores/guide'
import { useMetaStore } from '../stores/meta'
import { useRecordingsStore } from '../stores/recordings'
import { useRewardsStore } from '../stores/rewards'

/**
 * Shell of every Beszéd page: loads the module's styles and data once, tells
 * the pages whose session this is, and unlocks audio on the first tap.
 */
const props = defineProps({
  childId: { type: Number, required: true },
  childName: { type: String, default: '' },
  /** The child's óvodai jel (an emoji), shown by their name. */
  childSign: { type: String, default: '' },
  guideName: { type: String, default: '' },
  /** The plan unlocks every game; false = the free demo games only. A module used on its own has no plans. */
  premium: { type: Boolean, default: true },
})

provideModuleContext(createModuleContext(props))

const meta = useMetaStore()
const recordings = useRecordingsStore()
const guide = useGuideStore()
const rewards = useRewardsStore()

const { error, run: boot } = useAsync(() => Promise.all([meta.load(), recordings.load()]), {
  fallback: t('hub.loadFailed'),
})

// Rewards belong to the child; never blocking (a game works without them).
watch(() => props.childId, id => rewards.load(id), { immediate: true })

/** Morning, day, evening or night in Zoé's garden (the sky, the scene colours). */
const daytime = useDaytime()
const { reduceMotion } = useMotionPref()

// iOS only plays audio after a tap. Pages unlock on their own buttons too; this
// covers opening a game URL directly.
const unlock = () => guide.unlock()

/** Every touch answers with a soft note and a tiny buzz (services/touch/feel.js). */
const root = ref(null)
const { scale: globalScale } = usePinchZoom(root)
let uninstallFeel = () => {}

onMounted(async () => {
  boot()
  const uninstallTouch = installTouchFeel(root.value)
  const uninstallPeek = installPeek(root.value)
  uninstallFeel = () => {
    uninstallTouch()
    uninstallPeek()
  }
  document.addEventListener('click', unlock, { capture: true, once: true })
  // Results played offline earlier: upload them, then show the updated rewards.
  if (pendingCount()) {
    await flushOutbox()
    rewards.load(props.childId)
  }
})

onBeforeUnmount(() => {
  uninstallFeel()
  document.removeEventListener('click', unlock, { capture: true })
  guide.reset()
})
</script>

<template>
  <main ref="root" class="bz" :data-daytime="daytime" :data-motion="reduceMotion ? 'reduced' : null" :style="{ '--bz-global-scale': globalScale }">
    <div class="bz-backdrop" aria-hidden="true" />
    <BzNotice v-if="error" tone="warn">
      {{ error }}
      <BzButton size="sm" @click="boot">{{ t('common.retry') }}</BzButton>
    </BzNotice>
    <RouterView v-else-if="meta.loaded" v-slot="{ Component, route }">
      <!-- Page changes fade and rise slightly (opacity/transform only, so they stay smooth on phones). -->
      <Transition name="page" mode="out-in">
        <div :key="route.path" class="page">
          <component :is="Component" />
        </div>
      </Transition>
    </RouterView>
    <p v-else class="bz-loading" aria-busy="true">{{ t('common.loading') }}</p>
  </main>
</template>

<style scoped>
.page-enter-active {
  transition: opacity 0.22s ease, transform 0.28s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.page-leave-active {
  transition: opacity 0.12s ease;
}
.page-enter-from {
  opacity: 0;
  transform: translate3d(0, 12px, 0);
}
.page-leave-to {
  opacity: 0;
}
</style>
