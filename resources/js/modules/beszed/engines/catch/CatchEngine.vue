<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useMotionPref } from '../../composables/useMotionPref'
import { t } from '../../i18n'
import { beep, tone } from '../../services/audio/sfx'
import { burst } from '../../services/effects/burst'
import { buzz } from '../../services/touch/feel'
import { useGuideStore } from '../../stores/guide'
import { engineEmits, engineProps } from '../contract'

/**
 * Kapd el! (go/no-go): bubbles float up one at a time, as the server's stream says (when, which lane, how
 * fast, which are targets); the child taps the targets and lets the others go. In `hear` mode every picture
 * says its word as it appears. A wrong catch or a target let go is a mistake: a soft sound, and the first time
 * Csillám explains it (`why`) while the bubbles stand still. The round ends as soon as `need` targets are caught,
 * graded by mistakes; if the stream runs out first it comes round once more, then the round ends gently.
 * Only transform/opacity move (Web Animations, so they pause and resume); with less motion everything is slower
 * and the bubbles don't sway.
 * data: CatchData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const guide = useGuideStore()
const { reduceMotion } = useMotionPref()
const calm = reduceMotion.value || Boolean(window.matchMedia?.('(prefers-reduced-motion: reduce)').matches)
/** Less motion: the whole stream runs this much slower. */
const SLOW = calm ? 1.4 : 1
/** Each catch climbs a step of the pentatonic scale. */
const SCALE = [523.25, 587.33, 659.25, 783.99, 880, 1046.5, 1174.66, 1318.5]
/** After an explanation the bubbles wait at least this long, at most the second, for Csillám to finish. */
const TALK_MIN_MS = 900
const TALK_MAX_MS = 9000

/** 'wait' (the rule is being said) → 'play' → 'between' (the stream comes round again) → 'play' → 'done' */
const phase = ref('wait')
const pass = ref(0)
const caught = ref([])
const goal = ref(props.data.goal)
/** Bubbles on screen: { key, item, state: 'fly' | 'caught' | 'wrong' | 'gone' } */
const flying = ref([])
/** Paused while Csillám explains (status line: "Figyelj…"). */
const listening = ref(false)
const sky = ref(null)

let mistakes = 0
let explainedWrong = false
let explainedMiss = false
/** Stream time in ms; runs only while nothing pauses it. */
let clock = 0
let lastFrame = 0
let frame = 0
let next = 0
let switched = false
let talkSince = 0
const pauses = new Set()
/** key → its rise animation, and its element */
const rises = new Map()
const elements = new Map()

const status = computed(() => {
  if (listening.value) return t('catch.listen')
  if (phase.value === 'between') return t('catch.again')
  if (phase.value === 'wait') return t('catch.ready')
  return ''
})

function grade() {
  const [smooth, some] = props.data.grade ?? [1, 3]
  return mistakes <= smooth ? 1 : mistakes <= some ? 2 : 3
}

function pause(reason) {
  pauses.add(reason)
  listening.value = pauses.has('talk')
  rises.forEach(anim => anim.playState === 'running' && anim.pause())
}

function resume(reason) {
  pauses.delete(reason)
  listening.value = pauses.has('talk')
  if (pauses.size === 0) rises.forEach(anim => anim.playState === 'paused' && anim.play())
}

/** Csillám says it; the bubbles stand still until she has finished. */
function talk(text) {
  emit('say', text)
  talkSince = performance.now()
  pause('talk')
}

function tick(now) {
  frame = requestAnimationFrame(tick)
  const dt = lastFrame ? Math.min(now - lastFrame, 100) : 0
  lastFrame = now
  if (phase.value !== 'play') return
  if (pauses.has('talk')) {
    const waited = now - talkSince
    if (waited > TALK_MIN_MS && (!guide.talking || waited > TALK_MAX_MS)) resume('talk')
  }
  if (pauses.size) return
  clock += dt
  const turn = props.data.switch
  if (turn && !switched && clock >= turn.at * SLOW) {
    switchRule(turn)
    return
  }
  const stream = props.data.stream
  while (next < stream.length && clock >= stream[next].at * SLOW) spawn(stream[next++])
  if (next >= stream.length && !flying.value.some(b => b.state === 'fly')) endPass()
}

