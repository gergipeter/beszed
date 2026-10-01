<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { fetchDailyPath, fetchSpotlight } from '../api'
import GameFolders from '../components/hub/GameFolders.vue'
import GardenMap from '../components/garden/GardenMap.vue'
import GardenSky from '../components/garden/GardenSky.vue'
import GuideBubble from '../components/guide/GuideBubble.vue'
import DailyPath from '../components/hub/DailyPath.vue'
import InstallApp from '../components/hub/InstallApp.vue'
import Spotlight from '../components/hub/Spotlight.vue'
import PlayerStatus from '../components/rewards/PlayerStatus.vue'
import StreakHistory from '../components/rewards/StreakHistory.vue'
import BzButton from '../components/ui/BzButton.vue'
import EmojiArt from '../components/ui/EmojiArt.vue'
import { useModuleContext } from '../composables/useModuleContext'
import { ICONS } from '../config/icons'
import { config } from '../config/options'
import { t } from '../i18n'
import { flyTo } from '../services/effects/fly'
import { useGuideStore } from '../stores/guide'
import { useMetaStore } from '../stores/meta'
import { useRewardsStore } from '../stores/rewards'

const { childId, childName, childSign, guideName, premium } = useModuleContext()
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

/**
 * The garden grows one plant per finished game. What the child saw last time is
 * remembered on this device, so the plants grown since then sprout in front of them.
 */
const plants = computed(() => rewards.summary?.sessions ?? 0)
const sproutFrom = ref(Infinity)
const seenKey = () => `beszed.garden.${childId.value}`
watch(
  plants,
  now => {
    if (!rewards.summary || sproutFrom.value !== Infinity) return
    let seen = null
    try {
      seen = localStorage.getItem(seenKey())
      localStorage.setItem(seenKey(), String(now))
    } catch {
      /* private mode: no sprouting, the garden still shows */
    }
    sproutFrom.value = seen === null ? now : Math.min(Number(seen), now)
  },
  { immediate: true },
)
const newPlants = computed(() => (sproutFrom.value === Infinity ? 0 : plants.value - sproutFrom.value))

/** Folders (games grouped by what they develop) or the garden map; the choice is remembered on this device. */
const VIEW_KEY = 'beszed.hub.view'
const readView = () => {
  try {
    return localStorage.getItem(VIEW_KEY) === 'garden' ? 'garden' : 'folders'
  } catch {
    return 'folders'
  }
}
const view = ref(readView())
function setView(v) {
  view.value = v
  try {
    localStorage.setItem(VIEW_KEY, v)
  } catch {
    /* not kept */
  }
}

const header = ref(null)
let flying = false

/** Csillám flies to the game's stone, then the game opens. */
async function play(game, stone) {
  if (flying) return
  guide.unlock() // inside the tap, so iOS allows audio in the game
  flying = true
  const target = stone ?? document.querySelector(`[data-game="${game}"] .stone`)
  const visible = target && target.getBoundingClientRect().bottom > 0 && target.getBoundingClientRect().top < innerHeight
  await flyTo(header.value?.$el.querySelector('.csillam'), visible ? target : null)
  flying = false
  router.push({ name: 'beszed.play', params: { childId: childId.value, game } })
}
</script>

<template>
  <GardenSky />

  <GuideBubble
    ref="header"
    tag="header"
    size="lg"
    :name="guideName"
    :avatar-label="t('hub.greetLabel', { guide: guideName })"
    @press="greet"
  >
    <h1 class="hello">
      {{ childName ? t('hub.helloNamed', { child: childName }) : t('hub.hello') }}
      <EmojiArt v-if="childSign" class="sign" :char="childSign" :label="t('hub.sign')" />
    </h1>
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

  <p v-if="newPlants > 0" class="grew" role="status">
    <EmojiArt :char="ICONS.sprout" /> {{ t(newPlants === 1 ? 'hub.grewOne' : 'hub.grewMany', { count: newPlants }) }}
  </p>

  <div class="views" role="tablist">
    <button
      v-for="v in ['folders', 'garden']"
      :key="v"
      type="button"
      role="tab"
      class="view"
      :class="{ 'view--on': view === v }"
      :aria-selected="view === v"
      @click="setView(v)"
    >
      <EmojiArt :char="v === 'folders' ? '🗂️' : '🌳'" /> {{ t(`hub.views.${v}`) }}
    </button>
  </div>

  <GameFolders
    v-if="view === 'folders' && meta.meta?.folders?.length"
    :folders="meta.meta.folders"
    :games="meta.games"
    :medals="rewards.summary?.medals ?? {}"
    :path-games="path?.games ?? []"
    @play="play"
  />
  <GardenMap
    v-else
    :games="meta.games"
    :medals="rewards.summary?.medals ?? {}"
    :path="path"
    :spotlight="spotlightGameId"
    :plants="plants"
    :sprout-from="sproutFrom"
    @play="play"
  />

  <nav class="parents" :aria-label="t('hub.forParents')">
    <BzButton :to="{ name: 'beszed.recordings', params: { childId } }" :icon="ICONS.mic">
      {{ t('recordings.title') }}
    </BzButton>
    <BzButton :to="{ name: 'beszed.journey', params: { childId } }" :icon="ICONS.map">
      {{ t('journey.title') }}
    </BzButton>
    <BzButton :to="{ name: 'beszed.progress', params: { childId } }" :icon="ICONS.chart">
      {{ t('progress.title') }}
    </BzButton>
    <BzButton :to="{ name: 'beszed.settings', params: { childId } }" :icon="ICONS.settings">
      {{ t('settings.title') }}
    </BzButton>
    <BzButton v-if="!premium" :to="{ name: 'beszed.premium', params: { childId } }" :icon="ICONS.star">
      {{ t('premium.title') }}
    </BzButton>
    <InstallApp />
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
/* the child's óvodai jel by their name, like on their towel and cup */
.sign {
  display: inline-block;
  margin-left: 6px;
  font-size: 0.85em;
  vertical-align: -0.08em;
  animation: sign-wave 2.6s ease-in-out infinite;
}
@keyframes sign-wave {
  50% {
    transform: rotate(-10deg) scale(1.08);
  }
}
.intro {
  margin: 2px 0 0;
  font-size: var(--bz-text-md);
  line-height: 1.25;
  color: var(--bz-muted);
}
.grew {
  display: flex;
  align-items: center;
  gap: 8px;
  width: fit-content;
  margin: 0 auto 14px;
  padding: 8px 16px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-size: var(--bz-text-md);
  font-weight: 800;
  box-shadow: var(--bz-shadow);
  animation: grew 0.6s var(--bz-spring) 0.4s backwards;
}
@keyframes grew {
  from {
    opacity: 0;
    transform: translateY(12px) scale(0.8);
  }
}
.views {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin: 4px 0 16px;
}
.view {
  padding: 8px 18px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-weight: 800;
  font-size: var(--bz-text-md);
  box-shadow: var(--bz-shadow-sm);
  transition: transform 0.3s var(--bz-spring), background 0.2s;
}
.view--on {
  background: var(--bz-sun);
  transform: scale(1.06);
}
.parents {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 28px;
}
</style>
