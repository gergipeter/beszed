<script setup>
import { computed, onMounted, ref } from 'vue'
import { useGameSession } from '../../composables/useGameSession'
import { useModuleContext } from '../../composables/useModuleContext'
import { useWakeLock } from '../../composables/useWakeLock'
import { ICONS } from '../../config/icons'
import { resolveEngine } from '../../engines'
import { t } from '../../i18n'
import { useGuideStore } from '../../stores/guide'
import { useMetaStore } from '../../stores/meta'
import { useRewardsStore } from '../../stores/rewards'
import { useSettingsStore } from '../../stores/settings'
import GuideBubble from '../guide/GuideBubble.vue'
import BzButton from '../ui/BzButton.vue'
import BzNotice from '../ui/BzNotice.vue'
import EmojiArt from '../ui/EmojiArt.vue'
import CategoryPicker from './CategoryPicker.vue'
import LevelPicker from './LevelPicker.vue'
import FinishScreen from './FinishScreen.vue'
import GameStage from './GameStage.vue'
import GameHud from './GameHud.vue'

/** Plays one game: HUD, Csillám with the caption, and the current round's engine. */
const props = defineProps({
  game: { type: String, required: true },
})

const emit = defineEmits(['exit'])

const { childId, guideName, premium } = useModuleContext()
const guide = useGuideStore()
const meta = useMetaStore()
const rewards = useRewardsStore()
const settings = useSettingsStore()

/**
 * Games with picture themes (Kirakó) start with the theme picker; the choice is
 * remembered on the device and can be changed from the theme chip on the stage.
 */
const themes = computed(() => meta.game(props.game)?.categories ?? [])
const themeKey = `beszed.theme.${props.game}`
const readTheme = () => {
  try {
    return localStorage.getItem(themeKey)
  } catch {
    return null
  }
}
const category = ref(null)
/** A pálya picked by hand (games with a long level range): the next load starts there. */
const pickedLevel = ref(null)
const maxLevel = computed(() => meta.game(props.game)?.maxLevel ?? 0)
/** The free plan stops at its top level; the server holds the line, this only keeps the chooser honest. */
const pickMax = computed(() => {
  const cap = meta.game(props.game)?.freeMaxLevel ?? meta.meta?.freeMaxLevel
  return premium.value || !cap ? maxLevel.value : Math.min(maxLevel.value, cap)
})
function pickLevel(n) {
  pickedLevel.value = n
  if (!picking.value) load()
}
const lastTheme = readTheme()
const picking = ref(themes.value.length > 0)
const themeName = computed(() => themes.value.find(c => c.id === category.value)?.name ?? '')
function pickTheme(id) {
  category.value = id
  try {
    localStorage.setItem(themeKey, id)
  } catch {
    /* not kept on this device */
  }
  picking.value = false
  load()
}

const {
  session,
  round,
  index,
  total,
  locked,
  promptDone,
  finished,
  stars,
  solved,
  result,
  savedLater,
  error,
  load,
  speakPrompt,
  answer,
  skip,
} = useGameSession(childId, props.game, { category, level: pickedLevel })
// the phone must not dim and lock while the child listens to Csillám
useWakeLock()

const info = computed(() => meta.game(props.game))
const engine = computed(() => (round.value ? resolveEngine(round.value.engine) : null))

function replay() {
  guide.hop()
  speakPrompt()
}

function toggleMute() {
  settings.toggleMuted().catch(() => {})
}

/** Turtle mode on/off; the question is said again so the child hears the new pace. */
function toggleSlow() {
  guide.slow = !guide.slow
  speakPrompt()
}

function exit() {
  guide.slow = false
  guide.reset()
  emit('exit')
}

onMounted(() => {
  if (!picking.value) load()
})
</script>

