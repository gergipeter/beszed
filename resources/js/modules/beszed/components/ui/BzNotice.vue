<script setup>
/** Info or warning box; warnings are announced to screen readers. */
defineProps({
  tone: { type: String, default: 'info', validator: v => ['info', 'warn'].includes(v) },
})
</script>

<template>
  <div class="notice" :class="`notice--${tone}`" :role="tone === 'warn' ? 'alert' : undefined">
    <slot />
  </div>
</template>

<style scoped>
.notice {
  margin: 16px 0;
  padding: 12px 16px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  font-size: var(--bz-text);
  line-height: 1.45;
}
.notice--warn {
  background: color-mix(in srgb, var(--bz-coral) 22%, var(--bz-card));
}
@media print {
  .notice {
    background: none;
  }
}
</style>
