<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { BzButton, BzNotice, EmojiArt, SkillMap, SoundProgress } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { formatDate } from '../../modules/beszed/i18n'
import { http } from '../http'
import { fill, texts } from '../texts'

/**
 * What the speech therapist sees behind a share link (public, read-only): the
 * last 90 days per skill area and per game. No sign-in; the link is the key.
 */
const route = useRoute()
const report = ref(null)
const error = ref('')

const pct = v => (v == null ? '–' : `${Math.round(v * 100)}%`)
const date = formatDate
const print = () => window.print()
const played = computed(() => (report.value?.games ?? []).filter(g => g.rounds > 0))

onMounted(async () => {
  try {
    report.value = await http.get(`/api/share/${encodeURIComponent(route.params.token)}`, { quiet401: true }).then(r => r.data)
    document.title = fill(texts.share.docTitle, { name: report.value.child.name })
  } catch (e) {
    error.value = e.response?.status === 404 ? texts.share.expired : texts.share.loadFailed
  }
})
</script>

<template>
  <main class="bz share">
    <BzNotice v-if="error" tone="warn">{{ error }}</BzNotice>
    <p v-else-if="!report" class="muted">{{ texts.share.loading }}</p>

    <article v-else class="doc">
      <header class="top">
        <div>
          <p class="kicker">{{ texts.share.kicker }}</p>
          <h1>{{ report.child.name }}<small v-if="report.child.age"> · {{ report.child.age }}</small></h1>
          <p class="muted">
            {{ fill(texts.share.period, { since: date(report.since), generated: date(report.generatedAt), expires: date(report.expiresAt) }) }}
          </p>
        </div>
        <BzButton class="bz-noprint" @click="print">{{ texts.share.print }}</BzButton>
      </header>

      <section v-if="report.narrative" class="card">
        <h2>{{ texts.share.summary }}</h2>
        <p>{{ report.narrative }}</p>
        <ul v-if="report.recommendations?.length">
          <li v-for="(r, i) in report.recommendations" :key="i">{{ r }}</li>
        </ul>
      </section>

      <SkillMap :areas="report.areas" :games="report.games" />
      <SoundProgress v-if="report.sounds" :sounds="report.sounds" />

      <section class="card">
        <h2>{{ texts.share.perGame }}</h2>
        <p v-if="!played.length" class="muted">{{ texts.share.noPlay }}</p>
        <div v-else class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>{{ texts.share.columns.game }}</th>
                <th>{{ texts.share.columns.skill }}</th>
                <th>{{ texts.share.columns.sessions }}</th>
                <th>{{ texts.share.columns.answers }}</th>
                <th>{{ texts.share.columns.firstTry }}</th>
                <th>{{ texts.share.columns.level }}</th>
                <th>{{ texts.share.columns.last }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="g in played" :key="g.id">
                <td><EmojiArt :char="g.emoji" /> {{ g.name }}</td>
                <td>{{ g.skill }}</td>
                <td>{{ g.sessions }}</td>
                <td>{{ g.rounds }}</td>
                <td>{{ pct(g.firstTryRate) }}</td>
                <td>{{ g.level ? `${g.level} / ${g.maxLevel}` : '–' }}</td>
                <td>{{ date(g.lastPlayed) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <p class="muted foot">{{ texts.share.foot }}</p>
    </article>
  </main>
</template>

<style scoped>
.share {
  max-width: 900px;
}
.doc {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.top {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 8px;
}
.kicker {
  margin: 0;
  font-weight: 700;
  color: var(--bz-muted);
}
h1 {
  margin: 0;
  font-size: clamp(26px, 5vw, 34px);
}
h1 small {
  font-size: 0.6em;
  color: var(--bz-muted);
}
h2 {
  margin: 0 0 6px;
  font-size: 18px;
}
.card {
  margin: 10px 0;
  padding: 14px 16px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
}
.card p {
  margin: 0 0 6px;
}
.muted {
  margin: 2px 0 0;
  color: var(--bz-muted);
  font-size: 14px;
}
.table-wrap {
  overflow-x: auto;
}
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--bz-text-sm);
}
.table th,
.table td {
  padding: 8px 10px;
  border-bottom: 1px solid var(--bz-soft);
  text-align: left;
  white-space: nowrap;
}
.foot {
  margin-top: 8px;
}
@media print {
  .share {
    background: none;
  }
  .card {
    padding: 0;
    background: none;
    break-inside: avoid;
  }
}
</style>
