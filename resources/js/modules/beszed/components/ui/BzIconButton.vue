<script setup>
import { RouterLink } from 'vue-router'
import EmojiArt from './EmojiArt.vue'

/** Round toolbar button (home, replay…). `label` is its accessible name. */
defineProps({
  icon: { type: String, required: true },
  label: { type: String, required: true },
  to: { type: [String, Object], default: null },
  /** For a toggle: null for a plain button, else whether it is switched on. */
  active: { type: Boolean, default: null },
})
</script>

<template>
  <RouterLink v-if="to" :to="to" class="icon-btn" :aria-label="label"><EmojiArt :char="icon" /></RouterLink>
  <button
    v-else
    type="button"
    class="icon-btn"
    :class="{ on: active }"
    :aria-label="label"
    :aria-pressed="active === null ? undefined : active"
  >
    <EmojiArt :char="icon" />
  </button>
</template>

<style scoped>
.icon-btn {
  flex: none;
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--bz-card);
  font-size: 28px;
  box-shadow: 0 4px 0 var(--bz-shadow-color);
  transition: transform 0.38s var(--bz-spring);
}
.icon-btn.on {
  background: var(--bz-sun);
  box-shadow:
    0 0 0 3px var(--bz-on-bright),
    0 4px 0 var(--bz-shadow-color);
}
.icon-btn:active {
  transform: scale(0.9);
  transition-duration: 0.07s;
  transition-timing-function: ease-out;
}
</style>
