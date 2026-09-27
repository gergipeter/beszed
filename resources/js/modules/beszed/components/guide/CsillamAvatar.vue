<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { config } from '../../config/options'
import { t } from '../../i18n'
import { useGuideStore } from '../../stores/guide'
import { useRewardsStore } from '../../stores/rewards'
import { emojiAssetName } from '../../utils/emoji'
import { ACCESSORY_ART } from './accessories'

/**
 * Csillám the unicorn. The drawing is inline SVG; the moods (talking, happy,
 * sad, hop, party) switch its parts through the classes below.
 *
 * Smoothness on phones: browsers can't hand SVG-internal animations to the GPU,
 * so everything that moves continuously lives on HTML layers around the SVG
 * (whole-body bob/jump on the wrapper, sparkles and the talking mouth as
 * overlays) and animates only transform/opacity. Inside the SVG only short,
 * finite motions remain (clapping arms, drooping head).
 */
const props = defineProps({
  name: { type: String, default: '' },
  /** What she wears (one id per slot); left out = what the child chose (rewards). */
  worn: { type: Object, default: undefined },
})

/** Sparkle positions in % of the drawing (from the original SVG coordinates) and size in % of its width. */
const SPARKS = [
  { left: 13, top: 18, size: 11.2, delay: 0 },
  { left: 88, top: 11.7, size: 8.8, delay: 0.15 },
  { left: 9, top: 67.6, size: 8, delay: 0.3 },
  { left: 93, top: 53.2, size: 10.4, delay: 0.45 },
]

const guide = useGuideStore()
const rewards = useRewardsStore()
/** Every worn slot resolved to its art, e.g. [{ slot: 'head', art: {...} }, ...]. */
const wornArt = computed(() => {
  const ids = props.worn === undefined ? rewards.worn : props.worn
  return Object.entries(ids ?? {})
    .map(([slot, id]) => ({ slot, art: ACCESSORY_ART[id] ?? null }))
    .filter(w => w.art)
})
/** Same picture set as the rest of the app (config.emoji); otherwise the device's emoji as SVG text. */
const base = config.emoji.baseUrl
const accessoryImage = art => (base ? `${base.replace(/\/?$/, '/')}${emojiAssetName(art.char)}${config.emoji.ext}` : null)
// Unique gradient id, in case two unicorns are ever on screen.
const gradientId = `bz-horn-${Math.random().toString(36).slice(2, 8)}`
const classes = computed(() => [
  'csillam',
  guide.mood !== 'idle' && guide.mood,
  { talking: guide.talking, party: guide.party },
])
const label = computed(() => t('guide.avatarLabel', { name: props.name || config.guideName }))

/**
 * Her eyes follow the child's finger (or mouse): the pupils glide a few units
 * towards it, and back to the middle after a while without a touch. At most
 * one measurement per frame; a small repaint of the eyes only.
 */
const el = ref(null)
const gaze = reactive({ x: 0, y: 0 })
const GAZE_X = 4
const GAZE_Y = 3.5
const REST_MS = 2500
let frame = 0
let rest = 0
let point = null

function look(event) {
  point = { x: event.clientX, y: event.clientY }
  frame ||= requestAnimationFrame(aim)
}
function aim() {
  frame = 0
  const box = el.value?.getBoundingClientRect()
  if (!box || !point) return
  const dx = point.x - (box.left + box.width / 2)
  const dy = point.y - (box.top + box.height * 0.42) // the eyes, not the middle of the body
  const distance = Math.hypot(dx, dy) || 1
  const reach = Math.min(1, distance / 160)
  gaze.x = (dx / distance) * GAZE_X * reach
  gaze.y = (dy / distance) * GAZE_Y * reach
  clearTimeout(rest)
  rest = setTimeout(() => Object.assign(gaze, { x: 0, y: 0 }), REST_MS)
}
onMounted(() => {
  window.addEventListener('pointermove', look, { passive: true })
  window.addEventListener('pointerdown', look, { passive: true })
})
onBeforeUnmount(() => {
  window.removeEventListener('pointermove', look)
  window.removeEventListener('pointerdown', look)
  cancelAnimationFrame(frame)
  clearTimeout(rest)
})
</script>

