<script setup>
import { onMounted, ref, watch } from 'vue'
import { fetchProgress } from '../api'
import BzButton from '../components/ui/BzButton.vue'
import BzNotice from '../components/ui/BzNotice.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useAsync } from '../composables/useAsync'
import { useModuleContext } from '../composables/useModuleContext'
import { ICONS } from '../config/icons'
import { formatDate, formatPercent, t } from '../i18n'

/** Per-game summary; printable for the speech therapist (logopédus). */
const PERIODS = [7, 30, 90]

const { childId } = useModuleContext()
const days = ref(30)
const { data: report, error, loading, run: load } = useAsync(() => fetchProgress(childId.value, days.value), {
  fallback: t('progress.loadFailed'),
})

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
    <div class="table-wrap" :aria-busy="loading">
      <table class="table">
        <thead>
          <tr>
            <th>{{ t('progress.columns.game') }}</th>
            <th>{{ t('progress.columns.skill') }}</th>
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
  font-size: 22px;
}
.table-wrap {
  overflow-x: auto;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  transition: opacity 0.15s;
}
.table-wrap[aria-busy='true'] {
  opacity: 0.6;
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
