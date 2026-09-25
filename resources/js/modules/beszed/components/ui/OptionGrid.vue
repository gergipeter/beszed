<script setup>
/** Grid of OptionTiles; 4 columns fold to 2 on phones. */
defineProps({
  columns: { type: Number, default: 3, validator: v => [2, 3, 4].includes(v) },
})
</script>

<template>
  <div class="grid" :class="`grid--${columns}`"><slot /></div>
</template>

<style scoped>
.grid {
  display: grid;
  gap: 14px;
  width: 100%;
}
.grid--2 {
  grid-template-columns: 1fr 1fr;
  max-width: 520px;
}
.grid--3 {
  grid-template-columns: repeat(3, 1fr);
  max-width: 640px;
}
.grid--4 {
  grid-template-columns: repeat(4, 1fr);
  max-width: 640px;
}
@media (max-width: 480px) {
  .grid--4 {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