function spawn(item) {
  flying.value.push({ key: `${pass.value}-${item.id}`, item, state: 'fly' })
  if (props.data.mode === 'hear') emit('say', item.name)
}

/** Ref callback: a new bubble starts rising from just below the sky to above it. */
function mount(el, b) {
  if (!el || elements.has(b.key) || b.state !== 'fly') return
  elements.set(b.key, el)
  const height = sky.value?.clientHeight ?? 400
  const size = el.offsetHeight || 90
  const anim = el.animate?.(
    [{ transform: `translate(-50%, ${Math.round(-size * 0.4)}px)` }, { transform: `translate(-50%, ${Math.round(-(height + size * 1.1))}px)` }],
    { duration: b.item.rise * SLOW, easing: 'linear', fill: 'forwards' },
  )
  if (!anim) return
  if (pauses.size) anim.pause()
  rises.set(b.key, anim)
  anim.finished.then(() => escaped(b)).catch(() => {})
}

function remove(b) {
  rises.get(b.key)?.cancel()
  rises.delete(b.key)
  elements.delete(b.key)
  flying.value = flying.value.filter(x => x.key !== b.key)
}

/** The bubble's inner ball pops, slips away or fades; then it is gone. */
function vanish(b, kind) {
  const ball = elements.get(b.key)?.firstElementChild
  const frames = {
    pop: [{ transform: 'scale(1)', opacity: 1 }, { transform: 'scale(1.35)', opacity: 0 }],
    slip: calm
      ? [{ opacity: 1 }, { opacity: 0.4, offset: 0.6 }, { opacity: 0 }]
      : [{ transform: 'translateX(0)', opacity: 1 }, { transform: 'translateX(-8px)' }, { transform: 'translateX(8px)' }, { transform: 'translateX(-5px)', opacity: 0.8 }, { transform: 'translateX(0)', opacity: 0 }],
    fade: [{ opacity: 1 }, { opacity: 0 }],
  }[kind]
  const anim = ball?.animate?.(frames, { duration: kind === 'slip' ? 520 : 280, easing: 'ease-out', fill: 'forwards' })
  if (anim) anim.finished.then(() => remove(b)).catch(() => remove(b))
  else remove(b)
}

function escaped(b) {
  if (b.state !== 'fly') return
  b.state = 'gone'
  remove(b)
  if (phase.value !== 'play' || !b.item.target) return
  mistakes++
  tone(196, 0.25)
  if (!explainedMiss) {
    explainedMiss = true
    talk(b.item.why)
  }
}

function tap(b, event) {
  if (props.locked || phase.value !== 'play' || pauses.size || b.state !== 'fly') return
  rises.get(b.key)?.pause()
  if (b.item.target) {
    b.state = 'caught'
    caught.value = [...caught.value, b.item.emoji]
    tone(SCALE[Math.min(caught.value.length - 1, SCALE.length - 1)], 0.22)
    buzz(10)
    const box = event.currentTarget?.getBoundingClientRect?.()
    if (box) burst({ x: box.left + box.width / 2, y: box.top + box.height / 2 }, { pieces: 8, reach: 50 })
    vanish(b, 'pop')
    if (caught.value.length >= props.data.need) finish(true)
    return
  }
  b.state = 'wrong'
  mistakes++
  beep(150, 0.2)
  buzz([10, 40, 10])
  vanish(b, 'slip')
  if (!explainedWrong) {
    explainedWrong = true
    talk(b.item.why)
  }
}

