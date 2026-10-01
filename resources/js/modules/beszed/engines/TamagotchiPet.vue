<script setup>
import { computed } from 'vue'

/**
 * The Tamagotchi pet: its own little unicorn, drawn the same way Csillám is
 * (inline SVG body + HTML overlay layers for continuous motion, so bobbing,
 * clapping and the chewing animation stay on the compositor). Unlike Csillám
 * it isn't tied to the global guide store — its mood and one-shot actions
 * come from props/emits, since a session can have several of these later.
 *
 * Feeding is a real drag-and-drop: the food the child drags IS the element
 * that travels to the mouth (see useDrag in TamagotchiEngine), so this
 * component only exposes `.mouth` as the drop target and plays the chew/
 * cheek-puff reaction once the engine confirms the food landed.
 */
const props = defineProps({
  /** idle | happy | sad | hungry | sick | sleeping */
  mood: { type: String, default: 'idle' },
  /** Set true for one beat to play the eat/play/pet reaction; engine clears it after. */
  action: { type: String, default: '' },
  /** data-drop id the mouth registers as, for useDrag to find. */
  mouthZone: { type: String, default: 'mouth' },
  palette: { type: Array, default: () => ['#8FD9FF', '#BFEFFF', '#6FC3FF', '#D9F4FF'] },
})
const emit = defineEmits(['tap'])

const gradientId = `bz-pet-horn-${Math.random().toString(36).slice(2, 8)}`
const maneStyle = computed(() => Object.fromEntries(props.palette.map((c, i) => [`--mane-${i + 1}`, c])))
const classes = computed(() => ['pet-uni', props.mood !== 'idle' && `mood-${props.mood}`, props.action && `act-${props.action}`])
</script>

