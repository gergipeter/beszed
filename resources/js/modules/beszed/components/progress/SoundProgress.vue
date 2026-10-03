<script setup>
import { computed } from 'vue'
import { formatPercent, t } from '../../i18n'

/**
 * How the child does on single sounds (first sounds, sound pairs, rhymes), from the
 * answers the games store per word. Like SkillMap: the first-try share this period as
 * a bar, the period before as a tick, a trend word and a band; one measure, one hue,
 * and everything is written out, never shown by colour alone. The best and the one to
 * practise carry a word, not just a highlight. Sounds with too few answers are left out.
 */
const props = defineProps({
  /** @type {import('vue').PropType<import('../../types').SoundReport>} */
  sounds: { type: Object, required: true },
})

const KINDS = ['start', 'contrast', 'rhyme']
const FLAGS = ['strongest', 'improved', 'weakest']
const TREND = { up: '↗', flat: '→', down: '↘' }

const groups = computed(() =>
  KINDS.map(kind => ({ kind, items: props.sounds.items.filter(i => i.kind === kind) })).filter(g => g.items.length),
)
const flagsOf = item => FLAGS.filter(f => props.sounds[f] === item.key)
const hasPrevious = computed(() => props.sounds.items.some(i => i.previousRate != null))
</script>

<template>
  <section v-if="sounds.attempts > 0" class="sounds" :aria-label="t('sounds.title')">
    <header class="head">
      <h3 class="title">{{ t('sounds.title') }}</h3>
      <span class="sub">{{ t('sounds.subtitle') }}</span>
    </header>

    <p v-if="sounds.summary" class="story">{{ sounds.summary }}</p>
    <p v-if="sounds.tip" class="tip">
      <b>{{ t('sounds.tipTitle') }}:</b> {{ sounds.tip }}
    </p>

    <div v-for="g in groups" :key="g.kind" class="group">
      <h4 class="group-title">{{ t(`sounds.kinds.${g.kind}`) }}</h4>
      <ul class="list">
        <li v-for="s in g.items" :key="s.key" class="sound" :class="{ 'sound--weakest': sounds.weakest === s.key }">
          <b class="chip">{{ s.label }}</b>
          <div class="body">
            <div
              class="meter"
              role="meter"
              aria-valuemin="0"
              aria-valuemax="100"
              :aria-valuenow="s.firstTryRate == null ? undefined : Math.round(s.firstTryRate * 100)"
              :aria-label="t('sounds.meterLabel', { sound: `${s.label} (${t(`sounds.kindName.${s.kind}`)})` })"
            >
              <span class="fill" :style="{ '--p': s.firstTryRate ?? 0 }" />
              <span
                v-if="s.previousRate != null"
                class="prev"
                :style="{ left: `${s.previousRate * 100}%` }"
                :title="t('sounds.previous', { value: formatPercent(s.previousRate) })"
              />
            </div>
            <div class="meta">
              <span v-for="f in flagsOf(s)" :key="f" class="flag" :class="`flag--${f}`">{{ t(`sounds.flags.${f}`) }}</span>
              <span v-if="s.examples.length" class="examples">{{ s.examples.join(', ') }}</span>
            </div>
            <div v-if="s.overallRate != null && s.overallAttempts > s.attempts" class="meta overall">
              {{ t('sounds.overall', { value: formatPercent(s.overallRate), count: s.overallAttempts }) }}
            </div>
          </div>
          <div class="result">
            <b class="value">{{ formatPercent(s.firstTryRate) }}</b>
            <span class="band">{{ t(`skills.bands.${s.band}`) }}</span>
            <span v-if="s.trend" class="trend">
              <span aria-hidden="true">{{ TREND[s.trend] }}</span> {{ t(`skills.trend.${s.trend}`) }}
            </span>
            <span class="count">{{ t('sounds.answers', { count: s.attempts }) }}</span>
          </div>
        </li>
      </ul>
    </div>

    <p class="legend">
      <span class="legend-item"><span class="key key--fill" aria-hidden="true" /> {{ t('skills.legendNow') }}</span>
      <span v-if="hasPrevious" class="legend-item">
        <span class="key key--prev" aria-hidden="true" /> {{ t('skills.legendPrev') }}
      </span>
    </p>
    <p v-if="sounds.few > 0" class="note">{{ t('sounds.few', { count: sounds.few, min: sounds.minAttempts }) }}</p>
    <p class="note">{{ t('sounds.note') }}</p>
  </section>
</template>

<style scoped>
.sounds {
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
.examples,
.overall,
.count,
.legend,
.note {
  font-size: 14px;
  color: var(--bz-muted);
}
.story {
  margin: 0 0 8px;
}
.tip {
  margin: 0 0 12px;
  padding: 8px 12px;
  border-radius: var(--bz-radius);
  background: var(--bz-soft);
}
.group + .group {
  margin-top: 14px;
}
.group-title {
  margin: 0 0 8px;
  font-size: var(--bz-text-sm);
  color: var(--bz-muted);
}
.list {
  display: grid;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.sound {
  display: grid;
  grid-template-columns: 5.25rem 1fr auto;
  align-items: center;
  gap: 10px;
}
.chip {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px 6px;
  border: 2px solid var(--bz-chart-grid);
  border-radius: var(--bz-radius-sm);
  font-size: 20px;
  line-height: 1.2;
  white-space: nowrap;
}
.sound--weakest .chip {
  border-color: var(--bz-guide);
}
.body {
  min-width: 0;
}
.meter {
  position: relative;
  height: 10px;
  margin: 2px 0 4px;
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
.meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 2px 8px;
  line-height: 1.3;
}
.examples {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.flag {
  padding: 0 8px;
  border: 1.5px solid var(--bz-guide);
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}
.flag--strongest::before {
  content: '★ ';
}
.flag--improved::before {
  content: '↗ ';
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
.count {
  font-size: 13px;
}
.trend {
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
  .sound {
    grid-template-columns: 4.5rem 1fr auto;
    gap: 8px;
  }
  .chip {
    padding: 3px 4px;
    font-size: 17px;
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
  .sounds {
    background: none;
    padding: 0;
  }
  .tip {
    background: none;
    border: 1px solid var(--bz-chart-grid);
  }
  .fill,
  .prev {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
}
</style>