<template>
  <div class="runner">
    <GameHud
      :total="total"
      :done="index"
      :stars="stars"
      :show-progress="Boolean(session) && !finished"
      :muted="settings.muted"
      @exit="exit"
      @toggle-mute="toggleMute"
      :slow="guide.slow"
      @toggle-slow="toggleSlow"
    />

    <!-- Scene changes (playing ↔ finished) and each new round glide in: opacity/transform only. -->
    <Transition name="scene" mode="out-in">
      <BzNotice v-if="error" key="error" tone="warn">
        {{ error }}
        <BzButton size="sm" @click="load">{{ t('common.retry') }}</BzButton>
      </BzNotice>

      <FinishScreen
        v-else-if="finished"
        key="finish"
        :stars="solved"
        :rounds="total"
        :result="result"
        :saved-later="savedLater"
        :level="rewards.level"
        :guide-name="guideName"
        @again="load"
        @exit="exit"
      />

      <div v-else key="play" class="play">
        <GuideBubble :name="guideName" :avatar-label="t('game.repeatLabel', { guide: guideName })" @press="replay">
          <b v-if="info" class="game-name"><EmojiArt :char="info.emoji" /> {{ info.name }}</b>
          <p class="caption" aria-live="polite">{{ guide.caption }}</p>
        </GuideBubble>

        <LevelPicker v-if="maxLevel > 10" :max="pickMax" :current="session?.level ?? null" @pick="pickLevel" />
        <CategoryPicker v-if="picking" :categories="themes" :last="lastTheme" :prompt="meta.game(game)?.pickPrompt" @pick="pickTheme" />
        <template v-else>
          <button v-if="themes.length && themeName" type="button" class="theme-chip" @click="picking = true">
            <EmojiArt :char="ICONS.picture" /> {{ t('game.theme', { name: themeName }) }}
          </button>
        </template>
        <GameStage v-if="round && !picking" class="stage" :stage="info?.stage">
          <Transition name="round" mode="out-in">
            <div :key="round.key" class="round">
              <component
                :is="engine"
                v-if="engine"
                :data="round.data"
                :locked="locked"
                :prompt-done="promptDone"
                @answer="answer"
                @skip="skip"
                @say="text => guide.speak([text])"
                @replay="parts => speakPrompt({ parts })"
              />
              <BzNotice v-else tone="warn">
                {{ t('game.unsupported') }}
                <BzButton size="sm" @click="skip()">{{ t('common.next') }}</BzButton>
              </BzNotice>
            </div>
          </Transition>
        </GameStage>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* "Téma: Állatok": back to the theme picker */
.theme-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: -6px auto 10px;
  padding: 5px 14px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-weight: 800;
  font-size: var(--bz-text-sm);
  box-shadow: var(--bz-shadow-sm);
}
/* room the stage leaves for the rest (HUD, Csillám): engines size big boards with it (Kirakó) */
.runner {
  --bz-stage-room: 330px;
}
/*
 * a phone held sideways: Csillám and her words on the left, the game on the right,
 * so the round fits the short screen without scrolling
 */
@media (orientation: landscape) and (max-height: 600px) {
  .runner {
    --bz-stage-room: 120px;
  }
  .play {
    display: grid;
    grid-template-columns: minmax(170px, 28%) 1fr;
    align-items: start;
    gap: 14px;
  }
  .play :deep(.guide) {
    flex-direction: column;
    align-items: stretch;
    margin: 0;
  }
  .play :deep(.guide .avatar) {
    align-self: center;
    width: clamp(90px, 16vh, 130px);
  }
  .play :deep(.guide .bubble::before) {
    display: none;
  }
  .play :deep(.scene) {
    min-height: calc(100dvh - 110px);
  }
}
.game-name {
  display: block;
  font-size: 17px;
  color: var(--bz-muted);
}
.caption {
  min-height: 1.3em;
  margin: 0;
  font-size: var(--bz-text-md);
  line-height: 1.3;
}
.round {
  display: flex;
  flex-direction: column;
  flex: 1;
  align-items: center;
  gap: 18px;
}
.round-enter-active {
  transition: opacity 0.25s ease, transform 0.32s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.round-leave-active {
  transition: opacity 0.14s ease, transform 0.14s ease-in;
}
.round-enter-from {
  opacity: 0;
  transform: translate3d(28px, 0, 0);
}
.round-leave-to {
  opacity: 0;
  transform: translate3d(-28px, 0, 0);
}
.scene-enter-active {
  transition: opacity 0.3s ease, transform 0.35s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.scene-leave-active {
  transition: opacity 0.15s ease;
}
.scene-enter-from {
  opacity: 0;
  transform: scale(0.97);
}
.scene-leave-to {
  opacity: 0;
}
</style>
