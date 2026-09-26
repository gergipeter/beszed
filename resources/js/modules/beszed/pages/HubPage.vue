<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { fetchDailyPath, fetchSpotlight } from '../api'
import GuideBubble from '../components/guide/GuideBubble.vue'
import DailyPath from '../components/hub/DailyPath.vue'
import GameTile from '../components/hub/GameTile.vue'
import Spotlight from '../components/hub/Spotlight.vue'
import PlayerStatus from '../components/rewards/PlayerStatus.vue'
import StreakHistory from '../components/rewards/StreakHistory.vue'
import BzButton from '../components/ui/BzButton.vue'
import EmojiArt from '../components/ui/EmojiArt.vue'
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

// DEV MODE
const showDevMode = ref(false)
const devDifficulty = ref(localStorage.getItem('dev-difficulty') || 'auto')

// Persist dev difficulty to localStorage
watch(devDifficulty, (val) => {
  localStorage.setItem('dev-difficulty', val)
})

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

/** The hub's game groups, simple ones first; `from` keeps the tiles' float-in running on across groups. */
const TIERS = [
  { id: 'simple', icon: ICONS.tierSimple },
  { id: 'advanced', icon: ICONS.tierAdvanced },
]
const groups = computed(() => {
  let from = 0
  return TIERS.map(tier => {
    const games = meta.games.filter(g => (g.tier ?? 'simple') === tier.id)
    const group = { ...tier, games, from }
    from += games.length
    return group
  }).filter(group => group.games.length)
})

function play(game) {
  guide.unlock() // inside the tap, so iOS allows audio in the game
  router.push({ name: 'beszed.play', params: { childId: childId.value, game } })
}
</script>

<template>
  <!-- DEV MODE BUTTON -->
  <button
    class="dev-mode-btn"
    @click="showDevMode = !showDevMode"
    title="Toggle dev mode"
    style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; padding: 8px 12px; background: #222; border: 2px solid #0f0; color: #0f0; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 12px;"
  >
    ⚙️ DEV {{ showDevMode ? '✓' : '' }}
  </button>

  <div v-if="showDevMode" class="dev-mode-panel" style="position: fixed; bottom: 70px; right: 20px; width: 300px; background: #1a1a1a; border: 2px solid #0f0; border-radius: 8px; padding: 16px; color: #0f0; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; z-index: 9999; box-shadow: 0 0 20px rgba(0, 255, 0, 0.2);">
    <h3 style="margin: 0 0 12px; border-bottom: 1px solid #0f0; padding-bottom: 8px;">🎮 DEV PANEL</h3>
    <p style="margin: 0 0 8px; font-size: 10px; color: #888;">Memory Game Difficulty Override:</p>
    <select v-model="devDifficulty" style="width: 100%; padding: 6px; background: #0a0a0a; border: 1px solid #0f0; color: #0f0; border-radius: 4px; margin-bottom: 12px; font-family: monospace;">
      <option value="auto">Auto (by level)</option>
      <option value="easy">Easy (Könnyű)</option>
      <option value="medium">Medium (Közepesen nehéz)</option>
      <option value="hard">Hard (Nehéz)</option>
    </select>
    <p style="margin: 0; font-size: 10px; color: #888;">Current: <strong>{{ devDifficulty }}</strong></p>
    <p style="margin: 8px 0 0; font-size: 10px; color: #888;">ℹ️ Refresh page after changes</p>
  </div>

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

  <section v-for="group in groups" :key="group.id" class="group" :aria-labelledby="`hub-group-${group.id}`">
    <header class="group-head">
      <EmojiArt class="group-icon" :char="group.icon" />
      <h2 :id="`hub-group-${group.id}`" class="group-title">{{ t(`hub.tiers.${group.id}.title`) }}</h2>
      <p class="group-hint">{{ t(`hub.tiers.${group.id}.hint`) }}</p>
    </header>
    <div class="tiles">
      <GameTile
        v-for="(game, i) in group.games"
        :key="game.id"
        :game="game"
        :medal="rewards.medal(game.id)"
        :style="{ '--i': group.from + i }"
        @click="play(game.id)"
      />
    </div>
  </section>

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
.group + .group {
  margin-top: 26px;
}
.group-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  column-gap: 10px;
  margin: 0 4px 12px;
}
.group-icon {
  font-size: 30px;
}
.group-title {
  margin: 0;
  font-size: var(--bz-text-lg);
  font-weight: 800;
  line-height: 1.1;
}
.group-hint {
  flex-basis: 100%;
  margin: 2px 0 0;
  font-size: var(--bz-text-sm);
  line-height: 1.25;
  color: var(--bz-muted);
}
/* at least two columns, even on the narrowest phone (the tiles scale their text to fit) */
.tiles {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(170px, calc(50% - 8px)), 1fr));
  gap: 16px;
}
.parents {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 28px;
}
</style>
