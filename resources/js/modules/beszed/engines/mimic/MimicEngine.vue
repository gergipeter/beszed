<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import PictureCard from '../../components/ui/PictureCard.vue'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { beep, sparkle } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'
import { loadFaceTracker } from './faceTracker'
import { createBaseline, createRepCounter, toScores } from './poses'

/**
 * Mouth exercises in front of a mirror (Szájtorna). The picture swaps between the
 * exercise's moves by itself, slowly, as the thing to copy; the child (with a
 * parent) taps once after each repetition. Nobody is graded: finishing is the win.
 *
 * With the mirror on, the face tracker (MediaPipe, on the device: faceTracker.js) watches the moves
 * the server named a `pose` for and counts each repetition itself (poses.js); the picture then
 * follows the child's face. The camera picture is only shown and measured here, never recorded or
 * sent. The parent's button always counts too (a move the tracker can't see, a dark room).
 * data: MimicData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const SWAP_MS = 1800
/** The tracker runs at most this often (ms): plenty for a mouth, light on a phone. */
const TRACK_MS = 66
/** No face this long: ask the child to look into the camera. */
const NO_FACE_MS = 1200

const step = ref(0)
const done = ref(0)
const move = computed(() => props.data.moves[step.value % props.data.moves.length])
/** After the child does a move, its picture stays a moment before the swapping goes on. */
let holdPictureUntil = 0

let timer = null
onMounted(() => {
  timer = setInterval(() => {
    if (!props.locked && performance.now() >= holdPictureUntil) step.value++
  }, SWAP_MS)
})
onBeforeUnmount(() => {
  clearInterval(timer)
  stopMirror()
})

/** The front camera as a mirror: shown on this screen only, never recorded or sent anywhere. */
const mirror = ref(false)
const mirrorError = ref(false)
const video = ref(null)
let stream = null
const cameraSupported = typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices?.getUserMedia)

// ---- automatic counting ----
const watchable = props.data.moves.some(m => m.pose)
/** off · loading · calibrating · watching · noface · failed */
const tracking = ref('off')
const strength = ref(0)
let tracker = null
let counter = null
let baseline = null
let raf = 0
let lastRun = 0
let lastFace = 0

async function toggleMirror() {
  if (mirror.value) return stopMirror()
  mirrorError.value = false
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
    mirror.value = true
    await nextTick()
    if (video.value) video.value.srcObject = stream
    if (watchable) startTracking()
  } catch {
    stream = null
    mirrorError.value = true
  }
}

async function startTracking() {
  tracking.value = 'loading'
  try {
    tracker = await loadFaceTracker()
  } catch {
    tracking.value = mirror.value ? 'failed' : 'off'
    return
  }
  if (!mirror.value) return
  const poses = [...new Set(props.data.moves.map(m => m.pose).filter(Boolean))]
  baseline = createBaseline(poses)
  counter = null
  tracking.value = 'calibrating'
  lastRun = lastFace = performance.now()
  raf = requestAnimationFrame(track)
}

function track(now) {
  raf = requestAnimationFrame(track)
  if (now - lastRun < TRACK_MS || props.locked) return
  const dt = Math.min(200, now - lastRun)
  lastRun = now
  let scores = null
  try {
    scores = toScores(tracker.detect(video.value, now))
  } catch {
    // a lost GPU context or a frame it can't read: the mirror stays, the parent counts
    cancelAnimationFrame(raf)
    tracking.value = 'failed'
    return
  }
  if (!scores) {
    strength.value = 0
    if (now - lastFace > NO_FACE_MS) tracking.value = 'noface'
    return
  }
  lastFace = now
  if (!counter) {
    tracking.value = 'calibrating'
    if (!baseline.update(scores, dt)) return
    counter = createRepCounter(props.data.moves, { base: baseline.values })
  }
  tracking.value = 'watching'
  const r = counter.update(scores, dt)
  strength.value = r.strength
  // the picture shows the move the child is doing now
  if (r.active !== null) {
    if (step.value % props.data.moves.length !== r.active) step.value = r.active
    holdPictureUntil = now + SWAP_MS
  }
  if (r.rep) rep(true)
}

