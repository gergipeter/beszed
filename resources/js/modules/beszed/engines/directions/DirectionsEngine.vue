<script setup>
import { computed, reactive, ref } from 'vue'
import OptionGrid from '../../components/ui/OptionGrid.vue'
import OptionTile from '../../components/ui/OptionTile.vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { t } from '../../i18n'
import { beep } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'

/**
 * Following directions (Csináld, amit mondok!): tap what Csillám said, in
 * order. `steps` are sets: a step with several pictures ("az összes állatra")
 * takes them in any order. Taps count only once the direction has been said.
 * A wrong tap says the direction again from the start; after MAX_ERRORS the
 * round is skipped so the level can drop.
 * data: DirectionsData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const MAX_ERRORS = 3
const REPLAY_DELAY_MS = 1900

const step = ref(0)
const errors = ref(0)
/** picture id → its badge: the step number, or ✓ inside a step of several */
const marks = reactive({})
const { shaking, shake } = useShake()
const { later } = useTimers()

/** after a wrong tap, until the direction starts again */
const waiting = ref(false)
const listening = computed(() => !props.promptDone || waiting.value)
const current = computed(() => props.data.steps[step.value] ?? [])

function restart() {
  waiting.value = false
  step.value = 0
  for (const id of Object.keys(marks)) delete marks[id]
  emit('replay')
}

function tap(id) {
  if (props.locked || listening.value || marks[id]) return

  if (current.value.includes(id)) {
    marks[id] = current.value.length > 1 ? '✓' : step.value + 1
    beep(420 + step.value * 80, 0.08)
    if (current.value.every(x => marks[x])) step.value++
    if (step.value === props.data.steps.length) emit('answer', { correct: true, say: props.data.onCorrect })
    return
  }

  errors.value++
  shake(id)
  if (errors.value >= MAX_ERRORS) {
    emit('skip', t('directions.tooHard'))
    return
  }
  waiting.value = true
  emit('answer', { correct: false, say: props.data.wrong })
  later(restart, REPLAY_DELAY_MS)
}
</script>

<template>
  <OptionGrid class="grid" :class="{ 'grid--listening': listening }" :columns="data.grid.length <= 4 ? 4 : 3">
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
  <p class="hint" aria-live="polite">{{ listening ? t('directions.listen') : ' ' }}</p>
</template>

<style scoped>
.grid {
  transition: opacity 0.25s;
}
/* while Csillám is still talking: look, but wait with the taps */
.grid--listening {
  opacity: 0.55;
}
.hint {
  min-height: 1.3em;
  margin: 0;
  font-size: var(--bz-text-md);
  font-weight: 800;
  color: var(--bz-muted);
}
</style>