<template>
  <span :class="classes" :style="maneStyle" role="img" aria-label="Kis kedvenc" @click="emit('tap')">
    <svg class="uni" viewBox="0 0 200 222" aria-hidden="true">
      <defs>
        <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#FFF1B8" />
          <stop offset="1" stop-color="#F5B83D" />
        </linearGradient>
      </defs>
      <!-- tail -->
      <g class="pet-tail">
        <circle cx="158" cy="180" r="14" style="fill: var(--mane-1)" />
        <circle cx="169" cy="165" r="11" style="fill: var(--mane-2)" />
        <circle cx="174" cy="151" r="9" style="fill: var(--mane-3)" />
        <circle cx="172" cy="139" r="7" style="fill: var(--mane-4)" />
      </g>
      <!-- body + feet -->
      <ellipse cx="100" cy="174" rx="54" ry="40" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
      <ellipse cx="76" cy="210" rx="18" ry="9" fill="#C9B6FF" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
      <ellipse cx="124" cy="210" rx="18" ry="9" fill="#C9B6FF" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />

      <g class="u-head">
        <!-- mane -->
        <circle cx="56" cy="72" r="20" style="fill: var(--mane-1)" />
        <circle cx="48" cy="100" r="17" style="fill: var(--mane-2)" />
        <circle cx="54" cy="127" r="14" style="fill: var(--mane-3)" />
        <circle cx="144" cy="70" r="18" style="fill: var(--mane-4)" />
        <circle cx="152" cy="96" r="14" style="fill: var(--mane-1)" />
        <!-- ears -->
        <path class="ear ear--l" d="M64 64 L58 30 L88 50 Z" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <path class="ear ear--r" d="M136 64 L142 30 L112 50 Z" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <path d="M66 58 L63 40 L78 51 Z" fill="#FFC2DC" />
        <path d="M134 58 L137 40 L122 51 Z" fill="#FFC2DC" />
        <!-- face -->
        <ellipse cx="100" cy="98" rx="54" ry="50" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <circle cx="78" cy="54" r="15" style="fill: var(--mane-1)" />
        <circle cx="92" cy="46" r="10" style="fill: var(--mane-2)" />
        <circle cx="123" cy="52" r="12" style="fill: var(--mane-3)" />
        <!-- horn -->
        <path d="M90 52 L100 6 L110 52 Z" :fill="`url(#${gradientId})`" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <path d="M93 40 L107 34 M95 28 L105 24" stroke="#D8962A" stroke-width="2.5" stroke-linecap="round" />
        <!-- cheeks -->
        <circle class="cheek" cx="64" cy="114" r="9" fill="#FFB3D1" opacity=".75" />
        <circle class="cheek" cx="136" cy="114" r="9" fill="#FFB3D1" opacity=".75" />
        <!-- eyes: open / happy / sad / closed -->
        <g class="e-open">
          <ellipse cx="80" cy="94" rx="8.5" ry="11.5" fill="#3B1F4A" />
          <ellipse cx="120" cy="94" rx="8.5" ry="11.5" fill="#3B1F4A" />
          <circle cx="83" cy="89" r="3.2" fill="#fff" />
          <circle cx="123" cy="89" r="3.2" fill="#fff" />
          <path d="M71 86 L66 82 M125 86 L130 82" stroke="#3B1F4A" stroke-width="2.5" stroke-linecap="round" />
        </g>
        <g class="e-happy" stroke="#3B1F4A" stroke-width="4.5" fill="none" stroke-linecap="round">
          <path d="M71 97 Q80 84 89 97" />
          <path d="M111 97 Q120 84 129 97" />
        </g>
        <g class="e-sad">
          <ellipse cx="80" cy="97" rx="7.5" ry="9" fill="#3B1F4A" />
          <ellipse cx="120" cy="97" rx="7.5" ry="9" fill="#3B1F4A" />
          <path d="M69 84 L89 78 M131 84 L111 78" stroke="#3B1F4A" stroke-width="3.5" stroke-linecap="round" />
          <path d="M86 108 Q82 116 86 119 Q90 116 86 108 Z" fill="#7CC7FF" />
        </g>
        <g class="e-closed" stroke="#3B1F4A" stroke-width="4" fill="none" stroke-linecap="round">
          <path d="M72 94 Q80 99 88 94" />
          <path d="M112 94 Q120 99 128 94" />
        </g>
        <!-- muzzle + mouths (the eating mouth is an HTML overlay, see .mouth) -->
        <ellipse cx="100" cy="124" rx="30" ry="19" fill="#FFD9EA" stroke="#5A3D6E" stroke-width="2.5" />
        <ellipse cx="91" cy="119" rx="3" ry="2.2" fill="#5A3D6E" opacity=".55" />
        <ellipse cx="109" cy="119" rx="3" ry="2.2" fill="#5A3D6E" opacity=".55" />
        <path class="m-smile" d="M90 130 Q100 138 110 130" stroke="#5A3D6E" stroke-width="3" fill="none" stroke-linecap="round" />
        <path class="m-happy" d="M86 127 Q100 147 114 127 Z" fill="#8A2E4E" stroke="#5A3D6E" stroke-width="2.5" stroke-linejoin="round" />
        <path class="m-sad" d="M91 136 Q100 129 109 136" stroke="#5A3D6E" stroke-width="3" fill="none" stroke-linecap="round" />
      </g>
    </svg>

    <!-- composited overlays: mouth (drop target for feeding), sparkles, zzz -->
    <span class="mouth" :data-drop="mouthZone" aria-hidden="true" />
    <span
      v-for="i in 4"
      :key="i"
      class="spark"
      :style="{ left: `${18 + i * 18}%`, top: `${10 + (i % 2) * 55}%`, animationDelay: `${i * 0.12}s` }"
      aria-hidden="true"
    >
      <svg viewBox="-8 -8 16 16"><path d="M0 -8 L2 -2 L8 0 L2 2 L0 8 L-2 2 L-8 0 L-2 -2 Z" fill="#FFD84D" /></svg>
    </span>
    <span
      v-for="i in 3"
      :key="'zzz' + i"
      class="zzz"
      :style="{ animationDelay: `${i * 0.4}s` }"
      aria-hidden="true"
    >z</span>
  </span>
</template>

<style scoped>
.pet-uni {
  position: relative;
  display: block;
  width: 100%;
  cursor: pointer;
  will-change: transform;
  transform-origin: 50% 97%;
  animation: bob 3s ease-in-out infinite;
}
.uni {
  display: block;
  width: 100%;
  height: auto;
  overflow: visible;
  filter: drop-shadow(0 10px 8px rgba(0, 0, 0, 0.15));
}
.uni circle,
.uni path {
  transition: fill 0.6s ease;
}

/* which face parts show per mood (discrete switches, no per-frame cost) */
.e-happy,
.e-sad,
.e-closed,
.m-happy,
.m-sad {
  display: none;
}
.mood-happy .e-open,
.mood-happy .m-smile,
.mood-sad .e-open,
.mood-sad .m-smile,
.mood-hungry .e-open,
.mood-hungry .m-smile,
.mood-sick .e-open,
.mood-sick .m-smile,
.mood-sleeping .e-open,
.mood-sleeping .m-smile {
  display: none;
}
.mood-happy .e-happy,
.mood-happy .m-happy {
  display: inline;
}
.mood-sad .e-sad,
.mood-sad .m-sad,
.mood-hungry .e-sad,
.mood-hungry .m-sad,
.mood-sick .e-sad,
.mood-sick .m-sad {
  display: inline;
}
.mood-sleeping .e-closed {
  display: inline;
}

