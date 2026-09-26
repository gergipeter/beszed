<script setup>
import { formatDay, t } from '../../i18n'

/** The last 14 days as little dots: filled when a game was finished that day. */
defineProps({
  /** @type {import('vue').PropType<{ date: string, played: boolean }[]>} */
  days: { type: Array, required: true },
})

function label(day, isToday) {
  const date = formatDay(day.date)
  if (isToday) return t('rewards.streakDayToday', { date })
  return t(day.played ? 'rewards.streakDayPlayed' : 'rewards.streakDayMissed', { date })
}
</script>

<template>
  <ol class="history" :aria-label="t('rewards.streak', { count: days.filter(d => d.played).length })">
    <li
      v-for="(day, i) in days"
      :key="day.date"
      class="dot"
      :class="{ 'dot--on': day.played, 'dot--today': i === days.length - 1 }"
      :title="label(day, i === days.length - 1)"
    >
      <span class="bz-sr-only">{{ label(day, i === days.length - 1) }}</span>
    </li>
  </ol>
</template>

<style scoped>
.history {
  display: flex;
  gap: 4px;
  margin: 0 0 16px;
  padding: 0;
  list-style: none;
}
.dot {
  flex: 1;
  height: 10px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
}
.dot--on {
  background: var(--bz-leaf);
}
.dot--today {
  box-shadow: 0 0 0 2px var(--bz-card), 0 0 0 3px var(--bz-guide);
}
</style>
