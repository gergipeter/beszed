<script setup>
import { computed } from 'vue'
import { t } from '../../i18n'

/**
 * The weekly challenge: this many different games in a calendar week. A row of dots fills up as games are finished;
 * a full row means this week's sticker is won. `week` is rewards.summary.week.
 */
const props = defineProps({
  /** @type {import('vue').PropType<{ games: number, goal: number, won: number, days_left: number }>} */
  week: { type: Object, required: true },
})

const done = computed(() => props.week.games >= props.week.goal)
</script>

<template>
  <section class="weekly" :class="{ 'weekly--done': done }" :aria-label="t('weekly.title')">
    <div class="head">
      <b class="title">{{ t('weekly.title') }}</b>
      <span class="sub">
        {{ done ? t('weekly.done') : t('weekly.goal', { goal: week.goal }) }}
        <template v-if="!done"> · {{ t('weekly.left', { days: week.days_left }) }}</template>
      </span>
    </div>
    <div class="dots" role="img" :aria-label="t('weekly.progress', { done: Math.min(week.games, week.goal), goal: week.goal })">
      <span v-for="n in week.goal" :key="n" class="dot" :class="{ 'dot--on': n <= week.games }">{{ n <= week.games ? '⭐' : '' }}</span>
    </div>
    <small v-if="week.won" class="won">{{ t('weekly.won', { n: week.won }) }}</small>
  </section>
</template>

<style scoped>
.weekly {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 16px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-sm);
}
.weekly--done {
  background: color-mix(in srgb, var(--bz-sun) 40%, var(--bz-card));
}
.head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 2px 10px;
}
.title {
  font-size: var(--bz-text-lg);
}
.sub,
.won {
  font-size: var(--bz-text-sm);
  color: var(--bz-muted);
}
.dots {
  display: flex;
  gap: 8px;
}
.dot {
  display: grid;
  place-items: center;
  flex: 1;
  max-width: 54px;
  height: 36px;
  border-radius: var(--bz-radius-sm);
  background: color-mix(in srgb, var(--bz-muted) 18%, transparent);
  font-size: 20px;
  line-height: 1;
}
.dot--on {
  background: var(--bz-sun);
}
</style>
