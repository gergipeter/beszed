<script setup>
import { computed, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { t } from '../../i18n'

/**
 * Hangrepülő's sky. sustain: the flyer rises from the ground to its goal (the Moon, a flower, the clouds)
 * while the voice is on, and sinks back when it stops (level 2). pitch: it flies to the right while the
 * voice is on, up and down with the voice, and collects the stars. Without a microphone the sky itself
 * is pressed and held (and, for pitch, the finger sets the height): `hold` reports it.
 */
const props = defineProps({
  mode: { type: String, required: true },
  /** rocket · bee · balloon (HangrepuloRounds::FLYERS) */
  flyer: { type: String, required: true },
  progress: { type: Number, default: 0 },
  on: { type: Boolean, default: false },
  level: { type: Number, default: 0 },
  falling: { type: Boolean, default: false },
  /** pitch: 0–1 from the left, 0–1 from the bottom */
  x: { type: Number, default: 0.08 },
  y: { type: Number, default: 0.5 },
  /** pitch: [{ x, high, got }] */
  stars: { type: Array, default: () => [] },
  pressable: { type: Boolean, default: false },
  finished: { type: Boolean, default: false },
  label: { type: String, default: '' },
})
const emit = defineEmits(['hold'])

/** What flies, where to, and what it leaves behind while the voice is on. */
const FLYERS = {
  rocket: { emoji: '🚀', goal: '🌙', trail: 'flame' },
  bee: { emoji: '🐝', goal: '🌻', trail: 'wings' },
  balloon: { emoji: '🎈', goal: '☁️', trail: 'none' },
}
const art = computed(() => FLYERS[props.flyer] ?? FLYERS.rocket)

const box = ref(null)
/** pitch: where the flyer is, in % of the scene */
const left = computed(() => (props.mode === 'pitch' ? props.x * 100 : 50))
const bottom = computed(() => (props.mode === 'pitch' ? 8 + props.y * 72 : 4 + props.progress * 66))
const tilt = computed(() => {
  if (props.mode === 'pitch') return art.value.trail === 'flame' ? 45 : 0 // the rocket points right
  return props.falling ? 12 : 0
})
const starTop = high => (high ? 14 : 70)

function heightAt(e) {
  const r = box.value?.getBoundingClientRect()
  return r ? Math.min(1, Math.max(0, 1 - (e.clientY - r.top) / r.height)) : 0.5
}
function down(e) {
  if (!props.pressable) return
  e.currentTarget.setPointerCapture?.(e.pointerId)
  emit('hold', true, heightAt(e))
}
function move(e) {
  if (props.pressable && e.buttons) emit('hold', true, heightAt(e))
}
function up() {
  if (props.pressable) emit('hold', false, null)
}
</script>

<template>
  <div
    ref="box"
    class="sky"
    :class="[`sky--${mode}`, `sky--${art.trail}`, { 'sky--press': pressable, 'sky--done': finished }]"
    :role="pressable ? 'button' : 'img'"
    :aria-label="label"
    @pointerdown="down"
    @pointermove="move"
    @pointerup="up"
    @pointercancel="up"
    @lostpointercapture="up"
    @contextmenu.prevent
  >
    <span class="twinkle s1" /><span class="twinkle s2" /><span class="twinkle s3" />
    <div class="ground" />

    <template v-if="mode === 'sustain'">
      <EmojiArt class="goal" :char="art.goal" />
      <div class="path"><span class="path-fill" :style="{ transform: `scaleY(${progress})` }" /></div>
    </template>
    <template v-else>
      <span class="band band--high">{{ t('voice.high') }}</span>
      <span class="band band--low">{{ t('voice.low') }}</span>
      <span
        v-for="(s, i) in stars"
        :key="i"
        class="star"
        :class="{ 'star--got': s.got }"
        :style="{ left: `${s.x * 100}%`, top: `${starTop(s.high)}%` }"
      ><EmojiArt char="⭐" /></span>
    </template>

    <div class="flyer" :style="{ transform: `translate(${left}cqw, ${(-bottom * 0.75).toFixed(2)}cqw)` }">
      <div class="flyer-body" :class="{ 'flyer-body--on': on }" :style="{ transform: `rotate(${tilt}deg)` }">
        <span v-if="art.trail === 'flame'" class="flame" :style="{ opacity: on ? 1 : 0, transform: `scaleY(${0.6 + level * 0.9})` }" />
        <EmojiArt class="flyer-art" :char="art.emoji" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.sky {
  position: relative;
  width: 100%;
  /* a short screen (a tablet held sideways) keeps the buttons below in view */
  max-width: min(460px, 55vh);
  margin: 0 auto;
  aspect-ratio: 4 / 3;
  border-radius: var(--bz-radius-lg);
  overflow: hidden;
  container-type: inline-size;
  background: linear-gradient(#7cc8ff, #e2f4ff);
  box-shadow: var(--bz-shadow);
  touch-action: none;
  user-select: none;
  -webkit-user-select: none;
}
/* the rocket flies into the night sky, to the Moon */
.sky--flame {
  background: linear-gradient(#1e2560 0%, #4a4fb0 45%, #9fd7ff 100%);
}
.sky--wings {
  background: linear-gradient(#a6dcff, #fff3c4);
}
.sky--press {
  cursor: pointer;
}
.twinkle {
  position: absolute;
  width: 1.4cqw;
  height: 1.4cqw;
  border-radius: 50%;
  background: #fff;
  opacity: 0;
}
.sky--flame .twinkle {
  opacity: 0.85;
  animation: twinkle 2.2s ease-in-out infinite alternate;
}
.s1 {
  left: 14%;
  top: 12%;
}
.s2 {
  left: 78%;
  top: 22%;
  animation-delay: 0.7s;
}
.s3 {
  left: 30%;
  top: 34%;
  animation-delay: 1.3s;
}
@keyframes twinkle {
  to {
    opacity: 0.25;
  }
}
.ground {
  position: absolute;
  left: -10%;
  right: -10%;
  bottom: -14cqw;
  height: 24cqw;
  border-radius: 50%;
  background: linear-gradient(#a9df86, #7cc462);
}
.goal {
  position: absolute;
  left: 50%;
  top: 3cqw;
  margin-left: -8cqw;
  font-size: 16cqw;
  line-height: 1;
}
/* how far it has come: a dotted trail from the ground to the goal */
.path {
  position: absolute;
  left: 50%;
  top: 20cqw;
  bottom: 10cqw;
  width: 1.6cqw;
  margin-left: 14cqw;
  border-radius: 1cqw;
  background: rgba(255, 255, 255, 0.35);
  overflow: hidden;
}
.path-fill {
  position: absolute;
  inset: 0;
  background: var(--bz-sun);
  transform-origin: 50% 100%;
}
.band {
  position: absolute;
  left: 3cqw;
  padding: 0.6cqw 2.4cqw;
  border-radius: var(--bz-radius-pill);
  background: rgba(255, 255, 255, 0.8);
  color: #3b1f4a;
  font-size: max(13px, 3.6cqw);
  font-weight: 800;
}
.band--high {
  top: 3cqw;
}
.band--low {
  bottom: 3cqw;
}
.star {
  position: absolute;
  margin-left: -5cqw;
  font-size: 10cqw;
  line-height: 1;
  animation: glint 1.6s ease-in-out infinite alternate;
  transition:
    transform 0.5s var(--bz-spring),
    opacity 0.5s;
}
.star--got {
  transform: scale(1.8) translateY(-4cqw);
  opacity: 0;
  animation: none;
}
@keyframes glint {
  to {
    transform: scale(1.12) rotate(8deg);
  }
}
.flyer {
  /* moved by transform only (the scene is 100 × 75 cqw) */
  position: absolute;
  left: 0;
  bottom: 0;
  width: 0;
  height: 0;
}
.flyer-body {
  position: absolute;
  left: -8cqw;
  bottom: 0;
  width: 16cqw;
  display: grid;
  place-items: center;
  transition: transform 0.3s;
}
.flyer-art {
  font-size: 15cqw;
  line-height: 1;
}
/* the rocket emoji leans 45° to the upper right: turned upright, its flame comes out below */
.sky--flame.sky--sustain .flyer-art {
  transform: rotate(-45deg);
}
.flame {
  position: absolute;
  left: 50%;
  top: 88%;
  width: 5cqw;
  height: 9cqw;
  margin-left: -2.5cqw;
  border-radius: 50% 50% 50% 50% / 40% 40% 60% 60%;
  background: radial-gradient(circle at 50% 30%, #fff6a8, #ffb020 50%, rgba(255, 90, 30, 0));
  transform-origin: 50% 0;
  transition: opacity 0.15s;
}
.sky--pitch .flame {
  top: 70%;
  left: 18%;
  rotate: 45deg;
}
.flyer-body--on .flyer-art {
  animation: buzz 0.18s linear infinite alternate;
}
.sky--none .flyer-body--on .flyer-art {
  animation: none;
}
@keyframes buzz {
  to {
    translate: 0 -0.6cqw;
  }
}
.sky--done {
  animation: cheer 0.7s var(--bz-spring);
}
@keyframes cheer {
  40% {
    transform: scale(1.03);
  }
}
@media (prefers-reduced-motion: reduce) {
  .twinkle,
  .star,
  .flyer-body--on .flyer-art,
  .sky--done {
    animation: none;
  }
}
</style>