<template>
  <span ref="el" :class="classes" role="img" :aria-label="label">
    <svg class="uni" viewBox="0 0 200 222" aria-hidden="true">
      <defs>
        <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#FFF1B8" />
          <stop offset="1" stop-color="#F5B83D" />
        </linearGradient>
      </defs>
      <!-- tail -->
      <g>
        <circle cx="158" cy="180" r="14" fill="#FF9EC7" />
        <circle cx="169" cy="165" r="11" fill="#FFD36E" />
        <circle cx="174" cy="151" r="9" fill="#9EE6C9" />
        <circle cx="172" cy="139" r="7" fill="#B9A6FF" />
      </g>
      <!-- body + feet -->
      <ellipse cx="100" cy="174" rx="54" ry="40" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
      <ellipse cx="76" cy="210" rx="18" ry="9" fill="#C9B6FF" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
      <ellipse cx="124" cy="210" rx="18" ry="9" fill="#C9B6FF" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />

      <g class="u-head">
        <!-- mane -->
        <circle cx="56" cy="72" r="20" fill="#FF9EC7" />
        <circle cx="48" cy="100" r="17" fill="#FFD36E" />
        <circle cx="54" cy="127" r="14" fill="#9EE6C9" />
        <circle cx="144" cy="70" r="18" fill="#B9A6FF" />
        <circle cx="152" cy="96" r="14" fill="#FF9EC7" />
        <!-- ears -->
        <path d="M64 64 L58 30 L88 50 Z" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <path d="M136 64 L142 30 L112 50 Z" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <path d="M66 58 L63 40 L78 51 Z" fill="#FFC2DC" />
        <path d="M134 58 L137 40 L122 51 Z" fill="#FFC2DC" />
        <!-- face -->
        <ellipse cx="100" cy="98" rx="54" ry="50" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <circle cx="78" cy="54" r="15" fill="#FF9EC7" />
        <circle cx="92" cy="46" r="10" fill="#FFD36E" />
        <circle cx="123" cy="52" r="12" fill="#9EE6C9" />
        <!-- horn -->
        <path d="M90 52 L100 6 L110 52 Z" :fill="`url(#${gradientId})`" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <path d="M93 40 L107 34 M95 28 L105 24" stroke="#D8962A" stroke-width="2.5" stroke-linecap="round" />
        <!-- cheeks -->
        <circle cx="64" cy="114" r="9" fill="#FFB3D1" opacity=".75" />
        <circle cx="136" cy="114" r="9" fill="#FFB3D1" opacity=".75" />
        <!-- eyes: open / happy / sad -->
        <g class="e-open" :style="{ transform: `translate(${gaze.x.toFixed(1)}px, ${gaze.y.toFixed(1)}px)` }">
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
        <!-- muzzle + mouths (the talking mouth is an HTML overlay, see .mouth) -->
        <ellipse cx="100" cy="124" rx="30" ry="19" fill="#FFD9EA" stroke="#5A3D6E" stroke-width="2.5" />
        <ellipse cx="91" cy="119" rx="3" ry="2.2" fill="#5A3D6E" opacity=".55" />
        <ellipse cx="109" cy="119" rx="3" ry="2.2" fill="#5A3D6E" opacity=".55" />
        <path class="m-smile" d="M90 130 Q100 138 110 130" stroke="#5A3D6E" stroke-width="3" fill="none" stroke-linecap="round" />
        <path class="m-happy" d="M86 127 Q100 147 114 127 Z" fill="#8A2E4E" stroke="#5A3D6E" stroke-width="2.5" stroke-linejoin="round" />
        <path class="m-sad" d="M91 136 Q100 129 109 136" stroke="#5A3D6E" stroke-width="3" fill="none" stroke-linecap="round" />
        <!-- accessories (move with the head), one per worn slot -->
        <template v-for="w in wornArt" :key="w.slot">
          <image
            v-if="accessoryImage(w.art)"
            class="u-accessory"
            :href="accessoryImage(w.art)"
            :x="w.art.x - w.art.size / 2"
            :y="w.art.y - w.art.size / 2"
            :width="w.art.size"
            :height="w.art.size"
            :transform="`rotate(${w.art.rotate} ${w.art.x} ${w.art.y})`"
          />
          <text
            v-else
            class="u-accessory"
            :x="w.art.x"
            :y="w.art.y"
            :font-size="w.art.size"
            :transform="`rotate(${w.art.rotate} ${w.art.x} ${w.art.y})`"
            text-anchor="middle"
            dominant-baseline="central"
          >{{ w.art.char }}</text>
        </template>
      </g>

    </svg>

    <!-- composited overlays: arms (own layers so clapping runs on the GPU), mouth, sparkles -->
    <!-- arms are HTML layers (not <g> inside the drawing), so clapping never forces a layout -->
    <span class="arm arm--l" aria-hidden="true">
      <svg viewBox="60 144 24 51">
        <rect x="62" y="146" width="20" height="46" rx="10" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <rect x="62" y="180" width="20" height="13" rx="6" fill="#C9B6FF" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
      </svg>
    </span>
    <span class="arm arm--r" aria-hidden="true">
      <svg viewBox="116 144 24 51">
        <rect x="118" y="146" width="20" height="46" rx="10" fill="#FFF7FC" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
        <rect x="118" y="180" width="20" height="13" rx="6" fill="#C9B6FF" stroke="#5A3D6E" stroke-width="3" stroke-linejoin="round" />
      </svg>
    </span>
    <span class="mouth" aria-hidden="true" />
    <span
      v-for="(s, i) in SPARKS"
      :key="i"
      class="spark"
      :style="{ left: `${s.left}%`, top: `${s.top}%`, width: `${s.size}%`, animationDelay: `${s.delay}s` }"
      aria-hidden="true"
    >
      <svg viewBox="-8 -8 16 16"><path d="M0 -8 L2 -2 L8 0 L2 2 L0 8 L-2 2 L-8 0 L-2 -2 Z" fill="#FFD84D" /></svg>
    </span>
  </span>
