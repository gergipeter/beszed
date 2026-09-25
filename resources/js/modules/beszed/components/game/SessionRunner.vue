<script setup>
import { computed, onMounted } from 'vue'
import { useGameSession } from '../../composables/useGameSession'
import { useModuleContext } from '../../composables/useModuleContext'
import { resolveEngine } from '../../engines'
import { t } from '../../i18n'
import { useGuideStore } from '../../stores/guide'
import { useMetaStore } from '../../stores/meta'
import GuideBubble from '../guide/GuideBubble.vue'
import BzButton from '../ui/BzButton.vue'
import BzNotice from '../ui/BzNotice.vue'
import EmojiArt from '../ui/EmojiArt.vue'
import FinishScreen from './FinishScreen.vue'
import GameHud from './GameHud.vue'

/** Plays one game: HUD, Csillám with the caption, and the current round's engine. */
const props = defineProps({
  game: { type: String, required: true },
})

const emit = defineEmits(['exit'])

const { childId, guideName } = useModuleContext()
const guide = useGuideStore()
const meta = useMetaStore()
const { session, round, index, total, locked, promptDone, finished, stars, error, load, speakPrompt, answer, skip } =
  useGameSession(childId, props.game)

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
    />

    <BzNotice v-if="error" tone="warn">
      {{ error }}
      <BzButton size="sm" @click="load">{{ t('common.retry') }}</BzButton>
    </BzNotice>

    <FinishScreen v-else-if="finished" :stars="total" :guide-name="guideName" @again="load" @exit="exit" />

    <template v-else>
      <GuideBubble :name="guideName" :avatar-label="t('game.repeatLabel', { guide: guideName })" @press="replay">
        <b v-if="info" class="game-name"><EmojiArt :char="info.emoji" /> {{ info.name }}</b>
        <p class="caption" aria-live="polite">{{ guide.caption }}</p>
      </GuideBubble>

      <div v-if="round" class="stage">
        <component
          :is="engine"
          v-if="engine"
          :key="round.key"
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
    </template>
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
.stage {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
}
</style>
