<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { t } from '../../i18n'
import { tone } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'

/**
 * Zongora: an eight-key piano (do–re–mi–fa–szó–la–ti–do, low to high).
 * Three kinds of round, decided by the server (data.mode):
 *   find     the named or heard note: tap its key
 *   compare  two notes are played: which was higher (or lower)?
 *   echo     a short tune is played (keys light up): play it back
 * A wrong key/answer retries; after MAX_ERRORS the round is skipped so the level can drop.
 * data: PianoData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

/** C major, C4–C5. */
const FREQS = [261.63, 293.66, 329.63, 349.23, 392.0, 440.0, 493.88, 523.25]
/** One colour per note, so the keys can be learnt by colour first. */
const COLORS = ['#ff6b6b', '#ff9f43', '#feca57', '#7bd88f', '#48c9b0', '#54a0ff', '#9b7bff', '#ff6b6b']
const NOTE_MS = 520
const GAP_MS = 220
const START_MS = 450
const MAX_ERRORS = 3
const REPLAY_DELAY_MS = 1900

/** 'wait' (prompt) → 'watch' (sounds play) → 'play' (the child's turn) */
const phase = ref('wait')
const lit = ref(null)
const litAnswer = ref(null)
const step = ref(0)
const errors = ref(0)
const { shaking, shake } = useShake()
const { later } = useTimers()
const wait = ms => new Promise(resolve => later(resolve, ms))
/** Bumped to stop sounds that are still playing (replay, unmount). */
let run = 0

function sound(index, ms = NOTE_MS) {
  lit.value = index
  tone(FREQS[index], ms / 1000)
  later(() => {
    if (lit.value === index) lit.value = null
  }, ms)
}

async function playSounds() {
  const mine = ++run
  phase.value = 'watch'
  step.value = 0
  await wait(START_MS)
  const notes = props.data.mode === 'echo' ? props.data.melody : props.data.mode === 'compare' ? props.data.notes : [props.data.target]
  for (const [i, n] of notes.entries()) {
    if (mine !== run) return
    if (props.data.mode === 'compare') litAnswer.value = i
    sound(n)
    await wait(NOTE_MS + GAP_MS)
  }
  litAnswer.value = null
  if (mine === run) phase.value = 'play'
}

// The sounds start once Csillám has finished the prompt (also after a replay).
watch(
  () => props.promptDone,
  done => {
    if (done && !props.locked) {
      // find: the first two levels name the key, so the sound is only a hint the child can ask for
      if (props.data.mode === 'find' && !props.data.hear) phase.value = 'play'
      else playSounds()
    } else if (!done) {
      run++
      phase.value = props.data.mode === 'find' && !props.data.hear ? 'play' : 'wait'
    }
  },
  { immediate: true },
)
onBeforeUnmount(() => run++)

function wrong() {
  errors.value++
  if (errors.value >= MAX_ERRORS) {
    phase.value = 'done'
    emit('skip', t('piano.tooHard'))
    return false
  }
  emit('answer', { correct: false, say: t('piano.wrong') })
  return true
}

function press(index) {
  // free play is fine before the round starts; only answers are ignored
  if (props.locked || phase.value === 'watch' || phase.value === 'done') return
  sound(index, 320)
  if (props.data.mode === 'find') {
    if (index === props.data.target) {
      phase.value = 'done'
      emit('answer', { correct: true, say: props.data.onCorrect })
    } else {
      shake(index)
      wrong()
    }
  } else if (props.data.mode === 'echo' && phase.value === 'play') {
    if (index === props.data.melody[step.value]) {
      step.value++
      if (step.value === props.data.melody.length) {
        phase.value = 'done'
        emit('answer', { correct: true, say: props.data.onCorrect })
      }
      return
    }
    shake(index)
    phase.value = 'wait'
    if (wrong()) later(() => emit('replay', props.data.replayParts), REPLAY_DELAY_MS)
  }
}

function choose(i) {
  if (props.locked || phase.value !== 'play') return
  sound(props.data.notes[i], 420)
  if (String(i) === props.data.answer) {
    phase.value = 'done'
    emit('answer', { correct: true, say: props.data.onCorrect })
  } else {
    shake(`a${i}`)
    wrong()
  }
}

