<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { fetchDailyPath, fetchSpotlight } from '../api'
import GameFolders from '../components/hub/GameFolders.vue'
import GardenMap from '../components/garden/GardenMap.vue'
import GardenSky from '../components/garden/GardenSky.vue'
import GuideBubble from '../components/guide/GuideBubble.vue'
import DailyGift from '../components/hub/DailyGift.vue'
import DailyPath from '../components/hub/DailyPath.vue'
import GamesTabs from '../components/hub/GamesTabs.vue'
import WeatherChip from '../components/hub/WeatherChip.vue'
import ParentMenu from '../components/hub/ParentMenu.vue'
import Spotlight from '../components/hub/Spotlight.vue'
import PlayerStatus from '../components/rewards/PlayerStatus.vue'
import StreakHistory from '../components/rewards/StreakHistory.vue'
import WeeklyChallenge from '../components/rewards/WeeklyChallenge.vue'
import EmojiArt from '../components/ui/EmojiArt.vue'
import { useDailyGift } from '../composables/useDailyGift'
import { useModuleContext } from '../composables/useModuleContext'
import { useWeather } from '../composables/useWeather'
import { useWelcome } from '../composables/useWelcome'
import { ICONS } from '../config/icons'
import { t } from '../i18n'
import { bloom, gift as giftSfx, giggle } from '../services/audio/sfx'
import { flyTo } from '../services/effects/fly'
import { buzz } from '../services/touch/feel'
import { useGuideStore } from '../stores/guide'
import { useMetaStore } from '../stores/meta'
import { useRewardsStore } from '../stores/rewards'

const { childId, childName, childSign, guideName } = useModuleContext()
const meta = useMetaStore()
const rewards = useRewardsStore()
const guide = useGuideStore()
const router = useRouter()

const goalDone = () => Boolean(rewards.summary && rewards.summary.daily.done >= rewards.summary.daily.goal)
const nameOrPet = () => childName.value || t('hub.pet')
/** What Csillám says now: a welcome that fits the visit, then a caring word every so often. */
const welcome = useWelcome({
  childId: childId.value,
  child: nameOrPet(),
  goalDone,
  intro: t('hub.intro', { guide: guideName.value }),
})
let settled = false
watch(
  () => rewards.summary,
  summary => {
    if (!summary || settled) return
    settled = true
    welcome.settle()
  },
  { immediate: true },
)
const CARE_EVERY_MS = 25000
let careTimer
onMounted(() => (careTimer = setInterval(() => !guide.talking && welcome.next(), CARE_EVERY_MS)))
onBeforeUnmount(() => clearInterval(careTimer))

/**
 * The weather, top left. Csillám says it once on every visit to the app: on the first tap that
 * is not meant for something else (a game, the menus, Csillám herself, who then says it with her hello).
 */
const weather = useWeather()
const sayWeather = () => {
  guide.unlock()
  guide.speak([weather.sentence.value])
}
const ELSEWHERE = 'header, [data-game], dialog, a, .menu-btn, .weather-chip'
function announceOnTap(event) {
  if (event.target.closest?.(ELSEWHERE) || !weather.shouldAnnounce()) return
  document.removeEventListener('pointerdown', announceOnTap, true)
  sayWeather()
}
onMounted(async () => {
  if (!(await weather.load())) return
  document.addEventListener('pointerdown', announceOnTap, true)
})
onBeforeUnmount(() => document.removeEventListener('pointerdown', announceOnTap, true))

function greet() {
  guide.unlock()
  guide.celebrate()
  giggle() // tickled: a giggle and a little buzz
  buzz([12, 40, 12])
  const params = { child: childName.value, guide: guideName.value }
  // the very first hello can be the parent's own recording; later ones say what is on screen
  const first = welcome.line.value === t('hub.intro', { guide: guideName.value })
  const hello = first
    ? [{ rec: 'greet', alt: t(childName.value ? 'hub.greetingNamed' : 'hub.greeting', params) }]
    : [welcome.line.value]
  // the first hello of a visit carries the weather too
  const withWeather = weather.shouldAnnounce()
  if (withWeather) document.removeEventListener('pointerdown', announceOnTap, true)
  guide.speak(withWeather ? [...hello, weather.sentence.value] : hello)
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
const gift = useDailyGift(childId.value)
/** The daily gift: always a flower, planted in the garden for the day. */
function openGift() {
  guide.unlock()
  if (!gift.open()) return
  giftSfx()
  buzz([15, 50, 15, 50, 25])
  guide.celebrate()
  guide.speak([t('hub.gift.speech')])
}
const plants = computed(() => (rewards.summary?.sessions ?? 0) + gift.total.value)
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
/** Each new plant pops up with a little bloom sound, in step with its sprouting. */
watch(
  newPlants,
  (now, before = 0) => {
    for (let i = before; i < Math.min(now, before + 6); i++) setTimeout(bloom, 600 + (i - before) * 250)
  },
  { immediate: true },
)

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
  <ParentMenu />
  <WeatherChip
    v-if="weather.weather.value"
    :icon="weather.icon.value"
    :temp="weather.tempText.value"
    :label="t('weather.label', { sentence: weather.sentence.value })"
    @press="sayWeather"
  />

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
    <p class="intro">{{ welcome.line.value }}</p>
  </GuideBubble>

  <PlayerStatus
    v-if="rewards.summary"
    :summary="rewards.summary"
    :earned="rewards.earnedCount"
    :stickers-to="{ name: 'beszed.rewards', params: { childId } }"
  />
  <StreakHistory v-if="rewards.summary?.streak.recent.some(d => d.played)" :days="rewards.summary.streak.recent" />
  <WeeklyChallenge v-if="rewards.summary?.week" :week="rewards.summary.week" />

  <DailyGift :available="gift.available.value" @open="openGift" />

  <Spotlight v-if="spotlightGame" :game="spotlightGame" @play="play" />

  <DailyPath v-if="path && meta.games.length" :path="path" :games="meta.games" @play="play" />

  <template v-if="view === 'folders' && meta.meta?.folders?.length">
    <GamesTabs class="games-tabs-row" :view="view" @change="setView" />
    <GameFolders
      :folders="meta.meta.folders"
      :games="meta.games"
      :medals="rewards.summary?.medals ?? {}"
      :path-games="path?.games ?? []"
      @play="play"
    />
  </template>
  <!-- the garden has the tabs in its own toolbar, on the row with the categories and the map button -->
  <GardenMap
    v-else
    :games="meta.games"
    :medals="rewards.summary?.medals ?? {}"
    :path="path"
    :spotlight="spotlightGameId"
    :plants="plants"
    :sprout-from="sproutFrom"
    :folders="meta.meta?.folders ?? []"
    @play="play"
  >
    <template #head><GamesTabs :view="view" @change="setView" /></template>
  </GardenMap>
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
/* the games' tabs sit above the folders on their own row */
.games-tabs-row {
  margin: 6px 0 10px;
}
</style>
