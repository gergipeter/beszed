<script setup>
import MedalStars from '../rewards/MedalStars.vue'
import EmojiArt from '../ui/EmojiArt.vue'

/** A game on the hub, in the game's own colour, with its best medals. */
defineProps({
  /** @type {import('vue').PropType<import('../../types').GameMeta>} */
  game: { type: Object, required: true },
  medal: { type: Number, default: 0 },
})
</script>

<template>
  <button type="button" class="tile" :style="{ '--tile-color': game.color }">
    <EmojiArt class="art" :char="game.emoji" />
    <span class="name">{{ game.name }}</span>
    <span class="skill">{{ game.skill }}</span>
    <MedalStars v-if="medal" class="medals" :count="medal" />
  </button>
</template>

<style scoped>
.tile {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 18px 16px 16px;
  border-radius: 30px 30px 30px 10px;
  background: var(--tile-color, var(--bz-card));
  color: var(--bz-on-bright);
  text-align: left;
  box-shadow: 0 7px 0 rgba(59, 31, 74, 0.18);
  transition: transform 0.1s ease;
  /* tiles float in one after another; `backwards` so the press effect below still works afterwards */
  animation: tile-in 0.42s cubic-bezier(0.2, 0.8, 0.2, 1) backwards;
  animation-delay: calc(var(--i, 0) * 35ms);
}
.tile:active {
  transform: translateY(4px) scale(0.98);
}
@keyframes tile-in {
  from {
    opacity: 0;
    transform: translate3d(0, 18px, 0) scale(0.96);
  }
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
.medals {
  position: absolute;
  top: 14px;
  right: 14px;
  padding: 3px 6px;
  border-radius: var(--bz-radius-pill);
  background: rgba(255, 255, 255, 0.7);
}
</style>