function stopMirror() {
  cancelAnimationFrame(raf)
  raf = 0
  stream?.getTracks().forEach(track => track.stop())
  stream = null
  mirror.value = false
  tracking.value = 'off'
  strength.value = 0
}

function rep(auto = false) {
  if (props.locked || done.value >= props.data.reps) return
  done.value++
  if (auto) sparkle()
  else beep(520, 0.1)
  if (done.value >= props.data.reps) {
    stopMirror()
    emit('answer', { correct: true, say: props.data.onCorrect, tries: 1 })
  }
}

const trackNote = computed(
  () =>
    ({
      loading: t('mimic.trackLoading'),
      calibrating: t('mimic.trackCalibrating'),
      watching: t('mimic.trackWatching'),
      noface: t('mimic.trackNoFace'),
      failed: t('mimic.trackFailed'),
    })[tracking.value] ?? '',
)
</script>

<template>
  <p class="mirror">{{ t('mimic.mirror') }}</p>
  <PictureCard :key="step" :emoji="move.emoji" :label="move.label" pressable @click="emit('say', move.label)" />
  <div v-if="mirror" class="mirror-wrap" :class="{ 'mirror-wrap--on': tracking === 'watching' && strength >= 1 }">
    <video ref="video" class="mirror-cam" autoplay playsinline muted :aria-label="t('mimic.mirrorLabel')" />
    <div v-if="tracking === 'watching'" class="strength" role="img" :aria-label="t('mimic.trackStrength', { percent: Math.round(strength * 100) })">
      <span class="strength-fill" :style="{ height: `${Math.round(strength * 100)}%` }" />
    </div>
  </div>
  <p v-if="mirror && trackNote" class="mirror track-note" aria-live="polite">{{ trackNote }}</p>
  <p v-if="mirrorError" class="mirror">{{ t('mimic.noCamera') }}</p>
  <div class="dots" role="img" :aria-label="t('mimic.progress', { done, total: data.reps })">
    <span v-for="n in data.reps" :key="n" class="dot" :class="{ 'dot--on': n <= done }" />
  </div>
  <div class="bz-row">
    <BzButton :icon="ICONS.speaker" @click="emit('replay')">{{ t('mimic.again') }}</BzButton>
    <BzButton v-if="cameraSupported" :icon="ICONS.mirror" @click="toggleMirror">
      {{ mirror ? t('mimic.mirrorOff') : t('mimic.mirrorOn') }}
    </BzButton>
    <BzButton variant="primary" :icon="ICONS.done" :disabled="locked" @click="rep()">{{ t('mimic.rep') }}</BzButton>
  </div>
  <p v-if="cameraSupported && watchable && !mirror" class="mirror">{{ t('mimic.trackOffer') }}</p>
</template>

<style scoped>
.mirror {
  margin: 0;
  font-size: var(--bz-text-sm);
  color: var(--bz-muted);
  text-align: center;
}
.track-note {
  font-weight: 700;
}
.mirror-wrap {
  position: relative;
  width: min(100%, 260px);
  border-radius: var(--bz-radius-lg);
  box-shadow: 0 0 0 0 transparent;
  transition: box-shadow 0.2s;
}
.mirror-wrap--on {
  box-shadow: 0 0 0 5px var(--bz-sun);
}
.mirror-cam {
  display: block;
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: var(--bz-radius-lg);
  background: #000;
  transform: scaleX(-1); /* a mirror: left is left */
}
.strength {
  position: absolute;
  right: 8px;
  top: 12px;
  bottom: 12px;
  width: 12px;
  border-radius: var(--bz-radius-pill);
  background: rgb(255 255 255 / 0.45);
  overflow: hidden;
  display: flex;
  align-items: flex-end;
}
.strength-fill {
  width: 100%;
  border-radius: inherit;
  background: var(--bz-sun);
  transition: height 0.1s linear;
}
.dots {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
}
.dot {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--bz-sun);
  opacity: 0.35;
  transition: transform 0.15s, opacity 0.15s;
}
.dot--on {
  opacity: 1;
  transform: scale(1.15);
}
</style>
