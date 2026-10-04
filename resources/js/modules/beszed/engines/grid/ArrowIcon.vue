<script setup>
/** A plain arrow drawn in code, pointing `dir` (up, right, down, left). Decoration: the button carries the name. */
defineProps({ dir: { type: String, required: true } })
</script>

<template>
  <svg class="arrow" :class="`arrow--${dir}`" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path d="M12 3.2 21 12.6h-5.6V21H8.6v-8.4H3z" />
  </svg>
</template>

<style scoped>
.arrow {
  display: block;
  width: 1em;
  height: 1em;
  fill: currentColor;
  stroke: currentColor;
  stroke-width: 1.4;
  stroke-linejoin: round;
}
.arrow--right {
  rotate: 90deg;
}
.arrow--down {
  rotate: 180deg;
}
.arrow--left {
  rotate: 270deg;
}
</style>
