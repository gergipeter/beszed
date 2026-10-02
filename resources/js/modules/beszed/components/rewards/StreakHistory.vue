<script setup>
import { computed } from 'vue'
import { ICONS } from '../../config/icons'
import { formatDay, t } from '../../i18n'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * The days we played together, the last two weeks: a flower for every day a game was
 * finished. Days off are simply not shown: it only ever counts what the child did.
 */
const props = defineProps({
  /** @type {import('vue').PropType<{ date: string, played: boolean }[]>} */
  days: { type: Array, required: true },
})

const played = computed(() => props.days.filter(d => d.played))
</script>

<template>
  <ol v-if="played.length" class="history" :aria-label="t('rewards.daysTogether', { count: played.length })">
    <li v-for="day in played" :key="day.date" class="flower" :title="formatDay(day.date)">
      <EmojiArt :char="ICONS.flower" :label="formatDay(day.date)" />
    </li>
  </ol>
</template>

<style scoped>
.history {
  display: flex;
  flex-wrap: wrap;
  gap: 2px 6px;
  margin: 0 0 16px;
  padding: 0;
  list-style: none;
}
.flower {
  font-size: 22px;
  line-height: 1;
  animation: pop 0.5s var(--bz-spring) backwards;
}
.flower:nth-child(2) { animation-delay: 0.06s; }
.flower:nth-child(3) { animation-delay: 0.12s; }
.flower:nth-child(4) { animation-delay: 0.18s; }
.flower:nth-child(5) { animation-delay: 0.24s; }
@keyframes pop {
  from {
    transform: scale(0);
  }
}
</style>
