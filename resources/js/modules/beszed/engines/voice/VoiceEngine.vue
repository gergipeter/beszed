<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useMotionPref } from '../../composables/useMotionPref'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { pluck, sparkle } from '../../services/audio/sfx'
import { useGuideStore } from '../../stores/guide'
import { engineEmits, engineProps } from '../contract'
import BlowScene from './BlowScene.vue'
import FlyScene from './FlyScene.vue'
import VoiceMeter from './VoiceMeter.vue'
import {
  LEVELS,
  createGate,
  createGentleTimer,
  createHoldTimer,
  createNoiseFloor,
  createPitchTracker,
  createPuffCounter,
  createStepper,
  estimatePitch,
  heightFromLoudness,
  isVoiced,
  meterLevel,
  rms,
  toDb,
} from './signal'
import { useMic } from './useMic'

/**
 * Breath and voice games: the microphone drives a picture (Fújóka: blow; Hangrepülő: hold a sound,
 * high and low). Listening starts after Csillám's prompt and pauses whenever she speaks, so her voice
 * never plays the game. Nothing is recorded or sent: each frame is measured and forgotten, and the mic
 * is switched off as soon as the round is decided or left. Without a microphone a parent's button (or,
 * for the voice, pressing and holding the sky) lets the round be finished anyway.
 * The signal logic is in signal.js (tested in tests/js/voice.test.mjs).
 * data: VoiceData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const guide = useGuideStore()
const mic = useMic()
const { later } = useTimers()
const { reduceMotion } = useMotionPref()
const reducedMotion = () => reduceMotion.value || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

const STEP_ICONS = { soft: '🪶', strong: '💨' }
/** No progress for this long: the parent's button shows, and Csillám encourages once. */
const STUCK_MS = 8000
const HINT_GAP_MS = 7000
/** pitch: the flyer crosses the sky in about seven seconds of voice */
const SPEED = 0.00012
const IN_ZONE_MS = 200

const d = computed(() => props.data)
const blow = computed(() => d.value.mode === 'blow')
const kind = computed(() => (blow.value ? d.value.kind : d.value.mode))

// ---- what the screen shows ----
const phase = ref('wait') // wait · listen · done
const calibrating = ref(false)
const pausedForCsillam = ref(false)
const level = ref(0)
const strength = ref(0)
const progress = ref(0)
const puffs = ref(0)
const stepIndex = ref(0)
const angle = ref(0)
const oops = ref(0)
const falling = ref(false)
const x = ref(0.08)
const y = ref(0.5)
const stars = reactive((d.value.stars ?? []).map(s => ({ ...s, got: false })))
const waitingFor = ref(null) // pitch: the star the flyer waits at ('high' | 'low')
const stuck = ref(false)
const holding = ref(false)
const holdHeight = ref(0.5)

const noMic = computed(() => mic.status.value === 'denied' || mic.status.value === 'unsupported')
const showParent = computed(() => phase.value === 'listen' && (noMic.value || stuck.value))
const count = computed(() => (kind.value === 'alternate' ? d.value.steps.length : d.value.target?.puffs ?? 0))

// ---- the round's logic (signal.js) ----
const floor = createNoiseFloor()
const gate = createGate(blow.value ? LEVELS.blow : LEVELS.voice)
const target = d.value.target ?? {}
const puffCounter = createPuffCounter()
const hold =
  kind.value === 'long'
    ? createHoldTimer({ goalMs: target.holdMs, continuous: true, fallMs: target.holdMs * 1.5 })
    : kind.value === 'sustain'
      ? createHoldTimer({ goalMs: target.ms, continuous: target.continuous })
      : null
const gentle = kind.value === 'gentle' ? createGentleTimer({ goalMs: target.softMs }) : null
const stepper = kind.value === 'alternate' ? createStepper(d.value.steps.map(s => s.strength), { stepMs: target.stepMs }) : null
const tracker = kind.value === 'pitch' ? createPitchTracker() : null

let falls = 0
let spin = 0
let voicedMs = 0
let onMs = 0
let pitchedMs = 0
let loudHeights = false
let zoneMs = 0
let waitMs = 0
let totalWaitMs = 0
let nudgedAt = 0
let lastGain = 0
let lastPitch = { hz: 0, clarity: 0 }
let frameNo = 0
let quietUntil = 0
let cued = false

// ---- Csillám's hints: from the server, at most twice each, never back to back ----
const said = {}
let lastHint = -Infinity
function hint(key, now = performance.now()) {
  const text = d.value.hints?.[key]
  if (!text || now - lastHint < HINT_GAP_MS || (said[key] ?? 0) >= 2) return
  said[key] = (said[key] ?? 0) + 1
  lastHint = now
  emit('say', text)
}

