<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useRecorder } from '../../composables/useRecorder'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { useGuideStore } from '../../stores/guide'
import { engineEmits, engineProps } from '../contract'

/**
 * Hanggyakorló: the child says the picture's name (or a sentence) after Csillám — or, naming, without her —
 * may say it into the microphone and at once hears their own voice back (optionally as a chipmunk), then
 * judges it with a parent: "Jól mondtam!" wins the round, "Még gyakorlom" has Csillám say it slowly again.
 * The recording lives only in this page's memory (an object URL, revoked when done): never uploaded, never kept.
 * Without a microphone the round is the same, minus the recording.
 * data: SayData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

/** A recording stops by itself after this long. */
const MAX_MS = 4000
/** The chipmunk voice: faster, and higher because the pitch is not kept. */
const FUNNY_RATE = 1.35

const guide = useGuideStore()
const { later } = useTimers()
const { supported, recordingKey, start, stop } = useRecorder()

/** "Még gyakorlom" presses this round. */
const practised = ref(0)
/** Naming: the word is shown once Csillám has said it (help, practice, or the end). */
const revealed = ref(props.data.mode !== 'name')
const micFailed = ref(false)
const micShown = computed(() => supported && !micFailed.value)
const recording = computed(() => recordingKey.value === 'say')
const hasRecording = ref(false)
const playing = ref(false)
const funny = ref(funnyPreference.value)
watch(funny, on => (funnyPreference.value = on))

let url = null
let player = null
let stopTimer = null
let gone = false

function revoke() {
  if (url) URL.revokeObjectURL(url)
  url = null
}

function stopPlayback() {
  if (player) {
    player.onended = player.onerror = null
    player.pause()
  }
  player = null
  playing.value = false
}

function playBack() {
  if (!url || gone) return
  stopPlayback()
  const audio = new Audio(url)
  const rate = funny.value ? FUNNY_RATE : 1
  audio.defaultPlaybackRate = rate
  audio.playbackRate = rate
  // a higher voice, not only a faster one
  audio.preservesPitch = audio.mozPreservesPitch = audio.webkitPreservesPitch = !funny.value
  audio.onended = audio.onerror = () => {
    if (player === audio) playing.value = false
  }
  player = audio
  playing.value = true
  audio.play().catch(() => {
    if (player === audio) playing.value = false
  })
}

function recorded(blob) {
  clearTimeout(stopTimer)
  // the round is over (or the page left) before the recording came back: nothing to play
  if (gone || props.locked) return
  revoke()
  url = URL.createObjectURL(blob)
  hasRecording.value = true
  playBack()
}

async function toggleRecording() {
  if (props.locked) return
  if (recording.value) return stop()
  stopPlayback()
  // Csillám would be on the recording too
  guide.stop()
  const ok = await start('say', recorded)
  if (!ok) {
    micFailed.value = true
    return
  }
  if (gone) return stop()
  clearTimeout(stopTimer)
  stopTimer = later(stop, MAX_MS)
}

function again() {
  if (props.data.model) emit('say', props.data.model)
  else emit('replay')
}

function slowly() {
  revealed.value = true
  emit('replay', props.data.slow)
}

function good() {
  if (props.locked || recording.value) return
  stopPlayback()
  revealed.value = true
  emit('answer', { correct: true, say: props.data.onCorrect, tries: Math.min(3, practised.value + 1) })
}

function practise() {
  if (props.locked || recording.value) return
  stopPlayback()
  practised.value++
  revealed.value = true
  emit('replay', props.data.retry)
}

function skip() {
  if (props.locked) return
  stopPlayback()
  emit('skip', props.data.onSkip)
}

watch(
  () => props.locked,
  locked => {
    if (locked) stop()
  },
)

onBeforeUnmount(() => {
  gone = true
  clearTimeout(stopTimer)
  stop()
  stopPlayback()
  revoke()
})
</script>

<script>
import { ref as moduleRef } from 'vue'

/** The chipmunk switch stays as the child left it, from round to round. */
const funnyPreference = moduleRef(false)
</script>