function hearAgain() {
  if (props.locked || phase.value === 'watch') return
  playSounds()
}
</script>

<template>
  <p class="status" aria-live="polite">
    <template v-if="phase === 'watch'">{{ t('piano.watch') }}</template>
    <template v-else-if="data.mode === 'echo' && phase === 'play'">{{ t('piano.yourTurn') }}</template>
    <template v-else>&nbsp;</template>
  </p>

  <div class="keys" data-no-feel :class="{ 'keys--locked': data.mode === 'compare' }">
    <button
      v-for="(k, i) in data.keys"
      :key="i"
      type="button"
      class="key"
      :class="{ 'key--lit': lit === i, 'key--shake': shaking === i }"
      :style="{ '--k': data.colors ? COLORS[i] : 'var(--bz-soft)' }"
      :aria-label="k.label"
      :disabled="data.mode === 'compare'"
      @click="press(i)"
    >
      <span v-if="data.labels || i === 0" class="name">{{ k.label }}</span>
    </button>
  </div>

  <div v-if="data.mode === 'compare'" class="answers" data-no-feel>
    <button
      v-for="(n, i) in data.notes"
      :key="i"
      type="button"
      class="answer"
      :class="{ 'answer--lit': litAnswer === i, 'answer--shake': shaking === `a${i}` }"
      @click="choose(i)"
    >
      🎵 {{ i === 0 ? t('piano.first') : t('piano.second') }}
    </button>
  </div>

  <button v-if="data.mode !== 'compare' && (data.mode === 'echo' || data.hear)" type="button" class="again" @click="hearAgain">
    🔊 {{ t('piano.again') }}
  </button>
  <button v-else-if="data.mode === 'compare'" type="button" class="again" @click="hearAgain">
    🔊 {{ t('piano.again') }}
  </button>
</template>

<style scoped>
.status {
  min-height: 1.3em;
  margin: -6px 0 0;
  font-size: var(--bz-text-md);
  font-weight: 800;
  color: var(--bz-muted);
}
.keys {
  display: flex;
  gap: 4px;
  width: 100%;
  max-width: 520px;
  padding: 10px 10px 12px;
  border-radius: 20px;
  background: #3b1f4a;
  box-shadow: var(--bz-shadow-lg);
}
.key {
  position: relative;
  flex: 1;
  min-width: 0;
  height: clamp(150px, 34vh, 230px);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding-bottom: 10px;
  border-radius: 6px 6px 12px 12px;
  background: linear-gradient(to bottom, #fff 55%, color-mix(in srgb, var(--k) 55%, #fff));
  box-shadow: 0 5px 0 rgba(0, 0, 0, 0.25);
  transition: transform 0.08s ease, background 0.12s ease;
  touch-action: manipulation;
}
.key:active,
.key--lit {
  transform: translateY(4px);
  background: linear-gradient(to bottom, color-mix(in srgb, var(--k) 35%, #fff), var(--k));
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.25);
}
.key:disabled {
  cursor: default;
}
.name {
  font-size: clamp(11px, 3.2vw, 16px);
  font-weight: 900;
  color: var(--bz-ink);
  pointer-events: none;
}
.key--shake,
.answer--shake {
  animation: shake 0.35s ease;
}
.answers {
  display: flex;
  gap: 12px;
  width: 100%;
  max-width: 420px;
}
.answer {
  flex: 1;
  padding: 18px 8px;
  border-radius: 24px;
  background: var(--bz-card);
  font-size: var(--bz-text-md);
  font-weight: 900;
  box-shadow: var(--bz-shadow-lg);
  transition: transform 0.12s ease;
}
.answer--lit {
  transform: scale(1.06);
  background: var(--bz-sun);
}
.again {
  padding: 8px 18px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-weight: 800;
  box-shadow: var(--bz-shadow-sm);
}
@keyframes shake {
  25% {
    transform: translateX(-6px);
  }
  75% {
    transform: translateX(6px);
  }
}
</style>
