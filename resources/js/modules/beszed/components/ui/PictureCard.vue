<script setup>
import { t } from '../../i18n'
import EmojiArt from './EmojiArt.vue'

/**
 * The big picture a round is about. `pressable` makes it a button (e.g. "say the
 * word"); `silhouette` shows only its shadow.
 */
defineProps({
  emoji: { type: String, required: true },
  label: { type: String, default: '' },
  highlight: { type: Boolean, default: false },
  silhouette: { type: Boolean, default: false },
  pressable: { type: Boolean, default: false },
})
</script>

<template>
  <button
    v-if="pressable"
    type="button"
    class="picture"
    :class="{ 'picture--highlight': highlight, 'picture--silhouette': silhouette }"
    :data-peek="silhouette ? undefined : ''"
    :aria-label="label || t('common.picture')"
  >
    <EmojiArt class="art" :char="emoji" />
    <small v-if="label" class="label">{{ label }}</small>
  </button>
  <div v-else class="picture" :class="{ 'picture--highlight': highlight, 'picture--silhouette': silhouette }" :data-peek="silhouette ? undefined : ''" aria-hidden="true">
    <EmojiArt class="art" :char="emoji" />
    <small v-if="label" class="label">{{ label }}</small>
  </div>
</template>

<style scoped>
.picture {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 18px 34px 10px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  font-size: 110px;
  line-height: 1;
  box-shadow: var(--bz-shadow-lg);
  transition: transform 0.38s var(--bz-spring);
}
button.picture:active {
  transform: scale(0.94);
  transition-duration: 0.07s;
  transition-timing-function: ease-out;
}
.picture--highlight {
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  font-size: 80px;
}
.picture--silhouette {
  padding: 22px 38px 18px;
  font-size: 130px;
}
.picture--silhouette .art {
  filter: var(--bz-silhouette);
}
.label {
  margin-top: 4px;
  font-size: 16px;
  line-height: 1.2;
  color: var(--bz-muted);
}
.picture--highlight .label {
  color: var(--bz-on-bright);
}
</style>