/** Everything still in the air fades without counting (the rule turned round, or the round is over). */
function clearAir() {
  flying.value.filter(b => b.state === 'fly').forEach(b => {
    b.state = 'gone'
    rises.get(b.key)?.pause()
    vanish(b, 'fade')
  })
}

function switchRule(turn) {
  switched = true
  clearAir()
  goal.value = turn.goal
  talk(turn.say)
}

function startPass() {
  pass.value++
  clock = 0
  next = 0
  switched = false
  goal.value = props.data.goal
  pauses.clear()
  listening.value = false
  phase.value = 'play'
}

/** The stream ran out before enough were caught: once more, then a gentle end. */
function endPass() {
  if (pass.value < 2) {
    phase.value = 'between'
    emit('replay', [props.data.again])
    return
  }
  finish(false)
}

function finish(won) {
  phase.value = 'done'
  clearAir()
  if (won) emit('answer', { correct: true, say: props.data.onCorrect, tries: grade() })
  else if (caught.value.length) emit('answer', { correct: true, say: props.data.onEnd, tries: 3 })
  else emit('skip', props.data.onEnd)
}

watch(
  () => props.promptDone,
  done => {
    if (props.locked || phase.value === 'done') return
    if (!done) {
      // Csillám says the rule again: the bubbles wait for her
      if (phase.value === 'play') pause('prompt')
      return
    }
    if (phase.value === 'wait' || phase.value === 'between') startPass()
    else resume('prompt')
  },
  { immediate: true },
)

watch(
  () => props.locked,
  locked => {
    if (locked && phase.value === 'play') {
      phase.value = 'done'
      clearAir()
    }
  },
)

onMounted(() => {
  frame = requestAnimationFrame(tick)
  // the words of a listening round are fetched ahead, so each is said the moment its bubble appears
  if (props.data.mode === 'hear') guide.preload(props.data.stream.map(item => item.name))
})

onBeforeUnmount(() => {
  cancelAnimationFrame(frame)
  rises.forEach(anim => anim.cancel())
  rises.clear()
})
</script>

<template>
  <div class="bar">
    <div class="goal" role="img" :aria-label="t('catch.catchThese')">
      <b v-if="goal.text" class="letter">{{ goal.text }}</b>
      <EmojiArt v-for="(e, i) in goal.emojis" :key="`${e}-${i}`" :char="e" />
    </div>
    <div v-for="(e, i) in goal.avoid ?? []" :key="`no-${i}`" class="goal goal--no" role="img" :aria-label="t('catch.notThese')">
      <EmojiArt :char="e" />
    </div>
    <ol class="tray" :aria-label="t('catch.progress', { done: caught.length, total: data.need })">
      <li v-for="i in data.need" :key="i" class="slot" :class="{ 'slot--full': caught[i - 1] }">
        <EmojiArt v-if="caught[i - 1]" :char="caught[i - 1]" />
      </li>
    </ol>
  </div>

  <div ref="sky" class="sky" :class="{ 'sky--calm': calm, 'sky--listen': listening }" data-no-feel>
    <button
      v-for="b in flying"
      :key="b.key"
      :ref="el => mount(el, b)"
      type="button"
      class="bubble"
      :style="{ left: `${b.item.x * 100}%` }"
      :aria-label="b.item.name"
      @pointerdown.prevent="tap(b, $event)"
      @keydown.enter.prevent="tap(b, $event)"
      @keydown.space.prevent="tap(b, $event)"
    >
      <span class="ball"><span class="sway"><EmojiArt :char="b.item.emoji" /></span></span>
    </button>
    <p v-if="status" class="status" aria-live="polite">{{ status }}</p>
  </div>
</template>