function gained(now) {
  lastGain = now
  stuck.value = false
}

// ---- one frame of input → the game ----
function advance({ strength: s, level: l, hz, voiced, db, floorDb }, dt, now) {
  const before = progress.value
  if (blow.value) {
    // a blow, not a voice: "fúúú" said aloud doesn't count
    const b = voiced ? 0 : s
    if (s && voiced) {
      voicedMs += dt
      if (voicedMs > 1500) hint('voiced', now)
    }
    strength.value = b
    level.value = l
    const want = b === 2 ? 0.9 : b === 1 ? 0.35 : 0
    spin += (want * (0.6 + l) - spin) * (1 - Math.exp(-dt / 250))
    angle.value = (angle.value + Math.min(spin, reducedMotion() ? 0.25 : 2) * dt) % 360

    if (kind.value === 'puffs') {
      if (puffCounter.update(b > 0, dt)) {
        puffs.value++
        pluck(520 + puffs.value * 90)
      }
      progress.value = Math.min(1, puffs.value / count.value)
      if (puffs.value >= count.value) return finish(1)
    } else if (kind.value === 'long') {
      const r = hold.update(b > 0, dt)
      progress.value = r.progress
      falling.value = r.falling
      if (r.done) return finish(1)
    } else if (kind.value === 'gentle') {
      const r = gentle.update(b, dt)
      progress.value = r.progress
      if (r.popped) {
        oops.value++
        hint('tooStrong', now)
      }
      if (r.done) return finish(1)
    } else {
      const r = stepper.update(b, dt)
      progress.value = (r.index + r.stepProgress) / count.value
      if (r.stepped) {
        stepIndex.value = r.index
        pluck(660)
        gained(now)
        if (!r.done) emit('say', d.value.steps[r.index].say)
      }
      if (r.done) return finish(1)
    }
  } else if (kind.value === 'sustain') {
    strength.value = s
    level.value = l
    const r = hold.update(s > 0, dt)
    if (r.falls > falls) {
      falls = r.falls
      hint('fell', now)
    }
    progress.value = r.progress
    falling.value = r.falling
    if (r.done) return finish(falls === 0 ? 1 : falls <= 2 ? 2 : 3)
  } else {
    pitchFrame(s, l, hz, db, floorDb, dt, now)
  }
  if (progress.value > before + 0.001) gained(now)
  if (!lastGain) lastGain = now
  if (now - lastGain > STUCK_MS && !stuck.value) {
    stuck.value = true
    hint('quiet', now)
  }
}

/** High voice up, low voice down; the flyer waits at each star until it is at the star's height. */
function pitchFrame(s, l, hz, db, floorDb, dt, now) {
  strength.value = s
  level.value = l
  const on = s > 0
  let want = null
  if (noMic.value) {
    want = holding.value ? holdHeight.value : null
  } else if (on) {
    onMs += dt
    if (hz > 0) pitchedMs += dt
    // no pitch to be found (a whisper, a noisy mic): loudness instead
    if (!loudHeights && onMs > 1500 && pitchedMs / onMs < 0.3) loudHeights = true
    want = loudHeights ? heightFromLoudness(db, floorDb) : tracker.update(hz, dt)
  }
  y.value += ((want ?? (on ? y.value : 0.5)) - y.value) * (1 - Math.exp(-dt / (want === null ? 700 : 150)))

  const next = stars.find(st => !st.got)
  if (!next) return
  if (on) x.value = Math.min(next.x - 0.03, x.value + dt * SPEED)
  const atStar = x.value >= next.x - 0.031
  waitingFor.value = atStar ? (next.high ? 'high' : 'low') : null
  if (!atStar) return
  const inZone = next.high ? y.value >= 0.62 : y.value <= 0.38
  zoneMs = inZone ? zoneMs + dt : 0
  if (zoneMs >= IN_ZONE_MS) {
    next.got = true
    zoneMs = waitMs = 0
    waitingFor.value = null
    sparkle()
    gained(now)
    progress.value = stars.filter(st => st.got).length / stars.length
    if (stars.every(st => st.got)) {
      x.value = Math.min(0.92, next.x + 0.04)
      return finish(totalWaitMs < 12000 ? 1 : totalWaitMs < 25000 ? 2 : 3)
    }
    return
  }
  waitMs += dt
  totalWaitMs += dt
  if (waitMs > 4000) hint(next.high ? 'high' : 'low', now)
  // still can't reach it: bring the star a little closer to the child's voice
  if (tracker && !loudHeights && waitMs > 6000 && now - nudgedAt > 1500) {
    tracker.nudge(next.high ? 1 : -1)
    nudgedAt = now
  }
}

