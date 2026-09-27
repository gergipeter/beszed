<script setup>
import { computed, onMounted } from 'vue'
import { useGameSession } from '../../composables/useGameSession'
import { useModuleContext } from '../../composables/useModuleContext'
import { resolveEngine } from '../../engines'
import { t } from '../../i18n'
import { useGuideStore } from '../../stores/guide'
import { useMetaStore } from '../../stores/meta'
import { useRewardsStore } from '../../stores/rewards'
import GuideBubble from '../guide/GuideBubble.vue'
import BzButton from '../ui/BzButton.vue'
import BzNotice from '../ui/BzNotice.vue'
import EmojiArt from '../ui/EmojiArt.vue'
import FinishScreen from './FinishScreen.vue'
import GameStage from './GameStage.vue'
import GameHud from './GameHud.vue'

/** Plays one game: HUD, Csillám with the caption, and the current round's engine. */
const props = defineProps({
  game: { type: String, required: true },
})

const emit = defineEmits(['exit'])

const { childId, guideName } = useModuleContext()
const guide = useGuideStore()
const meta = useMetaStore()
const rewards = useRewardsStore()
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
} = useGameSession(childId, props.game)

const info = computed(() => meta.game(props.game))
const engine = computed(() => (round.value ? resolveEngine(round.value.engine) : null))

function replay() {
  guide.hop()
  speakPrompt()
}

function exit() {
  guide.reset()
  emit('exit')
}

onMounted(load)
</script>

<template>
  <div class="runner">
    <GameHud
      :total="total"
      :done="index"
      :stars="stars"
      :show-progress="Boolean(session) && !finished"
      @exit="exit"
      @replay="speakPrompt()"
      @replay-slow="speakPrompt({ slow: true })"
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

      <div v-else key="play">
        <GuideBubble :name="guideName" :avatar-label="t('game.repeatLabel', { guide: guideName })" @press="replay">
          <b v-if="info" class="game-name"><EmojiArt :char="info.emoji" /> {{ info.name }}</b>
          <p class="caption" aria-live="polite">{{ guide.caption }}</p>
        </GuideBubble>

        <GameStage v-if="round" class="stage" :stage="info?.stage">
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
