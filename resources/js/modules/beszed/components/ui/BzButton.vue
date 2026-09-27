<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import EmojiArt from './EmojiArt.vue'

/** Pill button. With `to` it renders a RouterLink. Icon-only buttons need an `aria-label`. */
const props = defineProps({
  to: { type: [String, Object], default: null },
  /** Glyph before the label; use one from config/icons.js. */
  icon: { type: String, default: '' },
  variant: {
    type: String,
    default: 'default',
    validator: v => ['default', 'primary', 'soft', 'danger'].includes(v),
  },
  size: { type: String, default: 'md', validator: v => ['md', 'sm'].includes(v) },
  /** Gentle pulse, e.g. while recording. */
  pulse: { type: Boolean, default: false },
})

const classes = computed(() => ['btn', `btn--${props.variant}`, `btn--${props.size}`, { 'btn--pulse': props.pulse }])
</script>

<template>
  <RouterLink v-if="to" :to="to" :class="classes">
    <EmojiArt v-if="icon" :char="icon" /><slot />
  </RouterLink>
  <button v-else type="button" :class="classes">
    <EmojiArt v-if="icon" :char="icon" /><slot />
  </button>
</template>

<style scoped>
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 22px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  color: var(--bz-ink);
  font-size: 21px;
  font-weight: 700;
  line-height: 1.2;
  box-shadow: var(--bz-shadow);
  transition: transform 0.38s var(--bz-spring);
}
.btn:active:not(:disabled) {
  transform: scale(0.95);
  transition-duration: 0.07s;
  transition-timing-function: ease-out;
}
.btn:disabled {
  opacity: 0.35;
  cursor: default;
}
.btn--primary {
  background: var(--bz-leaf);
  color: var(--bz-on-accent);
}
.btn--soft {
  background: var(--bz-soft);
}
.btn--danger {
  background: var(--bz-coral);
  color: var(--bz-on-accent);
}
.btn--sm {
  padding: 8px 12px;
  font-size: 18px;
  box-shadow: var(--bz-shadow-sm);
}
.btn--pulse {
  animation: pulse 1s infinite;
}
@keyframes pulse {
  50% {
    transform: scale(1.1);
  }
}
</style>
