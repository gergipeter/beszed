<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { fetchJourney } from '../api'
import BzButton from '../components/ui/BzButton.vue'
import BzNotice from '../components/ui/BzNotice.vue'
import EmojiArt from '../components/ui/EmojiArt.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useAsync } from '../composables/useAsync'
import { useModuleContext } from '../composables/useModuleContext'
import { ICONS } from '../config/icons'
import { t } from '../i18n'

/**
 * "Utazás": every skill area as a track of games. Each step shows where the child stands
 * (new, practising, mastered) and the next one is lit; the big button plays what Csillám
 * suggests next. Nothing here is a test result: it only describes how the games went.
 */
const { childId, childName } = useModuleContext()
const router = useRouter()

const { data, error, run: load } = useAsync(() => fetchJourney(childId.value), { fallback: t('journey.loadFailed') })
onMounted(load)

const areas = computed(() => data.value?.areas ?? [])
const stepById = computed(() => Object.fromEntries(areas.value.flatMap(a => a.steps).map(s => [s.id, s])))
const recommended = computed(() => stepById.value[data.value?.recommended?.game] ?? null)

const hub = computed(() => ({ name: 'beszed.hub', params: { childId: childId.value } }))
const play = game => router.push({ name: 'beszed.play', params: { childId: childId.value, game } })

const STATE_ICON = { new: '', learning: ICONS.sprout, mastered: ICONS.star }
const label = step =>
  [
    step.name,
    t(`journey.state.${step.state}`),
    step.level && step.maxLevel ? t('journey.level', { level: step.level, max: step.maxLevel }) : '',
    step.held ? t('journey.held') : '',
  ]
    .filter(Boolean)
    .join('. ')
</script>

<template>
  <PageHeader :title="t('journey.title')" :back-to="hub" />
  <p class="lead">{{ childName ? t('journey.leadNamed', { child: childName }) : t('journey.lead') }}</p>

  <BzNotice v-if="error" kind="error" :message="error" @retry="load" />

  <template v-else-if="data">
    <section v-if="recommended" class="next" :aria-label="t('journey.next')">
      <h2>{{ t('journey.next') }}</h2>
      <BzButton variant="primary" @click="play(recommended.id)">
        <EmojiArt :char="recommended.emoji" /> {{ recommended.name }}
      </BzButton>
    </section>

    <section v-for="a in areas" :key="a.key" class="area" :aria-label="a.label">
      <header class="head">
        <EmojiArt class="area-icon" :char="a.emoji" />
        <h2 class="area-name">{{ a.label }}</h2>
        <span class="count">{{ t('journey.count', { done: a.mastered, total: a.total }) }}</span>
      </header>

      <ol class="steps">
        <li v-for="s in a.steps" :key="s.id">
          <button
            type="button"
            class="step"
            :class="[`step--${s.state}`, { 'step--next': a.next === s.id }]"
            :style="{ '--c': s.color }"
            :aria-label="label(s)"
            :aria-current="a.next === s.id ? 'step' : undefined"
            @click="play(s.id)"
          >
            <span class="disc">
              <EmojiArt class="art" :char="s.emoji" />
              <span v-if="STATE_ICON[s.state]" class="mark" aria-hidden="true"><EmojiArt :char="STATE_ICON[s.state]" /></span>
              <span v-if="s.held" class="held" aria-hidden="true"><EmojiArt :char="ICONS.lock" /></span>
            </span>
            <span class="name">{{ s.name }}</span>
          </button>
        </li>
      </ol>
    </section>

    <p class="note">{{ t('journey.note') }}</p>
  </template>
</template>

<style scoped>
.lead {
  margin: 0 0 14px;
  font-size: var(--bz-text);
}
.next {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  padding: 14px 18px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-sm);
}
.next h2 {
  margin: 0;
  flex: 1 1 140px;
  font-size: var(--bz-text-md);
}
.area {
  margin-bottom: 14px;
  padding: 12px 14px 14px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-sm);
}
.head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
}
.area-icon {
  font-size: 26px;
}
.area-name {
  flex: 1;
  margin: 0;
  font-size: var(--bz-text-md);
}
.count {
  font-weight: 800;
  font-size: var(--bz-text-sm);
}
.steps {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 10px;
  margin: 0;
  padding: 6px 4px 8px;
  list-style: none;
}
.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  width: 84px;
  padding: 0;
  background: none;
}
.disc {
  position: relative;
  display: grid;
  place-items: center;
  width: 64px;
  aspect-ratio: 1;
  border-radius: 50%;
  background: var(--c);
  box-shadow: inset 0 -4px 0 rgba(0, 0, 0, 0.1);
}
/* not played yet: paler and dashed (the state is also written in the aria-label) */
.step--new .disc {
  opacity: 0.55;
  outline: 2px dashed rgba(0, 0, 0, 0.25);
  outline-offset: 2px;
}
.step--mastered .disc {
  outline: 3px solid var(--bz-ink);
  outline-offset: 2px;
}
.step--next .disc {
  outline: 4px solid var(--bz-ink);
  outline-offset: 3px;
  animation: nudge 1.6s ease-in-out infinite;
}
.art {
  font-size: 30px;
}
.mark,
.held {
  position: absolute;
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-sm);
  font-size: 14px;
  line-height: 1;
}
.mark {
  top: -4px;
  right: -4px;
}
.held {
  bottom: -4px;
  right: -4px;
}
.name {
  font-weight: 800;
  font-size: var(--bz-text-sm);
  line-height: 1.15;
  text-align: center;
  overflow-wrap: anywhere;
}
.note {
  margin: 4px 0 0;
  font-size: var(--bz-text-sm);
}
@keyframes nudge {
  50% {
    transform: scale(1.06);
  }
}
@media (prefers-reduced-motion: reduce) {
  .step--next .disc {
    animation: none;
  }
}
</style>
