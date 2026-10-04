<script setup>
import { computed } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { ICONS } from '../../config/icons'

/**
 * The live voice meter: a glow that swells with the sound, inside a ring that fills with the round's
 * progress. `tone` colours it: off (waiting), on, soft, strong (too strong for a gentle blow).
 */
const props = defineProps({
  level: { type: Number, default: 0 },
  progress: { type: Number, default: 0 },
  tone: { type: String, default: 'off' },
  label: { type: String, default: '' },
})

const R = 44
const C = 2 * Math.PI * R
const dash = computed(() => `${(C * Math.min(1, Math.max(0, props.progress))).toFixed(1)} ${C.toFixed(1)}`)
</script>

<template>
  <div class="meter" :class="`meter--${tone}`" role="img" :aria-label="label">
    <span class="glow" :style="{ transform: `scale(${0.55 + level * 0.75})` }" />
    <svg viewBox="0 0 100 100" class="ring" aria-hidden="true">
      <circle cx="50" cy="50" :r="R" class="track" />
      <circle v-if="progress > 0.01" cx="50" cy="50" :r="R" class="fill" :stroke-dasharray="dash" />
    </svg>
    <EmojiArt class="mic" :char="ICONS.mic" />
  </div>
</template>

<style scoped>
.meter {
  position: relative;
  flex: none;
  width: 76px;
  height: 76px;
  display: grid;
  place-items: center;
}
.glow {
  position: absolute;
  inset: 12px;
  border-radius: 50%;
  background: var(--bz-sun);
  opacity: 0.85;
}
.meter--off .glow {
  opacity: 0.25;
}
.meter--soft .glow {
  background: #7fd8a0;
}
.meter--strong .glow {
  background: var(--bz-coral);
}
.ring {
  position: absolute;
  inset: 0;
  transform: rotate(-90deg);
}
.track {
  fill: none;
  stroke: var(--bz-card);
  stroke-width: 8;
}
.fill {
  fill: none;
  stroke: var(--bz-leaf);
  stroke-width: 8;
  stroke-linecap: round;
}
.mic {
  position: relative;
  font-size: 30px;
}
</style>
