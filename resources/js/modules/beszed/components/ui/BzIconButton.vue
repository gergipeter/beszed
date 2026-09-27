<script setup>
import { RouterLink } from 'vue-router'
import EmojiArt from './EmojiArt.vue'

/** Round toolbar button (home, replay…). `label` is its accessible name. */
defineProps({
  icon: { type: String, required: true },
  label: { type: String, required: true },
  to: { type: [String, Object], default: null },
})
</script>

<template>
  <RouterLink v-if="to" :to="to" class="icon-btn" :aria-label="label"><EmojiArt :char="icon" /></RouterLink>
  <button v-else type="button" class="icon-btn" :aria-label="label"><EmojiArt :char="icon" /></button>
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
.icon-btn:active {
  transform: scale(0.9);
  transition-duration: 0.07s;
  transition-timing-function: ease-out;
}
</style>
