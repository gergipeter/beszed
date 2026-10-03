<script setup>
import { computed, ref, watch } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import OptionGrid from '../../components/ui/OptionGrid.vue'
import OptionTile from '../../components/ui/OptionTile.vue'
import ClockFace from '../../components/ui/ClockFace.vue'
import PictureCard from '../../components/ui/PictureCard.vue'
import { useShake } from '../../composables/useShake'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { engineEmits, engineProps } from '../contract'
import SceneView from './SceneView.vue'

/**
 * Pick the right answer. Variants: plain options, "plates" (compare amounts),
 * "speakers" (whose sentence is right), scenes (Hol van?) and a pattern row.
 * data: ChoiceData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const COLUMNS = { two: 2, three: 3, four: 4 }
/** Stand-in for a scene whose object has no emoji. */
const SCENE_FALLBACK = '🧸'

const good = ref(null)
const { shaking, shake } = useShake()

/**
 * Csillám has a go first (data.guess, from App\Beszed\CsillamGuess): once the
 * prompt is said she points at an answer and asks if she's right. The child
 * says yes or no; catching her mistake, they then pick the right one. A wrong
 * judgement counts as a try, like a wrong answer.
 */
const guess = computed(() => props.data.guess ?? null)
const judging = ref(Boolean(props.data.guess))
/** Her guess was wrong and the child caught it: it stays crossed out. */
const caught = ref(false)
watch(
  () => props.promptDone,
  done => {
    if (done && judging.value && guess.value) emit('say', guess.value.ask)
  },
  { immediate: true },
)

/** @param {boolean} [quiet]  caught by tapping the right answer straight away: no "Melyik a jó?" */
function judge(agree, quiet = false) {
  if (props.locked || !judging.value) return
  const right = guess.value.id === props.data.answer
  if (agree && right) {
    good.value = guess.value.id
    judging.value = false
    emit('answer', { correct: true, say: guess.value.confirmed })
  } else if (!agree && !right) {
    judging.value = false
    caught.value = true
    if (!quiet) emit('say', guess.value.caught)
  } else {
    shake(guess.value.id)
    emit('answer', { correct: false, say: agree ? guess.value.agreedWrong : guess.value.deniedRight })
  }
}

function choose(id) {
  if (props.locked) return
  // judging by tapping a picture: her pick = "you're right", another = "no, it's this one"
  if (judging.value) {
    if (id === guess.value.id) return judge(true)
    judge(false, true)
    if (judging.value) return // she was right after all
  }
  if (id === props.data.answer) {
    good.value = id
    emit('answer', { correct: true, say: props.data.onCorrect })
    return
  }
  shake(id)
  const wrong = props.data.onWrong
  emit('answer', { correct: false, say: typeof wrong === 'string' ? wrong : wrong?.[id] })
}

function sayStimulus() {
  if (props.data.stimulus?.say) emit('say', props.data.stimulus.say)
}
</script>

<template>
  <ClockFace v-if="data.stimulus?.clock" :hour="data.stimulus.clock.hour" :minute="data.stimulus.clock.minute" />
  <PictureCard
    v-else-if="data.stimulus"
    :emoji="data.stimulus.emoji"
    :letter="data.stimulus.letter"
    :label="data.stimulus.label"
    :highlight="Boolean(data.stimulus.highlight)"
    :silhouette="Boolean(data.stimulus.silhouette)"
    :pressable="Boolean(data.stimulus.say)"
    @click="sayStimulus"
  />

  <div v-if="data.sequence" class="sequence" role="img" :aria-label="t('choice.sequence')">
    <EmojiArt v-for="(char, i) in data.sequence" :key="i" :char="char" />
    <span v-if="!data.noMissing" class="missing">?</span>
  </div>

  <OptionGrid :columns="COLUMNS[data.layout] ?? 3">
    <template v-if="data.variant === 'speakers'">
      <div v-for="o in data.options" :key="o.id" class="speaker">
        <OptionTile
          class="speaker-line"
          :emoji="o.emoji"
          :label="o.label"
          :good="good === o.id"
          :shake="shaking === o.id"
          @click="o.say && emit('say', o.say)"
        />
        <BzButton
          class="speaker-pick"
          variant="primary"
          :icon="ICONS.thumbsUp"
          :aria-label="t('choice.pickSpeaker', { label: o.label })"
          @click="choose(o.id)"
        />
      </div>
    </template>

    <template v-else>
      <OptionTile
        v-for="o in data.options"
        :key="o.id"
        :emoji="o.emoji"
        :label="o.label"
        :letter="o.letter"
        :variant="data.variant === 'plates' ? 'plate' : o.scene ? 'scene' : 'default'"
        :good="good === o.id"
        :shake="shaking === o.id"
        :badge="guess?.id === o.id && (judging || caught) ? ICONS.guide : null"
        :class="{
          'option--guess': judging && guess?.id === o.id,
          'option--waiting': judging && guess?.id !== o.id,
          'option--crossed': caught && guess?.id === o.id,
        }"
        :aria-label="o.label ?? t('choice.answer')"
        @click="choose(o.id)"
      >
        <SceneView v-if="o.scene" :relation="o.scene" :emoji="o.emoji ?? SCENE_FALLBACK" />
        <template v-else-if="o.emojis">
          <EmojiArt v-for="(char, i) in o.emojis" :key="i" :char="char" />
        </template>
      </OptionTile>
    </template>
  </OptionGrid>

  <!-- Csillám asks "Igazam van?": the child is the judge -->
  <div v-if="judging && promptDone" class="judge">
    <BzButton variant="primary" class="judge-btn" :icon="ICONS.thumbsUp" @click="judge(true)">{{ t('choice.agree') }}</BzButton>
    <BzButton class="judge-btn" :icon="ICONS.thumbsDown" @click="judge(false)">{{ t('choice.disagree') }}</BzButton>
  </div>
</template>

<style scoped>
.sequence {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px;
  padding: 12px 16px;
  border-radius: 24px;
  background: var(--bz-card);
  font-size: clamp(34px, 8vw, 52px);
}
.missing {
  display: inline-grid;
  place-items: center;
  width: 1.2em;
  height: 1.2em;
  border: 4px dashed var(--bz-coral);
  border-radius: var(--bz-radius-sm);
  color: var(--bz-coral);
  font-size: 0.7em;
  font-weight: 800;
}
/* Csillám's guess: her pick glows, the others wait for the verdict */
.option--guess {
  box-shadow:
    0 0 0 5px var(--bz-sun),
    var(--bz-shadow-lg);
  animation: pointed 1.2s ease-in-out infinite alternate;
}
.option--waiting {
  opacity: 0.7;
}
/* caught: her wrong pick crossed out while the child finds the right one */
.option--crossed {
  opacity: 0.4;
  filter: grayscale(0.8);
}
.judge {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 14px;
  animation: judge-in 0.4s var(--bz-spring) backwards;
}
.judge-btn {
  padding: 12px 26px;
  font-size: 24px;
}
@keyframes pointed {
  to {
    transform: translateY(-6px) scale(1.03);
  }
}
@keyframes judge-in {
  from {
    opacity: 0;
    transform: translateY(16px) scale(0.9);
  }
}
.speaker {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}
.speaker .speaker-line {
  width: 100%;
}
.speaker .speaker-line :deep(.label) {
  font-size: 14px;
}
.speaker .speaker-pick {
  padding: 6px 30px;
  font-size: 30px;
}
</style>
