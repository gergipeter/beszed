<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { BzButton, BzNotice, EmojiArt, SkillMap } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { http } from '../http'

/**
 * What the speech therapist sees behind a share link (public, read-only): the
 * last 90 days per skill area and per game. No sign-in; the link is the key.
 */
const route = useRoute()
const report = ref(null)
const error = ref('')

const pct = v => (v == null ? '–' : `${Math.round(v * 100)}%`)
const date = v => (v ? new Date(v).toLocaleDateString('hu-HU') : '–')
const print = () => window.print()
const played = computed(() => (report.value?.games ?? []).filter(g => g.rounds > 0))

onMounted(async () => {
  try {
    report.value = await http.get(`/api/share/${encodeURIComponent(route.params.token)}`, { quiet401: true }).then(r => r.data)
    document.title = `${report.value.child.name} – haladás`
  } catch (e) {
    error.value =
      e.response?.status === 404
        ? 'Ez a link lejárt, vagy a szülő visszavonta. Kérj tőle újat.'
        : 'Most nem sikerült betölteni. Próbáld újra kicsit később.'
  }
})
</script>

<template>
  <main class="bz share">
    <BzNotice v-if="error" tone="warn">{{ error }}</BzNotice>
    <p v-else-if="!report" class="muted">Betöltés…</p>

    <article v-else class="doc">
      <header class="top">
        <div>
          <p class="kicker">Beszéd – haladási összefoglaló</p>
          <h1>{{ report.child.name }}<small v-if="report.child.age"> · {{ report.child.age }}</small></h1>
          <p class="muted">
            {{ date(report.since) }} – {{ date(report.generatedAt) }} · a link érvényes: {{ date(report.expiresAt) }}
          </p>
        </div>
        <BzButton class="bz-noprint" @click="print">Nyomtatás</BzButton>
      </header>

      <section v-if="report.narrative" class="card">
        <h2>Összefoglaló</h2>
        <p>{{ report.narrative }}</p>
        <ul v-if="report.recommendations?.length">
          <li v-for="(r, i) in report.recommendations" :key="i">{{ r }}</li>
        </ul>
      </section>

      <SkillMap :areas="report.areas" :games="report.games" />

      <section class="card">
        <h2>Játékonként</h2>
        <p v-if="!played.length" class="muted">Ebben az időszakban még nem volt játék.</p>
        <div v-else class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Játék</th>
                <th>Mit gyakorol</th>
                <th>Végigjátszva</th>
                <th>Válaszok</th>
                <th>Elsőre jó</th>
                <th>Szint</th>
                <th>Utoljára</th>
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

      <p class="muted foot">
        Az adatok egy otthoni gyakorlóalkalmazásból származnak: „elsőre jó” = a gyerek első próbálkozásra jól
        válaszolt. Ez nem standardizált mérés és nem diagnózis; a szülő bármikor visszavonhatja a linket.
      </p>
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
