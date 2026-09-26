<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { fetchProgress, fetchProgressHistory } from '../api'
import TrendChart from '../components/charts/TrendChart.vue'
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

const print = () => window.print()

onMounted(load)
watch(days, load)
</script>

<template>
  <PageHeader class="bz-noprint" :title="t('progress.title')" :back-to="{ name: 'beszed.hub', params: { childId } }">
    <select v-model.number="days" class="period" :aria-label="t('progress.period')">
      <option v-for="n in PERIODS" :key="n" :value="n">{{ t('progress.lastDays', { count: n }) }}</option>
    </select>
    <BzButton :icon="ICONS.print" @click="print">{{ t('progress.print') }}</BzButton>
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
            <td>{{ formatPercent(g.firstTryRate) }}</td>
            <td>{{ formatPercent(g.solvedRate) }}</td>
            <td>{{ g.level ? `${g.level} / ${g.maxLevel}` : '–' }}</td>
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
.period {
  padding: 6px 10px;
  border: 2px solid var(--bz-guide);
  border-radius: 12px;
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
@media print {
  .table-wrap {
    background: none;
  }
}
</style>
