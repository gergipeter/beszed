<script setup>
import { onMounted } from 'vue'
import { t } from '../../i18n'
import { useGuideStore } from '../../stores/guide'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * Before a game with picture themes (Kirakó): big tiles to pick what the
 * pictures should be, animals, princesses and tales, vehicles… The last
 * choice glows. Csillám asks.
 */
const props = defineProps({
  /** @type {import('vue').PropType<{ id: string, name: string, emoji: string }[]>} */
  categories: { type: Array, required: true },
  last: { type: String, default: null },
  /** What Csillám asks; the Kirakó question when empty. */
  prompt: { type: String, default: null },
})
const emit = defineEmits(['pick'])
const guide = useGuideStore()

onMounted(() => guide.speak([props.prompt || t('game.pickTheme')]))
</script>

<template>
  <div class="picker" role="group" :aria-label="prompt || t('game.pickTheme')">
    <button
      v-for="(c, i) in categories"
      :key="c.id"
      type="button"
      class="theme"
      :class="{ 'theme--last': last === c.id }"
      :style="{ '--i': i }"
      @click="emit('pick', c.id)"
    >
      <EmojiArt class="theme-art" :char="c.emoji" />
      <span class="theme-name">{{ c.name }}</span>
    </button>
  </div>
</template>

<style scoped>
.picker {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(150px, calc(50% - 6px)), 1fr));
  gap: 12px;
}
.theme {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 16px 8px 12px;
  border: 4px solid transparent;
  border-radius: var(--bz-radius-lg) var(--bz-radius-lg) var(--bz-radius-lg) var(--bz-radius-sm);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
  animation: theme-in 0.4s var(--bz-spring) backwards;
  animation-delay: calc(var(--i) * 45ms);
  transition: transform 0.38s var(--bz-spring);
}
.theme:active {
  transform: scale(0.93);
  transition-duration: 0.07s;
}
.theme--last {
  border-color: var(--bz-sun);
}
.theme-art {
  font-size: clamp(48px, 14vw, 64px);
  line-height: 1;
}
.theme-name {
  font-size: 17px;
  font-weight: 800;
  line-height: 1.15;
  text-align: center;
}
@keyframes theme-in {
  from {
    opacity: 0;
    transform: translateY(14px) scale(0.9);
  }
}
</style>
