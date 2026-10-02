<script setup>
import { t } from '../../i18n'
import EmojiArt from '../ui/EmojiArt.vue'

/** "Ma ezt gyakoroljuk": a nudge towards the game the child struggles with most lately. */
defineProps({
  /** @type {import('vue').PropType<import('../../types').GameMeta>} */
  game: { type: Object, required: true },
})
const emit = defineEmits(['play'])
</script>

<template>
  <button type="button" class="spotlight" :style="{ '--tile-color': game.color }" @click="emit('play', game.id)">
    <EmojiArt class="art" :char="game.emoji" />
    <span class="body">
      <span class="title">{{ t('spotlight.title') }}</span>
      <span class="skill">{{ game.skill }}</span>
    </span>
    <span class="cta">{{ t('spotlight.cta') }}</span>
  </button>
</template>

<style scoped>
.spotlight {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  margin: 0 0 18px;
  padding: 12px 16px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
  text-align: left;
  transition: transform 0.1s ease;
}
.spotlight:active {
  transform: scale(0.98);
}
.art {
  flex: none;
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: var(--tile-color, var(--bz-soft));
  font-size: 30px;
}
.body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.title {
  font-size: var(--bz-text-sm);
  font-weight: 800;
  color: var(--bz-muted);
}
.skill {
  font-size: 17px;
  font-weight: 700;
  overflow-wrap: anywhere;
}
.cta {
  flex: none;
  padding: 8px 16px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-leaf-deep, var(--bz-leaf));
  color: var(--bz-on-accent);
  font-weight: 800;
  font-size: var(--bz-text-sm);
}
</style>