<style scoped>
.bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 8px 10px;
  width: 100%;
  max-width: 560px;
}
/* what to catch: the picture(s) or the letter of the sound, on a green card; what not to: crossed out */
.goal {
  display: flex;
  align-items: center;
  gap: 4px;
  min-height: 52px;
  padding: 4px 12px;
  border-radius: 18px;
  background: color-mix(in srgb, var(--bz-leaf) 22%, var(--bz-card));
  box-shadow: inset 0 0 0 3px color-mix(in srgb, var(--bz-leaf) 70%, transparent), var(--bz-shadow-sm);
  font-size: 34px;
  line-height: 1;
}
.goal--no {
  position: relative;
  background: color-mix(in srgb, var(--bz-coral) 14%, var(--bz-card));
  box-shadow: inset 0 0 0 3px color-mix(in srgb, var(--bz-coral) 60%, transparent), var(--bz-shadow-sm);
  font-size: 28px;
}
/* the cross over the picture not to catch */
.goal--no::after {
  content: '';
  position: absolute;
  inset: 50% 8px auto;
  height: 4px;
  border-radius: 2px;
  background: var(--bz-coral);
  transform: rotate(-35deg);
}
.letter {
  font-size: 34px;
  font-weight: 900;
  color: var(--bz-text);
}
.tray {
  display: flex;
  gap: 5px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.slot {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--bz-soft);
  box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--bz-text) 10%, transparent);
  font-size: 22px;
}
.slot--full {
  background: color-mix(in srgb, var(--bz-sun) 55%, var(--bz-card));
  animation: fill 0.35s var(--bz-spring);
}
.sky {
  position: relative;
  width: 100%;
  max-width: 560px;
  height: clamp(220px, calc(100dvh - var(--bz-stage-room, 330px) - 190px), 560px);
  overflow: hidden;
  border-radius: 28px;
  background:
    radial-gradient(60% 40% at 20% 15%, rgba(255, 255, 255, 0.45), transparent 70%),
    linear-gradient(to bottom, color-mix(in srgb, var(--bz-sky-top, #8fd3ff) 70%, #fff), color-mix(in srgb, var(--bz-sky-bottom, #dff3ff) 80%, #fff));
  box-shadow: inset 0 0 0 3px rgba(255, 255, 255, 0.55), var(--bz-shadow-lg);
  touch-action: manipulation;
  user-select: none;
  -webkit-user-select: none;
}
.bubble {
  --size: clamp(76px, 22vw, 108px);
  position: absolute;
  top: 100%;
  width: var(--size);
  height: var(--size);
  padding: 0;
  border-radius: 50%;
  background: none;
  transform: translate(-50%, 0);
  will-change: transform;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}
/* a soap bubble: clear, with a bright window and a rim */
.ball {
  display: grid;
  place-items: center;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background:
    radial-gradient(circle at 32% 26%, rgba(255, 255, 255, 0.95) 0 7%, rgba(255, 255, 255, 0.3) 8% 18%, transparent 19%),
    radial-gradient(circle, rgba(220, 242, 255, 0.35) 52%, rgba(130, 195, 255, 0.55) 86%, rgba(200, 160, 255, 0.6) 100%);
  box-shadow:
    inset 0 0 0 2px rgba(255, 255, 255, 0.75),
    0 4px 10px rgba(0, 60, 120, 0.16);
  font-size: calc(var(--size) * 0.56);
  line-height: 1;
}
.sway {
  display: block;
  animation: sway 2.6s ease-in-out infinite alternate;
}
.sky--calm .sway {
  animation: none;
}
.status {
  position: absolute;
  left: 50%;
  bottom: 12px;
  margin: 0;
  padding: 6px 16px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-sm);
  font-weight: 800;
  color: var(--bz-muted);
  white-space: nowrap;
  transform: translateX(-50%);
  pointer-events: none;
}
@keyframes sway {
  from {
    transform: translateX(-5px) rotate(-3deg);
  }
  to {
    transform: translateX(5px) rotate(3deg);
  }
}
@keyframes fill {
  from {
    transform: scale(0.4);
  }
}
</style>