</template>

<style scoped>
.csillam {
  position: relative;
  display: block;
  width: 100%;
  /* its own GPU layer: bobbing forever costs nothing on the main thread */
  will-change: transform;
  transform-origin: 50% 97%;
  animation: bob 3s ease-in-out infinite;
}
.uni {
  display: block;
  width: 100%;
  height: auto;
  overflow: visible;
}

/* her gaze following the finger glides instead of jumping */
.e-open {
  transition: transform 0.14s ease-out;
}

/* which face parts show per mood (discrete switches, no per-frame cost) */
.e-happy,
.e-sad,
.m-happy,
.m-sad {
  display: none;
}
.csillam.talking .m-smile {
  display: none;
}
.csillam.happy .e-open,
.csillam.happy .m-smile,
.csillam.sad .e-open,
.csillam.sad .m-smile {
  display: none;
}
.csillam.happy .e-happy,
.csillam.happy .m-happy,
.csillam.sad .e-sad,
.csillam.sad .m-sad {
  display: inline;
}

/* talking mouth: an HTML ellipse over the muzzle, scaled on the compositor */
.mouth {
  position: absolute;
  left: 50%;
  top: 59.5%;
  display: none;
  width: 6%;
  height: 4.5%;
  border-radius: 50%;
  background: #8a2e4e;
  transform: translate(-50%, -50%);
}
.csillam.talking:not(.happy):not(.sad) .mouth {
  display: block;
  animation: talk 0.2s ease-in-out infinite alternate;
}

/* sparkles (happy): HTML overlays, transform/opacity only */
.spark {
  position: absolute;
  display: none;
  aspect-ratio: 1;
  transform: translate(-50%, -50%);
}
.spark svg {
  display: block;
  width: 100%;
  height: 100%;
}
.csillam.happy .spark {
  display: block;
  animation: twinkle 0.6s ease-in-out infinite alternate;
}

/* whole-body moves on the wrapper (compositor) */
.csillam.happy {
  animation: jump 0.5s ease-out 3;
}
.csillam.hop {
  animation: jump 0.5s ease-out 1;
}
.csillam.party.happy {
  animation: jump 0.6s ease-out infinite;
}

/* arms: boxes match their viewBox in the 200×222 drawing; they rotate at the shoulder */
.arm {
  position: absolute;
  top: 64.86%;
  width: 12%;
  height: 22.97%;
  transform-origin: 50% 11.76%;
}
.arm svg {
  display: block;
  width: 100%;
  height: 100%;
  overflow: visible;
}
.arm--l {
  left: 30%;
}
.arm--r {
  left: 58%;
}
.csillam.happy .arm--l {
  animation: clapL 0.22s ease-in-out 8 alternate;
}
.csillam.happy .arm--r {
  animation: clapR 0.22s ease-in-out 8 alternate;
}

/* the only motion left inside the drawing: a short head droop when sad */
.u-head {
  transform-box: view-box;
  transform-origin: 100px 145px;
}
.csillam.sad .u-head {
  animation: droop 1.3s ease-in-out forwards;
}

@keyframes bob {
  50% {
    transform: translateY(-1.8%);
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
@keyframes clapL {
  to {
    transform: rotate(-50deg);
  }
}
@keyframes clapR {
  to {
    transform: rotate(50deg);
  }
}
@keyframes droop {
  25%,
  85% {
    transform: rotate(-9deg) translateY(5px);
  }
  100% {
    transform: none;
  }
}
@keyframes talk {
  from {
    transform: translate(-50%, -50%) scaleY(0.4);
  }
  to {
    transform: translate(-50%, -50%) scaleY(1.2);
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
  .csillam,
  .csillam * {
    animation: none !important;
  }
}
</style>
