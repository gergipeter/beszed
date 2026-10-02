<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { useRewardsStore } from '../../stores/rewards'
import EmojiArt from '../ui/EmojiArt.vue'
import LevelBar from './LevelBar.vue'

/** The hub's reward strip: level, streak, today's goal and the way to the sticker album. */
const props = defineProps({
  /** @type {import('vue').PropType<import('../../types').RewardSummary>} */
  summary: { type: Object, required: true },
  stickersTo: { type: [String, Object], required: true },
  earned: { type: Number, default: 0 },
})

const rewards = useRewardsStore()
const goalReached = computed(() => props.summary.daily.done >= props.summary.daily.goal)
</script>

<template>
  <section class="status">
    <LevelBar class="status-level" :level="summary.level" :next="rewards.nextGift" compact />
    <span v-if="summary.streak.days" class="chip" :title="t('rewards.streak', { count: summary.streak.days })">
      <EmojiArt :char="ICONS.fire" :label="t('rewards.streak', { count: summary.streak.days })" /> {{ summary.streak.days }}
    </span>
    <span
      class="chip"
      :class="{ 'chip--done': goalReached }"
      :title="t('rewards.daily', { done: summary.daily.done, goal: summary.daily.goal })"
    >
      <EmojiArt :char="ICONS.target" :label="t('rewards.daily', { done: summary.daily.done, goal: summary.daily.goal })" />
      {{ Math.min(summary.daily.done, summary.daily.goal) }}/{{ summary.daily.goal }}
    </span>
    <RouterLink class="chip chip--link" :to="stickersTo">
      <EmojiArt :char="ICONS.medal" /> {{ t('rewards.title') }} <b class="count">{{ earned }}</b>
    </RouterLink>
  </section>
</template>

<style scoped>
.status {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 10px;
  margin: 0 0 16px;
  padding: 10px 12px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
}
.status-level {
  flex: 1 1 150px;
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 12px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
  font-size: 19px;
  font-weight: 800;
  white-space: nowrap;
}
.chip--done {
  background: color-mix(in srgb, var(--bz-leaf) 22%, var(--bz-card));
}
.chip--link {
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  box-shadow: var(--bz-shadow-sm);
  transition: transform 0.08s;
}
.chip--link:active {
  transform: translateY(2px);
}
.count {
  display: inline-grid;
  place-items: center;
  min-width: 26px;
  height: 26px;
  padding: 0 6px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-size: 16px;
}
</style>
