<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { fetchProgress, fetchProgressHistory } from '../api'
import TrendChart from '../components/charts/TrendChart.vue'
import SkillMap from '../components/progress/SkillMap.vue'
import SoundProgress from '../components/progress/SoundProgress.vue'
import BzButton from '../components/ui/BzButton.vue'
import BzNotice from '../components/ui/BzNotice.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useAsync } from '../composables/useAsync'
import { useModuleContext } from '../composables/useModuleContext'
import { ICONS } from '../config/icons'
import { formatDate, formatDay, formatPercent, t } from '../i18n'
import { useRewardsStore } from '../stores/rewards'

/** Weekly trends + per-game summary; printable for the speech therapist (logopédus). */
const PERIODS = [7, 30, 90]

const { childId } = useModuleContext()
const rewards = useRewardsStore()
const days = ref(30)
/** The same period drives the charts (whole weeks, at least 4 for a trend) and the table. */
const weeks = computed(() => Math.max(4, Math.ceil(days.value / 7)))

const { data, error, loading, run: load } = useAsync(
  async () => {
    const [report, history] = await Promise.all([
      fetchProgress(childId.value, days.value),
      fetchProgressHistory(childId.value, weeks.value),
    ])
    return { report, history }
  },
  { fallback: t('progress.loadFailed') },
)
const report = computed(() => data.value?.report ?? null)

const points = key =>
  (data.value?.history.weeks ?? []).map(w => ({
    key: w.week,
    label: formatDay(w.week),
    long: t('progress.charts.weekOf', { date: formatDay(w.week, { month: 'long', day: 'numeric' }) }),
    value: w[key],
  }))
const gamePoints = computed(() => points('games'))
const ratePoints = computed(() => points('firstTryRate'))

/** Stars earned per skill area this period: one bar per area, not a time series. */
const starPoints = computed(
  () =>
    report.value?.areas.map(a => ({
      key: a.key,
      label: a.emoji,
      long: `${a.label}: ${t('progress.charts.starsCount', { count: a.stars })}`,
      value: a.stars,
    })) ?? [],
)

/** "This week", in plain words for the parent: what was done, and (only ever) the good news against last week. */
const thisWeek = computed(() => {
  const weeksData = data.value?.history.weeks ?? []
  const games = weeksData.at(-1)?.games ?? 0
  if (!games) return null
  const days = (rewards.summary?.streak.recent ?? []).slice(-7).filter(d => d.played).length
  const prev = weeksData.at(-2)?.games ?? 0
  return { games, days: days || 1, prev, more: games > prev && prev > 0 }
})

const print = () => window.print()
// jsPDF (+ html2canvas/purify) is ~400 KB — only fetch it when the PDF is requested.
const exportPdf = async () => {
  const { downloadClinicalReport } = await import('../services/reports/clinicalReport')
  downloadClinicalReport(report.value)
}

onMounted(load)
watch(days, load)
</script>

