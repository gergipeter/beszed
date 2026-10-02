<script setup>
import EmojiArt from '../ui/EmojiArt.vue'

/** Today's weather, a small pill in the top corner: the sky as a picture and the temperature. Tap: Csillám says it. */
defineProps({
  icon: { type: String, required: true },
  temp: { type: String, required: true },
  label: { type: String, required: true },
})
defineEmits(['press'])
</script>

<template>
  <button type="button" class="weather-chip" :aria-label="label" :title="label" @click="$emit('press')">
    <EmojiArt class="sky" :char="icon" />
    <b class="deg">{{ temp }}</b>
  </button>
</template>

<style scoped>
.weather-chip {
  position: absolute;
  top: calc(10px + env(safe-area-inset-top, 0px));
  left: 14px;
  z-index: 5;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 52px;
  padding: 0 16px 0 10px;
  border-radius: var(--bz-radius-pill);
  background: color-mix(in srgb, var(--bz-card) 82%, transparent);
  box-shadow: var(--bz-shadow-sm);
  backdrop-filter: blur(6px);
  transition: transform 0.3s var(--bz-spring);
  animation: drop-in 0.6s var(--bz-spring) backwards;
}
.weather-chip:active {
  transform: scale(0.92);
}
.sky {
  font-size: 30px;
  line-height: 1;
}
.deg {
  font-size: var(--bz-text-md);
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}
@keyframes drop-in {
  from {
    opacity: 0;
    transform: translateY(-14px);
  }
}
</style>