<template>
  <div class="say">
    <div class="card" :class="{ 'card--phrase': data.word.includes(' ') }">
      <span class="badge" :aria-label="t('say.sound', { sound: data.sound, where: data.where })">
        <b>{{ data.sound }}</b> · {{ data.where }}
      </span>
      <button type="button" class="art" :aria-label="revealed ? t('say.picture', { word: data.word }) : t('say.hidden')" @click="again">
        <EmojiArt :char="data.emoji" />
      </button>
      <p class="word" :class="{ 'word--hidden': !revealed }" aria-live="polite">{{ revealed ? data.word : '?' }}</p>
    </div>

    <div class="bz-row">
      <BzButton size="sm" :icon="ICONS.speaker" @click="again">{{ t('say.again') }}</BzButton>
      <BzButton size="sm" :icon="ICONS.turtle" @click="slowly">{{ t('say.slowly') }}</BzButton>
    </div>

    <template v-if="micShown">
      <BzButton
        class="rec"
        variant="primary"
        :icon="recording ? ICONS.stop : ICONS.mic"
        :pulse="recording"
        :disabled="locked"
        @click="toggleRecording"
      >
        {{ recording ? t('say.recording') : t('say.record') }}
      </BzButton>
      <div v-if="recording" class="meter" aria-hidden="true"><span class="meter-fill" :style="{ animationDuration: `${MAX_MS}ms` }" /></div>
      <div v-else-if="hasRecording" class="bz-row">
        <BzButton size="sm" :icon="ICONS.play" :disabled="locked" @click="playBack">{{ t('say.playAgain') }}</BzButton>
        <button type="button" class="funny" :class="{ 'funny--on': funny }" :aria-pressed="funny" @click="funny = !funny">
          <EmojiArt char="🐿️" /> {{ t('say.funny') }}
        </button>
      </div>
      <p class="note" :class="{ 'note--listen': playing }">{{ playing ? t('say.listen') : t('say.privacy') }}</p>
    </template>
    <p v-else-if="micFailed" class="note">{{ t('say.noMic') }}</p>

    <div class="judge" :aria-label="t('say.judgeHint')" role="group">
      <BzButton variant="primary" :icon="ICONS.thumbsUp" :disabled="locked || recording" @click="good">{{ t('say.good') }}</BzButton>
      <BzButton :icon="ICONS.again" :disabled="locked || recording" @click="practise">{{ t('say.practise') }}</BzButton>
      <BzButton v-if="practised >= data.skipAfter" class="judge-skip" size="sm" :disabled="locked" @click="skip">{{ t('common.next') }}</BzButton>
    </div>
  </div>
</template>

<style scoped>
.say {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  width: 100%;
}
.card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 8px 22px 6px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
  max-width: 100%;
}
.badge {
  padding: 2px 12px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  font-size: var(--bz-text-sm);
  font-weight: 700;
}
.badge b {
  font-size: 19px;
}
.art {
  min-width: 56px;
  min-height: 56px;
  padding: 4px;
  border-radius: var(--bz-radius);
  background: none;
  font-size: clamp(64px, 18vw, 92px);
  line-height: 1.1;
  transition: transform 0.38s var(--bz-spring);
}
.card--phrase .art {
  font-size: clamp(48px, 13vw, 70px);
}
.art:active {
  transform: scale(0.94);
  transition-duration: 0.07s;
}
.word {
  margin: 0;
  font-size: var(--bz-text-lg);
  font-weight: 800;
  line-height: 1.2;
  text-align: center;
  overflow-wrap: anywhere;
}
.card--phrase .word {
  font-size: var(--bz-text-md);
}
.word--hidden {
  color: var(--bz-muted);
}
.rec {
  min-height: 56px;
}
/* the two judging buttons side by side, even on a 320 px phone */
.judge {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  width: min(100%, 460px);
}
.judge :deep(.btn) {
  min-height: 56px;
  padding: 8px 10px;
  font-size: 18px;
}
.judge-skip {
  grid-column: 1 / -1;
  justify-self: center;
}
.funny {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 44px;
  padding: 6px 12px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  color: var(--bz-ink);
  font-size: var(--bz-text-sm);
  font-weight: 700;
  box-shadow: var(--bz-shadow-sm);
}
.funny--on {
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  opacity: 1;
}
.meter {
  width: min(260px, 80%);
  height: 10px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
  overflow: hidden;
}
.meter-fill {
  display: block;
  height: 100%;
  background: var(--bz-coral);
  transform-origin: left;
  animation: fill linear forwards;
}
@keyframes fill {
  from {
    transform: scaleX(0);
  }
  to {
    transform: scaleX(1);
  }
}
/* on its own card: readable on the light stage in the night palette too */
.note {
  margin: 0;
  padding: 3px 14px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  color: var(--bz-muted);
  font-size: var(--bz-text-sm);
  text-align: center;
}
.note--listen {
  color: var(--bz-ink);
  font-weight: 800;
}
@media (prefers-reduced-motion: reduce) {
  .meter-fill {
    animation: none;
    transform: scaleX(1);
    opacity: 0.6;
  }
  .art {
    transition: none;
  }
}
</style>
