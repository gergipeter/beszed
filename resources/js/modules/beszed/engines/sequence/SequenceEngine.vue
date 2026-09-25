<script setup>
import { reactive, ref } from 'vue'
import OptionGrid from '../../components/ui/OptionGrid.vue'
import OptionTile from '../../components/ui/OptionTile.vue'
import PictureCard from '../../components/ui/PictureCard.vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { beep } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'

/**
 * Tap the heard words' pictures in order (Papagáj). A wrong tap replays the
 * words; after MAX_ERRORS the round is skipped so the level can drop.
 * data: SequenceData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const MAX_ERRORS = 3
const REPLAY_DELAY_MS = 1900

const step = ref(0)
const errors = ref(0)
/** option id → its position in the tapped order */
const marks = reactive({})
const { shaking, shake } = useShake()
const { later } = useTimers()

function restart() {
  step.value = 0
  for (const id of Object.keys(marks)) delete marks[id]
  emit('replay', props.data.replayParts)
}

function tap(id) {
  if (props.locked || marks[id] || !props.promptDone) return

  if (id === props.data.order[step.value]) {
    marks[id] = ++step.value
    beep(420, 0.08)
    if (step.value === props.data.order.length) emit('answer', { correct: true, say: props.data.onCorrect })
    return
  }

  errors.value++
  shake(id)
  if (errors.value >= MAX_ERRORS) {
    emit('skip', t('sequence.tooHard'))
    return
  }
  emit('answer', { correct: false, say: t('sequence.wrong') })
  later(restart, REPLAY_DELAY_MS)
}
</script>

<template>
  <PictureCard :emoji="ICONS.parrot" :label="t('sequence.words', { count: data.order.length })" />
  <OptionGrid v-show="promptDone" :columns="4">
    <OptionTile
      v-for="o in data.grid"
      :key="o.id"
      :emoji="o.emoji"
      :label="o.label"
      :good="Boolean(marks[o.id])"
      :shake="shaking === o.id"
      :badge="marks[o.id]"
      @click="tap(o.id)"
    />
  </OptionGrid>
</template>
