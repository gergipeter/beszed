<script setup>
import { onBeforeUnmount, ref } from 'vue'
import { useModuleContext } from '../../composables/useModuleContext'
import { ICONS } from '../../config/icons'
import { config } from '../../config/options'
import { t } from '../../i18n'
import { buzz } from '../../services/touch/feel'
import BzButton from '../ui/BzButton.vue'
import EmojiArt from '../ui/EmojiArt.vue'
import InstallApp from './InstallApp.vue'

/**
 * The parents' menu behind a ☰ button in the corner: recordings, progress, settings, the premium
 * page, the child picker. Children see one small button instead of a row of grown-up links.
 *
 * It opens on a press-and-hold (about a second, a ring fills meanwhile), so a small child tapping at random
 * does not get in; a quick tap only shows a hint. A keyboard (or screen reader) activation opens it directly.
 *
 * A modal <dialog> (top layer): it covers the whole screen whatever the page is scrolled to, closes
 * with Escape or a tap outside, and keeps keyboard focus inside while it is open.
 */
const { childId, premium } = useModuleContext()

const dialog = ref(null)
const trigger = ref(null)

const HOLD_MS = 900
const holding = ref(false)
const hint = ref(false)
let holdTimer = null
let hintTimer = null
let opened = false

function open() {
  buzz(10)
  hint.value = false
  dialog.value?.showModal()
}

function startHold() {
  opened = false
  holding.value = true
  clearTimeout(holdTimer)
  holdTimer = setTimeout(() => {
    holding.value = false
    opened = true
    open()
  }, HOLD_MS)
}
function endHold() {
  clearTimeout(holdTimer)
  holding.value = false
}
/** A click without a finished hold: a tap shows the hint; a keyboard activation (detail 0) opens directly. */
function onClick(event) {
  if (event.detail === 0) return open()
  if (opened) {
    opened = false
    return
  }
  hint.value = true
  clearTimeout(hintTimer)
  hintTimer = setTimeout(() => (hint.value = false), 2600)
}
onBeforeUnmount(() => {
  clearTimeout(holdTimer)
  clearTimeout(hintTimer)
})
function close() {
  dialog.value?.close()
  trigger.value?.focus()
}
</script>

<template>
  <button
    ref="trigger"
    type="button"
    class="menu-btn"
    :class="{ 'menu-btn--holding': holding }"
    :aria-label="t('hub.menu')"
    aria-haspopup="dialog"
    @pointerdown="startHold"
    @pointerup="endHold"
    @pointerleave="endHold"
    @pointercancel="endHold"
    @contextmenu.prevent
    @click="onClick"
  >
    <svg class="ring" viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="24" /></svg>
    <span class="bars" aria-hidden="true"><i /><i /><i /></span>
  </button>
  <p v-if="hint" class="hint" role="status">{{ t('hub.menuHint') }}</p>

  <dialog ref="dialog" class="drawer" :aria-label="t('hub.forParents')" @click.self="close" @close="opened = false">
    <header class="head">
      <h2 class="title"><EmojiArt :char="ICONS.family" /> {{ t('hub.forParents') }}</h2>
      <button type="button" class="close" :aria-label="t('hub.closeMenu')" @click="close">
        <span aria-hidden="true">✕</span>
      </button>
    </header>

    <nav class="items">
      <BzButton class="item" variant="soft" :to="{ name: 'beszed.recordings', params: { childId } }" :icon="ICONS.mic">
        {{ t('recordings.title') }}
      </BzButton>
      <BzButton class="item" variant="soft" :to="{ name: 'beszed.progress', params: { childId } }" :icon="ICONS.chart">
        {{ t('progress.title') }}
      </BzButton>
      <BzButton class="item" variant="soft" :to="{ name: 'beszed.settings', params: { childId } }" :icon="ICONS.settings">
        {{ t('settings.title') }}
      </BzButton>
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
/* a long press must not select text or open the browser's own menu */
.menu-btn {
  -webkit-touch-callout: none;
  -webkit-user-select: none;
  user-select: none;
  touch-action: manipulation;
}
/* the ring that fills while the button is held */
.ring {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
  pointer-events: none;
}
.ring circle {
  fill: none;
  stroke: var(--bz-leaf, #3aa76d);
  stroke-width: 4;
  stroke-linecap: round;
  stroke-dasharray: 151;
  stroke-dashoffset: 151;
}
.menu-btn--holding .ring circle {
  stroke-dashoffset: 0;
  transition: stroke-dashoffset 0.9s linear;
}
.hint {
  position: absolute;
  top: calc(68px + env(safe-area-inset-top, 0px));
  right: 14px;
  z-index: 5;
  max-width: 220px;
  margin: 0;
  padding: 8px 14px;
  border-radius: 16px;
  background: var(--bz-card);
  color: var(--bz-ink);
  font-size: 16px;
  font-weight: 700;
  line-height: 1.2;
  box-shadow: var(--bz-shadow);
  animation: hint-in 0.3s var(--bz-spring);
}
@keyframes hint-in {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
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
  border-radius: 32px 0 0 32px;
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
