<script setup>
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import EmojiArt from '../ui/EmojiArt.vue'

/** 0–3 medal stars (a game's best result). `animated` pops them in one by one. */
defineProps({
  count: { type: Number, required: true },
  size: { type: String, default: 'sm', validator: v => ['sm', 'lg'].includes(v) },
  animated: { type: Boolean, default: false },
})
</script>

<template>
  <span
    class="medals"
    :class="[`medals--${size}`, { 'medals--animated': animated }]"
    role="img"
    :aria-label="t('rewards.medals', { count })"
  >
    <EmojiArt
      v-for="n in 3"
      :key="n"
      class="medal"
      :class="{ 'medal--off': n > count }"
      :style="{ '--n': n }"
      :char="ICONS.star"
    />
  </span>
</template>

<style scoped>
.medals {
  display: inline-flex;
  gap: 1px;
  line-height: 1;
}
.medals--sm {
  font-size: 16px;
}
.medals--lg {
  gap: 6px;
  font-size: 44px;
}
.medal {
  display: inline-block;
}
.medal--off {
  filter: grayscale(1);
  opacity: 0.3;
}
/* earned stars pop in one after another; opacity/transform only */
.medals--animated .medal:not(.medal--off) {
  animation: medal-pop 0.5s cubic-bezier(0.2, 1.7, 0.4, 1) backwards;
  animation-delay: calc(0.25s + var(--n) * 0.18s);
}
@keyframes medal-pop {
  from {
    opacity: 0;
    transform: scale(0.2) rotate(-35deg);
  }
}
</style>