// ---- the loop ----
let raf = 0
let last = 0
function frame(now) {
  raf = requestAnimationFrame(frame)
  const dt = last ? Math.min(100, now - last) : 16
  last = now
  if (phase.value !== 'listen' || props.locked) return

  if (noMic.value) {
    const on = holding.value
    advance({ strength: on ? 1 : 0, level: on ? 0.6 : 0, hz: 0, voiced: false, db: 0, floorDb: 0 }, dt, now)
    return
  }
  const samples = mic.read()
  if (!samples) return
  // Csillám is talking (and a moment after): don't listen, or her voice would play the game
  if (guide.talking) quietUntil = now + 400
  pausedForCsillam.value = now < quietUntil
  if (pausedForCsillam.value) {
    level.value = 0
    strength.value = 0
    lastGain = now
    return
  }
  const db = toDb(rms(samples))
  floor.update(db, dt)
  calibrating.value = !floor.calibrated
  if (!floor.calibrated) return
  if (kind.value === 'alternate' && !cued) {
    cued = true
    emit('say', d.value.steps[0].say)
    return
  }
  const s = gate.update(db, floor.db, dt)
  if (!s) lastPitch = { hz: 0, clarity: 0 }
  else if (++frameNo % 3 === 1) lastPitch = estimatePitch(samples, mic.sampleRate())
  advance({ strength: s, level: meterLevel(db, floor.db), hz: lastPitch.hz, voiced: isVoiced(lastPitch), db, floorDb: floor.db }, dt, now)
}

async function begin() {
  if (phase.value !== 'wait') return
  phase.value = 'listen'
  raf = requestAnimationFrame(frame)
  await mic.start()
  if (props.locked || phase.value !== 'listen') mic.stop()
}

function stopAll() {
  cancelAnimationFrame(raf)
  mic.stop()
}

function finish(tries) {
  if (phase.value === 'done') return
  phase.value = 'done'
  progress.value = 1
  level.value = 0
  strength.value = 0
  stopAll()
  sparkle()
  later(() => emit('answer', { correct: true, say: d.value.onCorrect, tries }), 700)
}

watch(
  () => [props.promptDone, props.locked],
  ([done, locked]) => {
    if (locked) stopAll()
    else if (done) begin()
  },
  { immediate: true },
)
onBeforeUnmount(stopAll)

// ---- the parent's buttons, without a mic (or when it doesn't hear the child) ----
function parentStep() {
  if (props.locked || phase.value !== 'listen') return
  if (!blow.value) return finish(noMic.value ? 1 : 2)
  if (kind.value === 'puffs') {
    puffs.value++
    progress.value = puffs.value / count.value
    pluck(520 + puffs.value * 90)
    if (puffs.value >= count.value) finish(1)
  } else if (kind.value === 'alternate') {
    stepIndex.value++
    progress.value = stepIndex.value / count.value
    if (stepIndex.value >= count.value) finish(1)
    else emit('say', d.value.steps[stepIndex.value].say)
  } else {
    finish(1)
  }
}

function onHold(down, height) {
  holding.value = down
  if (height !== null) holdHeight.value = height
}

// ---- words on the screen ----
const tone = computed(() => {
  if (phase.value !== 'listen' || !strength.value) return 'off'
  if (kind.value === 'gentle' || kind.value === 'alternate') return strength.value === 2 ? 'strong' : 'soft'
  return 'on'
})
const status = computed(() => {
  if (phase.value === 'done') return t('voice.done')
  if (phase.value === 'wait') return t('voice.wait')
  if (noMic.value) return blow.value ? t('voice.noMicBlow') : t('voice.noMicHold')
  if (mic.status.value === 'asking') return t('voice.asking')
  if (mic.status.value === 'suspended') return t('voice.tapToStart')
  if (pausedForCsillam.value) return t('voice.wait')
  if (calibrating.value) return t('voice.calibrating')
  if (kind.value === 'puffs') return t('voice.puffs', { done: puffs.value, total: count.value })
  if (kind.value === 'alternate') return d.value.steps[stepIndex.value]?.strength === 'strong' ? t('voice.strong') : t('voice.soft')
  if (kind.value === 'gentle') return strength.value === 2 ? t('voice.softer') : t('voice.blowSoftly')
  if (blow.value) return falling.value ? t('voice.keepGoing') : t('voice.blow')
  if (waitingFor.value) return waitingFor.value === 'high' ? t('voice.higher') : t('voice.lower')
  if (falling.value) return t('voice.keepGoing')
  return strength.value ? t('voice.flying') : t('voice.sayIt', { sound: d.value.shown })
})
const sceneLabel = computed(() => (blow.value ? t(`voice.scene.${d.value.scene}`) : noMic.value ? t('voice.skyHold') : t('voice.sky')))
</script>

