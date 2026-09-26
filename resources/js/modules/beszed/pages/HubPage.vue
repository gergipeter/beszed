<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { fetchDailyPath, fetchSpotlight } from '../api'
import GuideBubble from '../components/guide/GuideBubble.vue'
import DailyPath from '../components/hub/DailyPath.vue'
import GameTile from '../components/hub/GameTile.vue'
import Spotlight from '../components/hub/Spotlight.vue'
import PlayerStatus from '../components/rewards/PlayerStatus.vue'
import StreakHistory from '../components/rewards/StreakHistory.vue'
import BzButton from '../components/ui/BzButton.vue'
import { useModuleContext } from '../composables/useModuleContext'
import { ICONS } from '../config/icons'
import { config } from '../config/options'
import { t } from '../i18n'
import { useGuideStore } from '../stores/guide'
import { useMetaStore } from '../stores/meta'
import { useRewardsStore } from '../stores/rewards'

const { childId, childName, guideName } = useModuleContext()
const meta = useMetaStore()
const rewards = useRewardsStore()
const guide = useGuideStore()
const router = useRouter()

function greet() {
  guide.unlock()
  guide.celebrate()
  const params = { child: childName.value, guide: guideName.value }
  guide.speak([{ rec: 'greet', alt: t(childName.value ? 'hub.greetingNamed' : 'hub.greeting', params) }])
}

/** Today's path; stays hidden when it can't be loaded (e.g. offline for the first time today). */
const path = ref(null)
onMounted(() => {
  fetchDailyPath(childId.value)
    .then(p => (path.value = p))
    .catch(() => {})
})

/** "Ma ezt gyakoroljuk": stays hidden until there's enough recent play to tell what's weak. */
const spotlightGameId = ref(null)
onMounted(() => {
  fetchSpotlight(childId.value)
    .then(s => (spotlightGameId.value = s?.game ?? null))
    .catch(() => {})
})
const spotlightGame = computed(() => (spotlightGameId.value ? meta.game(spotlightGameId.value) : null))

function play(game) {
  guide.unlock() // inside the tap, so iOS allows audio in the game
  router.push({ name: 'beszed.play', params: { childId: childId.value, game } })
}
</script>

<template>
  <GuideBubble tag="header" size="lg" :name="guideName" :avatar-label="t('hub.greetLabel', { guide: guideName })" @press="greet">
    <h1 class="hello">{{ childName ? t('hub.helloNamed', { child: childName }) : t('hub.hello') }}</h1>
    <p class="intro">{{ t('hub.intro', { guide: guideName }) }}</p>
  </GuideBubble>

  <PlayerStatus
    v-if="rewards.summary"
    :summary="rewards.summary"
    :earned="rewards.earnedCount"
    :stickers-to="{ name: 'beszed.rewards', params: { childId } }"
  />
  <StreakHistory v-if="rewards.summary?.streak.recent.some(d => d.played)" :days="rewards.summary.streak.recent" />

  <Spotlight v-if="spotlightGame" :game="spotlightGame" @play="play" />

  <DailyPath v-if="path && meta.games.length" :path="path" :games="meta.games" @play="play" />

  <div class="tiles">
    <GameTile
      v-for="(game, i) in meta.games"
      :key="game.id"
      :game="game"
      :medal="rewards.medal(game.id)"
      :style="{ '--i': i }"
      @click="play(game.id)"
    />
  </div>

  <nav class="parents" :aria-label="t('hub.forParents')">
    <BzButton :to="{ name: 'beszed.recordings', params: { childId } }" :icon="ICONS.mic">
      {{ t('recordings.title') }}
    </BzButton>
    <BzButton :to="{ name: 'beszed.progress', params: { childId } }" :icon="ICONS.chart">
      {{ t('progress.title') }}
    </BzButton>
    <BzButton :to="{ name: 'beszed.settings', params: { childId } }" :icon="ICONS.settings">
      {{ t('settings.title') }}
    </BzButton>
    <BzButton v-if="config.exitTo" :to="config.exitTo" :icon="ICONS.family">{{ t('hub.exit') }}</BzButton>
  </nav>
</template>

<style scoped>
.hello {
  margin: 0;
  font-size: clamp(30px, 6.5vw, 46px);
  font-weight: 800;
  line-height: 1.05;
}
.intro {
  margin: 2px 0 0;
  font-size: var(--bz-text-md);
  line-height: 1.25;
  color: var(--bz-muted);
}
.tiles {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 16px;
}
.parents {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 28px;
}
</style>
