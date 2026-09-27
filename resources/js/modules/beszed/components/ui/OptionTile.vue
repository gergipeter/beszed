<script setup>
import EmojiArt from './EmojiArt.vue'

/**
 * A tappable answer. Shows `emoji` + `label`, or the default slot for custom
 * content (a scene, a plate of fruit). `good` / `shake` show the verdict.
 */
defineProps({
  emoji: { type: String, default: '' },
  label: { type: String, default: '' },
  variant: { type: String, default: 'default', validator: v => ['default', 'plate', 'scene'].includes(v) },
  good: { type: Boolean, default: false },
  shake: { type: Boolean, default: false },
  /** Small number in the corner (tap order in Papagáj). */
  badge: { type: [Number, String], default: null },
})
</script>

<template>
  <button type="button" class="option" :class="[`option--${variant}`, { 'option--good': good, 'option--shake': shake }]">
    <slot>
      <EmojiArt v-if="emoji" class="art" :char="emoji" />
      <small v-if="label" class="label">{{ label }}</small>
    </slot>
    <b v-if="badge" class="badge" aria-hidden="true">{{ badge }}</b>
  </button>
</template>

<style scoped>
.option {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  min-height: 110px;
  padding: 14px 8px;
  border: 4px solid transparent;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  font-size: 30px;
  font-weight: 800;
  box-shadow: var(--bz-shadow-lg);
  transition: transform 0.38s var(--bz-spring);
}
.option:active {
  transform: scale(0.93);
  transition-duration: 0.07s;
  transition-timing-function: ease-out;
}
.option--good {
  border-color: var(--bz-leaf);
  background: color-mix(in srgb, var(--bz-leaf) 18%, var(--bz-card));
}
.option--shake {
  animation: shake 0.45s;
}
.option--scene {
  padding: 6px;
}
/* Up to 8 items, 3 per row, inside the circle's inscribed square (~70% of it). */
.option--plate {
  flex-direction: row;
  flex-wrap: wrap;
  align-content: center;
  gap: 2px 4px;
  aspect-ratio: 1;
  padding: 15%;
  border-radius: 50%;
  font-size: clamp(20px, 6.2vw, 38px);
}
.art {
  font-size: clamp(48px, 11vw, 72px);
  line-height: 1.05;
}
.label {
  margin-top: 4px;
  font-size: 16px;
  line-height: 1.2;
  color: var(--bz-muted);
}
.badge {
  position: absolute;
  top: 6px;
  left: 8px;
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--bz-leaf);
  color: var(--bz-on-accent);
  font-size: 18px;
}
@keyframes shake {
  20% {
    transform: translateX(-10px);
  }
  40% {
    transform: translateX(10px);
  }
  60% {
    transform: translateX(-6px);
  }
  80% {
    transform: translateX(6px);
  }
}
</style>