<template>
  <div class="voice">
    <button v-if="!blow" type="button" class="model" @click="emit('say', data.sayModel)">
      <EmojiArt class="model-art" :char="data.emoji" />
      <span class="model-text">{{ data.shown }}</span>
      <EmojiArt class="model-speaker" :char="ICONS.speaker" :label="t('voice.hear')" />
    </button>

    <BlowScene
      v-if="blow"
      :scene="data.scene"
      :kind="data.kind"
      :count="count"
      :puffs="puffs"
      :progress="progress"
      :level="level"
      :strength="strength"
      :angle="angle"
      :oops="oops"
      :finished="phase === 'done'"
      :friend="data.friend ?? ''"
      :label="sceneLabel"
    />
    <FlyScene
      v-else
      :mode="data.mode"
      :flyer="data.flyer"
      :progress="progress"
      :on="strength > 0"
      :level="level"
      :falling="falling"
      :x="x"
      :y="y"
      :stars="stars"
      :pressable="noMic && phase === 'listen'"
      :finished="phase === 'done'"
      :label="sceneLabel"
      @hold="onHold"
    />

    <div v-if="kind === 'alternate'" class="steps" role="img" :aria-label="t('voice.steps', { done: stepIndex, total: count })">
      <span
        v-for="(s, i) in data.steps"
        :key="i"
        class="step"
        :class="{ 'step--now': i === stepIndex && phase === 'listen', 'step--done': i < stepIndex }"
      >
        <EmojiArt :char="STEP_ICONS[s.strength]" />
        <small>{{ s.strength === 'strong' ? t('voice.strongStep') : t('voice.softStep') }}</small>
      </span>
    </div>

    <div class="status-row">
      <VoiceMeter :level="level" :progress="progress" :tone="tone" :label="t('voice.meter', { percent: Math.round(level * 100) })" />
      <p class="status" aria-live="polite">{{ status }}</p>
    </div>

    <div class="bz-row">
      <BzButton :icon="ICONS.speaker" @click="emit('replay')">{{ t('voice.again') }}</BzButton>
      <BzButton v-if="mic.status.value === 'suspended'" variant="primary" :icon="ICONS.mic" @click="mic.resume">{{ t('voice.start') }}</BzButton>
      <BzButton v-if="showParent" variant="primary" :icon="blow ? '🌬️' : ICONS.done" :disabled="locked" @click="parentStep">
        {{ blow ? t('voice.blew') : t('voice.finished') }}
      </BzButton>
    </div>
    <p class="note">{{ showParent && !noMic ? t('voice.parentHint') : t('voice.privacy') }}</p>
  </div>
</template>

<style scoped>
.voice {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  width: 100%;
  max-width: 460px;
  margin: 0 auto;
}
.model {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 56px;
  padding: 6px 18px 6px 10px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  color: var(--bz-ink);
  box-shadow: var(--bz-shadow-sm);
}
.model-art {
  font-size: 34px;
}
.model-text {
  font-size: 30px;
  font-weight: 800;
  letter-spacing: 2px;
}
.model-speaker {
  font-size: 22px;
}
.steps {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
}
.step {
  display: grid;
  justify-items: center;
  min-width: 56px;
  padding: 4px 8px;
  border-radius: var(--bz-radius-sm);
  background: var(--bz-card);
  font-size: 26px;
  line-height: 1.1;
  opacity: 0.55;
  box-shadow: var(--bz-shadow-sm);
  transition:
    transform 0.25s var(--bz-spring),
    opacity 0.25s;
}
.step small {
  font-size: 13px;
  font-weight: 700;
  color: var(--bz-muted);
}
.step--now {
  opacity: 1;
  transform: scale(1.15);
}
.step--done {
  opacity: 0.9;
  background: var(--bz-soft);
}
.status-row {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  justify-content: center;
}
.status {
  margin: 0;
  min-width: 0;
  max-width: 300px;
  font-size: var(--bz-text-md);
  font-weight: 800;
  line-height: 1.25;
}
.note {
  margin: 0;
  font-size: var(--bz-text-sm);
  color: var(--bz-muted);
  text-align: center;
}
</style>
