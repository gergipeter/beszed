<script setup>
import { computed } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { HIDDEN, SCENES } from './scenes'

/** A little picture of `emoji` placed relative to a box (fölött, mögött…). */
const props = defineProps({
  relation: { type: String, required: true },
  emoji: { type: String, required: true },
})

const items = computed(() =>
  (SCENES[props.relation] ?? []).map(([char, x, y, size, z]) => ({
    char: char === HIDDEN ? props.emoji : char,
    style: { left: `${x}%`, top: `${y}%`, fontSize: `${((size / 150) * 100).toFixed(1)}cqw`, zIndex: z },
  })),
)
</script>

<template>
  <div class="scene" aria-hidden="true">
    <span v-for="(item, i) in items" :key="i" class="item" :style="item.style"><EmojiArt :char="item.char" /></span>
  </div>
</template>

<style scoped>
.scene {
  position: relative;
  width: 100%;
  aspect-ratio: 1;
  container-type: inline-size;
  overflow: hidden;
  border-radius: 20px;
  background: var(--bz-soft);
}
.item {
  position: absolute;
  transform: translate(-50%, -50%);
  line-height: 1;
}
</style>