/* sick: a slightly green tint on the face */
.mood-sick .uni {
  filter: drop-shadow(0 10px 8px rgba(0, 0, 0, 0.15)) saturate(0.75) hue-rotate(30deg);
}

/* eating mouth: an HTML ellipse over the muzzle, scaled on the compositor.
   Always laid out (opacity, not display:none) so useDrag can measure its real
   rect as a drop zone even before the first feed. */
.mouth {
  position: absolute;
  left: 50%;
  top: 59.5%;
  width: 10%;
  height: 7%;
  opacity: 0;
  border-radius: 50%;
  background: #8a2e4e;
  transform: translate(-50%, -50%);
}
.act-eat .mouth {
  opacity: 1;
  animation: chew 0.65s ease-in-out;
}

@keyframes chew {
  0%,
  100% {
    transform: translate(-50%, -50%) scaleY(0.5);
  }
  30%,
  60% {
    transform: translate(-50%, -50%) scaleY(1.3) scaleX(1.15);
  }
}
.act-eat .cheek {
  animation: cheek-puff 0.65s ease-in-out;
}
@keyframes cheek-puff {
  40% {
    transform: scale(1.35);
  }
}

/* sparkles (happy/playing): HTML overlays, transform/opacity only */
.spark {
  position: absolute;
  display: none;
  width: 11%;
  aspect-ratio: 1;
  transform: translate(-50%, -50%);
}
.spark svg {
  display: block;
  width: 100%;
  height: 100%;
}
.mood-happy .spark,
.act-play .spark {
  display: block;
  animation: twinkle 0.6s ease-in-out infinite alternate;
}

/* zzz (sleeping): drift up and fade */
.zzz {
  position: absolute;
  display: none;
  left: 68%;
  top: 30%;
  font-weight: 800;
  font-size: 16%;
  color: #8a7dc9;
}
.mood-sleeping .zzz {
  display: block;
  animation: zzz-float 2.4s ease-in infinite;
}
@keyframes zzz-float {
  0% {
    opacity: 0;
    transform: translate(0, 0) scale(0.6);
  }
  20% {
    opacity: 1;
  }
  100% {
    opacity: 0;
    transform: translate(14px, -30px) scale(1.3);
  }
}

/* whole-body moves on the wrapper (compositor) */
.mood-happy,
.act-play {
  animation: jump 0.5s ease-out 3;
}
.act-pet {
  animation: wobble-body 0.5s ease-in-out;
}
.mood-sleeping {
  animation: breathe 3.2s ease-in-out infinite;
}
.mood-sad .u-head,
.mood-hungry .u-head,
.mood-sick .u-head {
  transform-box: view-box;
  transform-origin: 100px 145px;
  animation: droop 1.6s ease-in-out infinite alternate;
}

.ear {
  transition: transform 0.3s ease;
  transform-box: fill-box;
  transform-origin: 50% 100%;
}
.act-pet .ear--l {
  animation: ear-wiggle 0.5s ease-in-out;
}
.act-pet .ear--r {
  animation: ear-wiggle 0.5s ease-in-out 0.08s;
}

@keyframes bob {
  50% {
    transform: translateY(-1.8%);
  }
}
@keyframes breathe {
  50% {
    transform: scale(1.035);
  }
}
@keyframes jump {
  0%,
  100% {
    transform: translateY(0);
  }
  40% {
    transform: translateY(-9%) scale(1.04);
  }
}
@keyframes wobble-body {
  0%,
  100% {
    transform: rotate(0deg);
  }
  25% {
    transform: rotate(-6deg);
  }
  75% {
    transform: rotate(6deg);
  }
}
@keyframes droop {
  to {
    transform: rotate(-6deg) translateY(4px);
  }
}
@keyframes ear-wiggle {
  0%,
  100% {
    transform: rotate(0deg);
  }
  50% {
    transform: rotate(14deg);
  }
}
@keyframes twinkle {
  from {
    opacity: 0.4;
    transform: translate(-50%, -50%) scale(0.8);
  }
  to {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1.1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .pet-uni,
  .pet-uni * {
    animation: none !important;
  }
}
</style>
