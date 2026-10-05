<script setup>
import { ref } from 'vue'
import { useModuleContext } from '../../composables/useModuleContext'
import { ICONS } from '../../config/icons'
import { config } from '../../config/options'
import { t } from '../../i18n'
import { buzz } from '../../services/touch/feel'
import { confetti } from '../../services/effects/confetti'
import { useRecordingsStore } from '../../stores/recordings'
import { useRewardsStore } from '../../stores/rewards'
import BzButton from '../ui/BzButton.vue'
import EmojiArt from '../ui/EmojiArt.vue'
import InstallApp from './InstallApp.vue'
import ParentGate from './ParentGate.vue'

/**
 * The parents' menu behind a ☰ button in the corner: recordings, progress, settings, the premium
 * page, the child picker. Children see one small button instead of a row of grown-up links.
 *
 * It opens behind a parental gate (read a three-digit number written in words and type it), so a small child
 * tapping at random cannot get in. The same gate guards purchases and links out (App Store guideline 1.3).
 *
 * A modal <dialog> (top layer): it covers the whole screen whatever the page is scrolled to, closes
 * with Escape or a tap outside, and keeps keyboard focus inside while it is open.
 */
const { childId, childName, premium } = useModuleContext()

const recordings = useRecordingsStore()
const rewards = useRewardsStore()
const dialog = ref(null)
const trigger = ref(null)
const giftMessage = ref('')
const gifting = ref(false)

const gate = ref(null)

/** The menu is for parents: a three-digit number to read and type first (ParentGate). */
async function ask() {
  if (await gate.value.ask()) open()
}
function open() {
  buzz(10)
  dialog.value?.showModal()
}
function close() {
  dialog.value?.close()
  trigger.value?.focus()
}

/** The parent hands over the "ügyes voltál" sticker by hand: always available, never scored. */
async function giveSticker() {
  if (gifting.value) return
  gifting.value = true
  try {
    const wasNew = await rewards.giftProud(childId.value)
    giftMessage.value = t(wasNew ? 'hub.giveStickerGiven' : 'hub.giveStickerAgain', { child: childName.value })
    confetti({ pieces: 50 })
  } catch {
    giftMessage.value = ''
  } finally {
    gifting.value = false
  }
}
</script>

<template>
  <button ref="trigger" type="button" class="menu-btn" :aria-label="t('hub.menu')" aria-haspopup="dialog" @click="ask">
    <span class="bars" aria-hidden="true"><i /><i /><i /></span>
  </button>
  <ParentGate ref="gate" />

  <dialog ref="dialog" class="drawer" :aria-label="t('hub.forParents')" @click.self="close">
    <header class="head">
      <h2 class="title"><EmojiArt :char="ICONS.family" /> {{ t('hub.forParents') }}</h2>
      <button type="button" class="close" :aria-label="t('hub.closeMenu')" @click="close">
        <span aria-hidden="true">✕</span>
      </button>
    </header>

    <nav class="items">
      <BzButton class="item" variant="soft" :to="{ name: 'beszed.recordings', params: { childId } }" :icon="ICONS.mic">
        {{ t('recordings.title') }}
        <b v-if="recordings.loaded && !Object.keys(recordings.urls).length" class="new">{{ t('common.new') }}</b>
      </BzButton>
      <BzButton class="item" variant="soft" :to="{ name: 'beszed.journey', params: { childId } }" :icon="ICONS.journey">
        {{ t('journey.title') }}
      </BzButton>
      <BzButton class="item" variant="soft" :to="{ name: 'beszed.progress', params: { childId } }" :icon="ICONS.chart">
        {{ t('progress.title') }}
      </BzButton>
      <BzButton class="item" variant="soft" :to="{ name: 'beszed.settings', params: { childId } }" :icon="ICONS.settings">
        {{ t('settings.title') }}
      </BzButton>
      <BzButton class="item" variant="soft" :icon="ICONS.heart" :disabled="gifting" @click="giveSticker">
        {{ t('hub.giveSticker') }}
      </BzButton>
      <p v-if="giftMessage" class="gift-message" role="status">{{ giftMessage }}</p>
      <BzButton
        v-if="!premium"
        class="item"
        variant="soft"
        :to="{ name: 'beszed.premium', params: { childId } }"
        :icon="ICONS.star"
      >
        {{ t('premium.title') }}
      </BzButton>
      <!-- its own sheet must not open underneath this one -->
      <div class="install" @click="close"><InstallApp /></div>
      <BzButton v-if="config.exitTo" class="item" variant="soft" :to="config.exitTo" :icon="ICONS.family">
        {{ t('hub.exit') }}
      </BzButton>
    </nav>
  </dialog>
</template>

<style scoped>
/* a small nudge until the first own-voice line is recorded */
.new {
  margin-left: 8px;
  padding: 1px 8px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  font-size: 12px;
}
/* ---- the corner button: three fat bars, big enough for a parent's thumb, small enough to ignore ---- */
.menu-btn {
  position: absolute;
  top: calc(10px + env(safe-area-inset-top, 0px));
  right: 14px;
  z-index: 5;
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--bz-card) 82%, transparent);
  box-shadow: var(--bz-shadow-sm);
  backdrop-filter: blur(6px);
  transition: transform 0.3s var(--bz-spring);
}
.menu-btn:active {
  transform: scale(0.88);
}
.bars {
  display: grid;
  gap: 5px;
}
.bars i {
  display: block;
  width: 24px;
  height: 4px;
  border-radius: 4px;
  background: var(--bz-ink);
}

/* ---- the drawer ---- */
.drawer {
  /* top layer: pinned to the screen, whatever transform the page has */
  position: fixed;
  inset: 0 0 0 auto;
  width: min(360px, 88vw);
  height: 100dvh;
  max-height: none;
  margin: 0;
  padding: calc(18px + env(safe-area-inset-top, 0px)) 18px calc(24px + env(safe-area-inset-bottom, 0px));
  border: 0;
  border-radius: var(--bz-radius-lg) 0 0 var(--bz-radius-lg);
  background: var(--bz-card);
  color: var(--bz-ink);
  font-family: var(--bz-font);
  box-shadow: -10px 0 40px rgba(0, 0, 0, 0.3);
  overflow-y: auto;
}
.drawer[open] {
  animation: slide-in 0.35s var(--bz-spring);
}
.drawer::backdrop {
  background: rgba(30, 20, 60, 0.5);
}
.head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 18px;
}
.title {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 24px;
}
.close {
  flex: none;
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--bz-soft);
  font-size: 22px;
  line-height: 1;
}
.gift-message {
  margin: -4px 0 0;
  padding: 8px 14px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-soft);
  font-size: 14px;
  font-weight: 700;
}
.items {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.item {
  width: 100%;
  justify-content: flex-start;
  border: 2px solid color-mix(in srgb, var(--bz-ink) 20%, transparent);
}
.install {
  display: contents;
}
.install :deep(.btn) {
  width: 100%;
  justify-content: flex-start;
  background: var(--bz-soft);
  border: 2px solid color-mix(in srgb, var(--bz-ink) 20%, transparent);
}
@keyframes slide-in {
  from {
    transform: translateX(100%);
  }
}
@media (prefers-reduced-motion: reduce) {
  .drawer[open] {
    animation: none;
  }
}
</style>
