<script setup>
import EmojiArt from '../ui/EmojiArt.vue'

/** One sticker of the album: colourful when earned, a shadow with a hint when not. */
defineProps({
  /** @type {import('vue').PropType<import('../../types').Badge>} */
  badge: { type: Object, required: true },
  earned: { type: Boolean, default: false },
  /** Pop-in animation (a sticker that was just earned). */
  fresh: { type: Boolean, default: false },
})
</script>

<template>
  <button type="button" class="sticker" :class="{ 'sticker--earned': earned, 'sticker--fresh': fresh }">
    <span class="disc"><EmojiArt class="art" :char="badge.emoji" /></span>
    <b class="name">{{ badge.name }}</b>
    <small v-if="!earned" class="hint">{{ badge.hint }}</small>
  </button>
</template>

<style scoped>
.sticker {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 10px 6px 12px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  text-align: center;
  box-shadow: var(--bz-shadow);
  transition: transform 0.08s;
}
.sticker:active {
  transform: translateY(3px);
}
.disc {
  display: grid;
  place-items: center;
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: var(--bz-soft);
  border: 3px dashed color-mix(in srgb, var(--bz-guide) 60%, transparent);
  font-size: 40px;
}
.art {
  filter: var(--bz-silhouette);
  opacity: 0.35;
}
.sticker--earned .disc {
  border: 4px solid #fff;
  background: radial-gradient(circle at 35% 30%, #fffbe0, var(--bz-sun) 75%);
  box-shadow: 0 3px 0 rgba(59, 31, 74, 0.2);
}
.sticker--earned .art {
  filter: none;
  opacity: 1;
}
.name {
  font-size: 16px;
  line-height: 1.15;
}
.sticker:not(.sticker--earned) .name {
  color: var(--bz-muted);
}
.hint {
  font-size: 13px;
  line-height: 1.2;
  color: var(--bz-muted);
}
.sticker--fresh {
  animation: pop 0.6s cubic-bezier(0.2, 1.6, 0.4, 1) both;
}
@keyframes pop {
  from {
    transform: scale(0.2) rotate(-20deg);
    opacity: 0;
  }
}
</style>