<template>
  <PageHeader class="bz-noprint" :title="t('progress.title')" :back-to="{ name: 'beszed.hub', params: { childId } }">
    <select v-model.number="days" class="period" :aria-label="t('progress.period')">
      <option v-for="n in PERIODS" :key="n" :value="n">{{ t('progress.lastDays', { count: n }) }}</option>
    </select>
    <BzButton :icon="ICONS.print" @click="print">{{ t('progress.print') }}</BzButton>
    <BzButton :icon="ICONS.download" @click="exportPdf">{{ t('progress.exportPdf') }}</BzButton>
    <BzButton :icon="ICONS.share" :to="{ name: 'beszed.shares', params: { childId } }">{{ t('share.title') }}</BzButton>
  </PageHeader>

  <BzNotice v-if="error" tone="warn">
    {{ error }}
    <BzButton size="sm" @click="load">{{ t('common.retry') }}</BzButton>
  </BzNotice>

  <template v-else-if="report">
    <h2 class="heading">{{ t('progress.heading', { name: report.child.name, since: report.since }) }}</h2>
    <p v-if="rewards.summary" class="summary">
      {{
        t('progress.summary', {
          level: rewards.summary.level.number,
          stars: rewards.summary.stars,
          streak: rewards.summary.streak.days,
          stickers: rewards.earnedCount,
        })
      }}
    </p>

    <BzNotice v-if="thisWeek" class="week">
      <p class="week-text">{{ t('progress.thisWeek', { days: thisWeek.days, games: thisWeek.games }) }}</p>
      <p v-if="thisWeek.more" class="week-text">{{ t('progress.thisWeekMore', { prev: thisWeek.prev }) }}</p>
      <p v-else class="week-text week-text--tip">{{ t('progress.thisWeekTip') }}</p>
    </BzNotice>

    <SkillMap :areas="report.areas" :games="report.games" />
    <SoundProgress v-if="report.sounds" :sounds="report.sounds" />

    <BzNotice v-if="report.narrative" class="narrative">
      <p class="narrative-text">{{ report.narrative }}</p>
      <ul v-if="report.recommendations?.length" class="recommendations">
        <li v-for="(item, i) in report.recommendations" :key="i">{{ item }}</li>
      </ul>
    </BzNotice>

    <!-- two single-measure charts (never one chart with two scales); refetch keeps the frame, dimmed -->
    <div class="charts" :aria-busy="loading">
      <TrendChart
        :title="t('progress.charts.games')"
        :subtitle="t('progress.charts.weeks', { count: weeks })"
        :points="gamePoints"
        kind="bar"
        :empty-text="t('progress.charts.empty')"
      />
      <TrendChart
        :title="t('progress.charts.rate')"
        :subtitle="t('progress.charts.weeks', { count: weeks })"
        :points="ratePoints"
        kind="line"
        :max="1"
        :format="formatPercent"
        :empty-text="t('progress.charts.empty')"
      />
      <TrendChart
        :title="t('progress.charts.stars')"
        :subtitle="t('progress.charts.starsSubtitle')"
        :points="starPoints"
        kind="bar"
        :empty-text="t('progress.charts.empty')"
      />
    </div>
    <details class="weekly">
      <summary>{{ t('progress.charts.table') }}</summary>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('progress.charts.week') }}</th>
              <th>{{ t('progress.charts.games_col') }}</th>
              <th>{{ t('progress.charts.answers') }}</th>
              <th>{{ t('progress.columns.firstTry') }}</th>
              <th>{{ t('progress.charts.minutes') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="w in data.history.weeks" :key="w.week">
              <td>{{ formatDay(w.week, { month: 'long', day: 'numeric' }) }}</td>
              <td>{{ w.games }}</td>
              <td>{{ w.answers }}</td>
              <td>{{ formatPercent(w.firstTryRate) }}</td>
              <td>{{ w.minutes }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </details>

    <div class="table-wrap" :aria-busy="loading">
      <table class="table">
        <thead>
          <tr>
            <th>{{ t('progress.columns.game') }}</th>
            <th>{{ t('progress.columns.skill') }}</th>
            <th>{{ t('progress.columns.sessions') }}</th>
            <th>{{ t('progress.columns.rounds') }}</th>
            <th>{{ t('progress.columns.stars') }}</th>
            <th>{{ t('progress.columns.firstTry') }}</th>
            <th>{{ t('progress.columns.solved') }}</th>
            <th>{{ t('progress.columns.level') }}</th>
            <th>{{ t('progress.columns.lastPlayed') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="g in report.games" :key="g.id">
            <td>{{ g.emoji }} {{ g.name }}</td>
            <td>{{ g.skill }}</td>
            <td>{{ g.sessions }}</td>
            <td>{{ g.rounds }}</td>
            <td>⭐ {{ g.stars }}</td>
            <td>{{ formatPercent(g.firstTryRate) }}</td>
            <td>{{ formatPercent(g.solvedRate) }}</td>
            <td>
              <span v-if="g.level" class="level-cell">
                <span class="level-meter" role="meter" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.round(g.levelProgress * 100)">
                  <span class="level-fill" :style="{ '--p': g.levelProgress }" />
                </span>
                {{ g.level }} / {{ g.maxLevel }}
              </span>
              <span v-else>–</span>
            </td>
            <td>{{ formatDate(g.lastPlayed) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <BzNotice>{{ t('progress.levelNote') }}</BzNotice>
  </template>

  <p v-else-if="loading" class="bz-loading" aria-busy="true">{{ t('common.loading') }}</p>
</template>

<style scoped>
.week-text {
  margin: 0 0 4px;
  font-weight: 700;
}
.week-text--tip {
  font-weight: 400;
  color: var(--bz-muted);
}
.period {
  padding: 6px 10px;
  border: 2px solid var(--bz-guide);
  border-radius: var(--bz-radius-sm);
  background: var(--bz-soft);
  color: var(--bz-ink);
  font: inherit;
  font-size: var(--bz-text-sm);
}
.heading {
  margin-bottom: 4px;
  font-size: 22px;
}
.summary {
  margin: 0 0 14px;
  font-weight: 700;
  color: var(--bz-muted);
}
.narrative-text {
  margin: 0 0 6px;
}
.recommendations {
  margin: 6px 0 0;
  padding-left: 20px;
}
.recommendations li {
  margin: 2px 0;
}
.table-wrap {
  overflow-x: auto;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  transition: opacity 0.15s;
}
.table-wrap[aria-busy='true'],
.charts[aria-busy='true'] {
  opacity: 0.6;
}
.charts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 14px;
  transition: opacity 0.15s;
}
.weekly {
  margin: 10px 0 18px;
}
.weekly summary {
  cursor: pointer;
  font-weight: 700;
  color: var(--bz-muted);
}
.weekly .table-wrap {
  margin-top: 8px;
}
@media print {
  .charts {
    grid-template-columns: 1fr 1fr;
    break-inside: avoid;
  }
}
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--bz-text-sm);
}
.table th,
.table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--bz-soft);
  text-align: left;
  white-space: nowrap;
}
.level-cell {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-variant-numeric: tabular-nums;
}
.level-meter {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 8px;
  overflow: hidden;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-chart-grid);
}
.level-fill {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: var(--bz-chart);
  transform: scaleX(var(--p));
  transform-origin: left;
}
@media (prefers-reduced-motion: no-preference) {
  .level-fill {
    transition: transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
  }
}
@media print {
  .table-wrap {
    background: none;
  }
}
</style>
