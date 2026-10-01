<script setup>
import { computed } from 'vue'
import { formatPercent, t } from '../../i18n'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * Skill areas (DIFER-like groupings of the games): first-try share this period as
 * a bar, the period before as a tick, a trend word and a band. One measure, one
 * hue; the band is written out, never shown by colour alone.
 */
const props = defineProps({
  /** @type {import('vue').PropType<import('../../types').SkillArea[]>} */
  areas: { type: Array, required: true },
  /** The game names, to list what each area is built from. */
  games: { type: Array, default: () => [] },
})

const names = computed(() => Object.fromEntries(props.games.map(g => [g.id, g.name])))
const hasPrevious = computed(() => props.areas.some(a => a.previousRate != null))
const gameList = area => area.games.map(id => names.value[id] ?? id).join(', ')
const TREND = { up: '↗', flat: '→', down: '↘' }
</script>

<template>
  <section class="skills" :aria-label="t('skills.title')">
    <header class="head">
      <h3 class="title">{{ t('skills.title') }}</h3>
      <span class="sub">{{ t('skills.subtitle') }}</span>
    </header>

    <ul class="list">
      <li v-for="a in areas" :key="a.key" class="area" :class="{ 'area--empty': a.firstTryRate == null }">
        <EmojiArt class="icon" :char="a.emoji" />
        <div class="body">
          <div class="line">
            <b class="label">{{ a.label }}</b>
            <span v-if="a.difer" class="tag" :title="t('skills.diferTitle')">DIFER</span>
          </div>
          <div
            class="meter"
            role="meter"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="a.firstTryRate == null ? undefined : Math.round(a.firstTryRate * 100)"
            :aria-label="t('skills.meterLabel', { area: a.label })"
          >
            <span class="fill" :style="{ '--p': a.firstTryRate ?? 0 }" />
            <span
              v-if="a.previousRate != null"
              class="prev"
              :style="{ left: `${a.previousRate * 100}%` }"
              :title="t('skills.previous', { value: formatPercent(a.previousRate) })"
            />
          </div>
          <div class="games">{{ gameList(a) }}</div>
        </div>
        <div class="result">
          <b class="value">{{ formatPercent(a.firstTryRate) }}</b>
          <span class="band">{{ t(`skills.bands.${a.band}`) }}</span>
          <span v-if="a.trend" class="trend">
            <span aria-hidden="true">{{ TREND[a.trend] }}</span> {{ t(`skills.trend.${a.trend}`) }}
          </span>
          <span v-if="a.stars" class="stars"><EmojiArt char="⭐" /> {{ a.stars }}</span>
        </div>
      </li>
    </ul>

    <p class="legend">
      <span class="legend-item"><span class="key key--fill" aria-hidden="true" /> {{ t('skills.legendNow') }}</span>
      <span v-if="hasPrevious" class="legend-item">
        <span class="key key--prev" aria-hidden="true" /> {{ t('skills.legendPrev') }}
      </span>
    </p>
    <p class="note">{{ t('skills.note') }}</p>
  </section>
</template>

<style scoped>
.skills {
  margin: 14px 0;
  padding: 14px 16px 12px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  break-inside: avoid;
}
.head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 10px;
  margin-bottom: 8px;
}
.title {
  margin: 0;
  font-size: 17px;
}
.sub,
.games,
.legend,
.note {
  font-size: 14px;
  color: var(--bz-muted);
}
.list {
  display: grid;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.area {
  display: grid;
  grid-template-columns: 34px 1fr auto;
  align-items: center;
  gap: 10px;
}
.icon {
  width: 30px;
  height: 30px;
  font-size: 26px;
}
.body {
  min-width: 0;
}
.line {
  display: flex;
  align-items: center;
  gap: 8px;
}
.label {
  font-size: var(--bz-text-sm);
}
.tag {
  padding: 0 6px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
  color: var(--bz-muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
}
.meter {
  position: relative;
  height: 10px;
  margin: 5px 0 3px;
  overflow: hidden;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-chart-grid);
}
.fill {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: var(--bz-chart);
  transform: scaleX(var(--p));
  transform-origin: left;
  transition: transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.prev {
  position: absolute;
  top: -1px;
  bottom: -1px;
  width: 3px;
  margin-left: -1.5px;
  border-radius: 2px;
  background: var(--bz-ink);
}
.games {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.result {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 108px;
  line-height: 1.2;
  text-align: right;
}
.value {
  font-size: 18px;
  font-variant-numeric: tabular-nums;
}
.band,
.trend,
.stars {
  font-size: 13px;
}
.trend {
  color: var(--bz-muted);
}
.stars {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-weight: 700;
  color: var(--bz-muted);
}
.stars :deep(.emoji) {
  font-size: 13px;
}
.area--empty .value {
  color: var(--bz-muted);
}
.legend {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 16px;
  margin: 12px 0 0;
}
.legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.key {
  display: inline-block;
  width: 14px;
  height: 8px;
  border-radius: 4px;
  background: var(--bz-chart);
}
.key--prev {
  width: 3px;
  height: 12px;
  border-radius: 2px;
  background: var(--bz-ink);
}
.note {
  margin: 6px 0 0;
}
@media (max-width: 480px) {
  .area {
    grid-template-columns: 28px 1fr auto;
    gap: 8px;
  }
  .result {
    min-width: 84px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .fill {
    transition: none;
  }
}
@media print {
  .skills {
    background: none;
    padding: 0;
  }
  .fill,
  .prev {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
}
</style>
