<script setup>
import EmojiArt from '../ui/EmojiArt.vue'

/** A game on the hub, in the game's own colour. */
defineProps({
  /** @type {import('vue').PropType<import('../../types').GameMeta>} */
  game: { type: Object, required: true },
})
</script>

<template>
  <button type="button" class="tile" :style="{ '--tile-color': game.color }">
    <EmojiArt class="art" :char="game.emoji" />
    <span class="name">{{ game.name }}</span>
    <span class="skill">{{ game.skill }}</span>
  </button>
</template>

<style scoped>
.tile {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 18px 16px 16px;
  border-radius: 30px 30px 30px 10px;
  background: var(--tile-color, var(--bz-card));
  color: var(--bz-on-bright);
  text-align: left;
  box-shadow: 0 7px 0 rgba(59, 31, 74, 0.18);
  transition: transform 0.08s, box-shadow 0.08s;
}
.tile:active {
  transform: translateY(5px);
  box-shadow: 0 2px 0 rgba(59, 31, 74, 0.18);
}
.art {
  font-size: 56px;
  line-height: 1.1;
}
.name {
  margin-top: 6px;
  font-size: 23px;
  font-weight: 800;
  line-height: 1.1;
}
.skill {
  font-size: 15px;
  line-height: 1.25;
  opacity: 0.75;
}
</style>
