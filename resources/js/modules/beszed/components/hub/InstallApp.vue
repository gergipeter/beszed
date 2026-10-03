<script setup>
import { computed, ref } from 'vue'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { canPrompt, isInstalled, isIos, promptInstall } from '../../services/device/install'
import BzButton from '../ui/BzButton.vue'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * "Csillám a kezdőképernyőn" for the parents: on Android, Chrome's own install
 * dialog; on iPhone/iPad, how to add it by hand. Both come with the tip that
 * keeps a child inside the app (Guided Access / screen pinning). Hidden once
 * the app runs installed, and where the browser can't install it.
 */
const open = ref(false)
const ios = isIos()
const shown = computed(() => !isInstalled() && (canPrompt.value || ios))

async function install() {
  if (canPrompt.value) await promptInstall()
  else open.value = true
}
</script>

<template>
  <BzButton v-if="shown" :icon="ICONS.phone" @click="install">{{ t('install.button') }}</BzButton>

  <Teleport to="body">
    <div v-if="open" class="bz-install" role="dialog" aria-modal="true" :aria-label="t('install.title')" @click.self="open = false">
      <div class="sheet">
        <h2 class="title"><EmojiArt :char="ICONS.phone" /> {{ t('install.title') }}</h2>
        <ol class="steps">
          <li><EmojiArt :char="ICONS.shareBox" /> {{ t('install.ios1') }}</li>
          <li><EmojiArt :char="ICONS.plus" /> {{ t('install.ios2') }}</li>
          <li><EmojiArt :char="ICONS.check" /> {{ t('install.ios3') }}</li>
        </ol>
        <p class="tip"><b>{{ t('install.tipTitle') }}</b> {{ ios ? t('install.tipIos') : t('install.tipAndroid') }}</p>
        <BzButton variant="primary" @click="open = false">{{ t('install.close') }}</BzButton>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
/* outside .bz (teleported), so the colours are its own */
.bz-install {
  position: fixed;
  inset: 0;
  z-index: 1003;
  display: grid;
  align-items: end;
  background: rgba(30, 20, 60, 0.45);
  font-family: 'Baloo 2', 'Trebuchet MS', 'Segoe UI', system-ui, sans-serif;
  color: #3b1f4a;
}
.sheet {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 560px;
  margin: 0 auto;
  width: 100%;
  padding: 22px 22px calc(22px + env(safe-area-inset-bottom, 0px));
  border-radius: var(--bz-radius-lg) var(--bz-radius-lg) 0 0;
  background: #fff;
  animation: up 0.35s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 24px;
}
.steps {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding-left: 22px;
  font-size: 18px;
  line-height: 1.3;
}
.tip {
  margin: 0;
  padding: 10px 14px;
  border-radius: var(--bz-radius-sm);
  background: #eaf7ff;
  font-size: 15px;
  line-height: 1.35;
}
@keyframes up {
  from {
    transform: translateY(100%);
  }
}
</style>
