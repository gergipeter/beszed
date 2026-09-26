<script setup>
import { ref } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import OptionGrid from '../../components/ui/OptionGrid.vue'
import OptionTile from '../../components/ui/OptionTile.vue'
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

function choose(id) {
  if (props.locked) return
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
  <PictureCard
    v-if="data.stimulus"
    :emoji="data.stimulus.emoji"
    :label="data.stimulus.label"
    :highlight="Boolean(data.stimulus.highlight)"
    :silhouette="Boolean(data.stimulus.silhouette)"
    :pressable="Boolean(data.stimulus.say)"
    @click="sayStimulus"
  />

  <div v-if="data.sequence" class="sequence" role="img" :aria-label="t('choice.sequence')">
    <EmojiArt v-for="(char, i) in data.sequence" :key="i" :char="char" />
    <span class="missing">?</span>
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
        :variant="data.variant === 'plates' ? 'plate' : o.scene ? 'scene' : 'default'"
        :good="good === o.id"
        :shake="shaking === o.id"
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
