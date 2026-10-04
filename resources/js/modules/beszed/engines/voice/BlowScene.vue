<script setup>
import { computed } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'

/**
 * Fújóka's pictures, drawn with CSS and SVG (plus an emoji or two): the cake's candles, the dandelion,
 * the sailboat on the pond, the pinwheel, the soap bubbles, the feather. Everything the child does is
 * in the props; the scene only shows it. Sizes are in cqw (the scene's width), so it scales on any screen.
 */
const props = defineProps({
  scene: { type: String, required: true },
  kind: { type: String, required: true },
  /** puffs: how many candles / bubbles / tufts; alternate: how many steps */
  count: { type: Number, default: 0 },
  /** puffs blown so far */
  puffs: { type: Number, default: 0 },
  /** 0–1 through the round */
  progress: { type: Number, default: 0 },
  /** 0–1 live loudness */
  level: { type: Number, default: 0 },
  /** 0 silent · 1 a (soft) blow · 2 strong */
  strength: { type: Number, default: 0 },
  /** the pinwheel's angle, degrees */
  angle: { type: Number, default: 0 },
  /** goes up each time a gentle blow was too strong (the bubble pops, the feather flies off) */
  oops: { type: Number, default: 0 },
  finished: { type: Boolean, default: false },
  friend: { type: String, default: '' },
  label: { type: String, default: '' },
})

const SEEDS = 24
/** a fixed shuffle, so long-blow seeds leave from all around the head */
const SEED_ORDER = [5, 17, 11, 0, 20, 8, 14, 2, 22, 9, 15, 3, 19, 6, 12, 23, 1, 10, 16, 4, 21, 7, 13, 18]

const seeds = computed(() =>
  Array.from({ length: SEEDS }, (_, i) => {
    const gone =
      props.kind === 'puffs' ? i % Math.max(1, props.count) < props.puffs : SEED_ORDER.indexOf(i) < Math.floor(props.progress * SEEDS)
    return { i, angle: (360 / SEEDS) * i, gone, drift: ((i * 37) % 11) - 5 }
  }),
)

const candles = computed(() => Array.from({ length: props.count }, (_, i) => ({ i, out: i < props.puffs })))

/** where each released bubble floats to (cqw from the left and the top; the scene is 100 × 75) */
const BUBBLE_SPOTS = [
  [62, 12],
  [42, 20],
  [80, 28],
  [55, 34],
  [86, 9],
]
const bubbles = computed(() =>
  Array.from({ length: props.count }, (_, i) => ({ i, up: i < props.puffs, spot: BUBBLE_SPOTS[i % BUBBLE_SPOTS.length], size: 14 - (i % 3) * 2 })),
)

const lean = computed(() => (props.strength ? -10 - props.level * 30 : 0))
const boatX = computed(() => 4 + props.progress * 60)
const featherLift = computed(() => {
  if (props.finished) return 1
  if (props.strength === 1) return 0.55 + props.progress * 0.45
  return props.progress * 0.35
})
const bubbleScale = computed(() => 0.2 + props.progress * 0.8)
</script>

<template>
  <div class="scene" :class="[`scene--${scene}`, { 'scene--done': finished }]" role="img" :aria-label="label">
    <!-- birthday cake: one candle goes out per puff -->
    <template v-if="scene === 'candles'">
      <div class="table" />
      <div class="cake">
        <div class="plate" />
        <div class="sponge"><span class="frosting" /><span class="dots" /></div>
        <div class="candles">
          <div v-for="c in candles" :key="c.i" class="candle" :class="{ 'candle--out': c.out }">
            <span class="flame" :style="{ transform: `rotate(${c.out ? 0 : lean}deg)` }"><span class="flame-core" /></span>
            <span class="smoke" />
          </div>
        </div>
      </div>
    </template>

    <!-- dandelion: its seeds fly off -->
    <template v-else-if="scene === 'dandelion'">
      <div class="meadow" />
      <div class="stem" />
      <div class="head">
        <span
          v-for="s in seeds"
          :key="s.i"
          class="seed"
          :class="{ 'seed--gone': s.gone }"
          :style="{ '--a': `${s.angle}deg`, '--drift': s.drift }"
        ><span class="fluff" /></span>
        <span class="core" />
      </div>
    </template>

    <!-- sailboat on the pond: it sails to the far shore -->
    <template v-else-if="scene === 'boat'">
      <div class="water"><span class="wave w1" /><span class="wave w2" /></div>
      <div class="shore" />
      <div class="boat" :style="{ transform: `translateX(${boatX}cqw)` }">
        <span class="wind" :style="{ opacity: strength ? 0.35 + level * 0.65 : 0 }"><i /><i /><i /></span>
        <EmojiArt class="boat-art" char="⛵" />
      </div>
    </template>

    <!-- pinwheel: it spins with the blow; the ring fills -->
    <template v-else-if="scene === 'pinwheel'">
      <div class="meadow" />
      <div class="pole" />
      <svg class="pinwheel" viewBox="-50 -50 100 100" aria-hidden="true">
        <circle class="pin-progress-track" r="46" />
        <circle v-if="progress > 0.01" class="pin-progress" r="46" :stroke-dasharray="`${(289 * progress).toFixed(1)} 289`" transform="rotate(-90)" />
        <g :transform="`rotate(${angle.toFixed(1)})`">
          <path d="M0 0 L0 -38 L22 -18 Z" fill="#ff6f61" />
          <path d="M0 0 L38 0 L18 22 Z" fill="#ffd84d" />
          <path d="M0 0 L0 38 L-22 18 Z" fill="#4fb3ff" />
          <path d="M0 0 L-38 0 L-18 -22 Z" fill="#5bd08a" />
          <circle r="5" fill="#fff" stroke="#3b1f4a" stroke-width="1.5" />
        </g>
      </svg>
    </template>

    <!-- soap bubbles: a puff sends one up; a gentle blow grows one big bubble -->
    <template v-else-if="scene === 'bubbles'">
      <div class="meadow meadow--low" />
      <div class="wand"><span class="wand-ring" /><span class="wand-stick" /></div>
      <template v-if="kind === 'puffs'">
        <span
          v-for="b in bubbles"
          :key="b.i"
          class="bubble"
          :class="{ 'bubble--up': b.up }"
          :style="{ '--x': b.spot[0], '--y': b.spot[1], '--s': `${b.size}cqw` }"
        />
      </template>
      <span
        v-else
        :key="`big-${oops}`"
        class="bubble bubble--big"
        :class="{ 'bubble--away': finished, 'bubble--fresh': oops > 0 }"
        :style="{ '--grow': bubbleScale }"
      />
    </template>

    <!-- feather: floats on a gentle blow, flies off on a strong one -->
    <template v-else-if="scene === 'feather'">
      <span class="cloud c1" /><span class="cloud c2" />
      <div class="meadow meadow--low" />
      <div :key="`f-${oops}`" class="feather" :class="{ 'feather--blown': oops > 0 && !finished }">
        <EmojiArt class="feather-art" char="🪶" :style="{ transform: `translateY(${(1 - featherLift) * 34}cqw) rotate(${strength ? -18 + level * 10 : 12}deg)` }" />
      </div>
    </template>

    <EmojiArt v-if="friend" class="friend" :char="friend" />
  </div>
</template>

<style scoped>
.scene {
  position: relative;
  width: 100%;
  /* a short screen (a tablet held sideways) keeps the buttons below in view */
  max-width: min(460px, 55vh);
  margin: 0 auto;
  aspect-ratio: 4 / 3;
  border-radius: var(--bz-radius-lg);
  overflow: hidden;
  container-type: inline-size;
  background: linear-gradient(#bfe6ff, #eaf7ff);
  box-shadow: var(--bz-shadow);
}
.friend {
  position: absolute;
  left: 4cqw;
  bottom: 5cqw;
  font-size: 13cqw;
  line-height: 1;
}
.meadow {
  position: absolute;
  left: -10%;
  right: -10%;
  bottom: -18cqw;
  height: 38cqw;
  border-radius: 50%;
  background: linear-gradient(#a9df86, #7cc462);
}
.meadow--low {
  bottom: -26cqw;
}

/* ---------- candles ---------- */
.scene--candles {
  background: linear-gradient(#fff1d6, #ffd9c2);
}
.table {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 17cqw;
  background: linear-gradient(#c98a5a, #a8693f);
}
.cake {
  position: absolute;
  left: 22cqw;
  width: 56cqw;
  bottom: 9cqw;
  height: 46cqw;
}
.plate {
  position: absolute;
  left: -5cqw;
  right: -5cqw;
  bottom: 0;
  height: 5cqw;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 0.8cqw 0 rgba(59, 31, 74, 0.15);
}
.sponge {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 2.5cqw;
  height: 21cqw;
  border-radius: 3cqw 3cqw 2cqw 2cqw;
  background: linear-gradient(#ff9fb8, #f07c9c);
}
.frosting {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 7cqw;
  border-radius: 3cqw 3cqw 0 0;
  background:
    radial-gradient(circle at 50% 0, #fff 2.6cqw, transparent 2.7cqw) 0 3cqw / 7cqw 5cqw repeat-x,
    linear-gradient(#fff, #fff) 0 0 / 100% 3.6cqw no-repeat;
}
.dots {
  position: absolute;
  left: 4cqw;
  right: 4cqw;
  bottom: 4cqw;
  height: 3cqw;
  background: radial-gradient(circle, #ffd84d 0.9cqw, transparent 1cqw) 0 0 / 8cqw 3cqw repeat-x;
}
.candles {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 23cqw;
  display: flex;
  justify-content: space-evenly;
  align-items: flex-end;
}
.candle {
  position: relative;
  width: 4cqw;
  height: 15cqw;
  border-radius: 1cqw 1cqw 0 0;
  background: repeating-linear-gradient(-35deg, #fff 0 2cqw, #6ec3ff 2cqw 4cqw);
  box-shadow: inset -0.6cqw 0 0 rgba(59, 31, 74, 0.12);
}
.candle::before {
  content: '';
  position: absolute;
  left: 50%;
  top: -2cqw;
  width: 0.5cqw;
  height: 2cqw;
  margin-left: -0.25cqw;
  background: #3b1f4a;
}
.flame {
  position: absolute;
  left: 50%;
  bottom: 100%;
  width: 4.6cqw;
  height: 8cqw;
  margin: 0 0 1.4cqw -2.3cqw;
  border-radius: 50% 50% 50% 50% / 62% 62% 38% 38%;
  background: radial-gradient(circle at 50% 70%, #fff6a8, #ffb020 55%, #ff7a1a);
  transform-origin: 50% 100%;
  box-shadow: 0 0 4cqw 1cqw rgba(255, 200, 80, 0.55);
  transition:
    transform 0.12s,
    opacity 0.25s;
  animation: flicker 0.9s ease-in-out infinite alternate;
}
.flame-core {
  position: absolute;
  left: 30%;
  right: 30%;
  bottom: 12%;
  height: 40%;
  border-radius: 50%;
  background: #fffbe0;
}
.candle--out .flame {
  opacity: 0;
  animation: none;
}
.smoke {
  position: absolute;
  left: 50%;
  bottom: 100%;
  width: 5cqw;
  height: 5cqw;
  margin-left: -2.5cqw;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(150, 150, 160, 0.7), rgba(150, 150, 160, 0));
  opacity: 0;
}
.candle--out .smoke {
  animation: smoke 1.6s ease-out forwards;
}
@keyframes flicker {
  to {
    scale: 0.92 1.06;
  }
}
@keyframes smoke {
  0% {
    opacity: 0.9;
    transform: translateY(0) scale(0.6);
  }
  100% {
    opacity: 0;
    transform: translateY(-16cqw) scale(1.8);
  }
}

/* ---------- dandelion ---------- */
.stem {
  position: absolute;
  left: 50%;
  bottom: 0;
  width: 1.4cqw;
  height: 46cqw;
  margin-left: -0.7cqw;
  border-radius: 1cqw;
  background: #4f9a3a;
}
.head {
  position: absolute;
  left: 50%;
  top: 30cqw;
  width: 0;
  height: 0;
}
.core {
  position: absolute;
  left: -3.5cqw;
  top: -3.5cqw;
  width: 7cqw;
  height: 7cqw;
  border-radius: 50%;
  background: radial-gradient(circle, #c9b98a, #9c8a5a);
}
.seed {
  position: absolute;
  left: -0.15cqw;
  top: 0;
  width: 0.3cqw;
  height: 13cqw;
  background: rgba(255, 255, 255, 0.9);
  transform-origin: 50% 0;
  transform: rotate(calc(var(--a) + 180deg));
  transition:
    transform 1.8s ease-out,
    opacity 1.8s ease-in;
}
.fluff {
  position: absolute;
  left: -2.6cqw;
  bottom: -2.6cqw;
  width: 5.5cqw;
  height: 5.5cqw;
  border-radius: 50%;
  background:
    repeating-conic-gradient(rgba(255, 255, 255, 0.95) 0 6deg, transparent 6deg 30deg),
    radial-gradient(circle, rgba(255, 255, 255, 0.9) 0.6cqw, transparent 0.8cqw);
  filter: drop-shadow(0 0 0.3cqw rgba(59, 31, 74, 0.35));
}
.seed--gone {
  opacity: 0;
  transform: translate(calc(45cqw + var(--drift) * 3cqw), calc(-30cqw + var(--drift) * 4cqw)) rotate(calc(var(--a) + 260deg));
}

/* ---------- boat ---------- */
.scene--boat {
  background: linear-gradient(#9fd7ff 0 44%, #4aa8e8 44% 100%);
}
.water {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 56%;
  background: linear-gradient(#5db7f0, #2f8fd6);
}
.wave {
  position: absolute;
  left: -10%;
  right: -10%;
  height: 3cqw;
  background: radial-gradient(ellipse at 50% 0, transparent 2cqw, rgba(255, 255, 255, 0.55) 2.1cqw 2.6cqw, transparent 2.7cqw) 0 0 / 8cqw 3cqw repeat-x;
  animation: waves 3s linear infinite;
}
.w1 {
  top: 18%;
}
.w2 {
  top: 55%;
  animation-duration: 4.2s;
  opacity: 0.7;
}
@keyframes waves {
  to {
    transform: translateX(8cqw);
  }
}
.shore {
  position: absolute;
  right: -14cqw;
  bottom: -6cqw;
  width: 36cqw;
  height: 46cqw;
  border-radius: 50% 0 0 0;
  background: linear-gradient(#f4dca8 0 18%, #a9df86 18%);
}
.scene--boat .friend {
  left: auto;
  right: 5cqw;
  bottom: 20cqw;
}
.boat {
  position: absolute;
  left: 0;
  bottom: 26cqw;
  transition: transform 0.15s linear;
}
.boat-art {
  display: block;
  font-size: 22cqw;
  line-height: 1;
  animation: bob 2.4s ease-in-out infinite;
}
@keyframes bob {
  50% {
    transform: translateY(-1.2cqw) rotate(-3deg);
  }
}
.wind {
  position: absolute;
  right: 100%;
  top: 4cqw;
  display: grid;
  gap: 2.2cqw;
  transition: opacity 0.15s;
}
.wind i {
  display: block;
  width: 13cqw;
  height: 1.1cqw;
  border-radius: 1cqw;
  background: rgba(255, 255, 255, 0.9);
}
.wind i:nth-child(2) {
  margin-left: 4cqw;
  width: 9cqw;
}

/* ---------- pinwheel ---------- */
.pole {
  position: absolute;
  left: 50%;
  bottom: 0;
  width: 2cqw;
  height: 40cqw;
  margin-left: -1cqw;
  border-radius: 1cqw;
  background: #8a5a3c;
}
.pinwheel {
  position: absolute;
  left: 50%;
  top: 4cqw;
  width: 52cqw;
  height: 52cqw;
  margin-left: -26cqw;
  overflow: visible;
}
.pin-progress-track {
  fill: rgba(255, 255, 255, 0.45);
  stroke: rgba(255, 255, 255, 0.7);
  stroke-width: 5;
}
.pin-progress {
  fill: none;
  stroke: var(--bz-leaf);
  stroke-width: 5;
  stroke-linecap: round;
}

/* ---------- bubbles ---------- */
.scene--bubbles {
  background: linear-gradient(#c9ecff, #f3fbff);
}
.wand {
  position: absolute;
  left: 14cqw;
  bottom: 8cqw;
  width: 14cqw;
  height: 34cqw;
}
.wand-ring {
  position: absolute;
  left: 0;
  top: 0;
  width: 14cqw;
  height: 14cqw;
  border: 1.6cqw solid #c06bd8;
  border-radius: 50%;
}
.wand-stick {
  position: absolute;
  left: 6.2cqw;
  top: 13cqw;
  width: 1.6cqw;
  height: 21cqw;
  border-radius: 1cqw;
  background: #c06bd8;
}
.bubble {
  /* released: at its spot; before that, shrunk inside the wand's ring (21, 40) */
  --x: 21;
  --y: 40;
  position: absolute;
  left: calc(var(--x) * 1cqw);
  top: calc(var(--y) * 1cqw);
  width: var(--s, 12cqw);
  height: var(--s, 12cqw);
  margin: calc(var(--s, 12cqw) / -2) 0 0 calc(var(--s, 12cqw) / -2);
  border-radius: 50%;
  background:
    radial-gradient(circle at 32% 30%, rgba(255, 255, 255, 0.95) 0 9%, transparent 11%),
    radial-gradient(circle, rgba(255, 255, 255, 0.05) 55%, rgba(170, 120, 255, 0.45) 70%, rgba(80, 200, 255, 0.55) 85%, rgba(255, 170, 220, 0.6) 100%);
  opacity: 0;
  transform: translate(calc((21 - var(--x)) * 1cqw), calc((40 - var(--y)) * 1cqw)) scale(0.2);
  transition:
    transform 1.4s ease-out,
    opacity 0.3s;
}
.bubble--up {
  opacity: 1;
  transform: none;
  animation: float 3s ease-in-out 1.4s infinite;
}
@keyframes float {
  50% {
    translate: 0 -1.5cqw;
  }
}
.bubble--big {
  --s: 34cqw;
  opacity: 1;
  transform: scale(var(--grow));
  transition:
    transform 0.2s,
    opacity 1.6s;
}
.bubble--fresh {
  animation: pop-in 0.5s;
}
@keyframes pop-in {
  0% {
    opacity: 0;
  }
}
.bubble--away {
  transform: translateY(-60cqw) scale(1);
  opacity: 0.6;
  transition:
    transform 1.6s ease-in,
    opacity 1.6s;
}

/* ---------- feather ---------- */
.scene--feather {
  background: linear-gradient(#9fd7ff, #e2f4ff);
}
.cloud {
  position: absolute;
  width: 26cqw;
  height: 9cqw;
  border-radius: 9cqw;
  background: #fff;
  opacity: 0.85;
}
.cloud::before {
  content: '';
  position: absolute;
  left: 5cqw;
  top: -5cqw;
  width: 12cqw;
  height: 12cqw;
  border-radius: 50%;
  background: inherit;
}
.c1 {
  left: 8cqw;
  top: 8cqw;
}
.c2 {
  right: 6cqw;
  top: 20cqw;
  transform: scale(0.75);
}
.feather {
  position: absolute;
  left: 50%;
  top: 14cqw;
  margin-left: -9cqw;
  animation: sway 2.6s ease-in-out infinite;
}
.feather-art {
  display: block;
  font-size: 18cqw;
  line-height: 1;
  transition: transform 0.35s ease-out;
}
.feather--blown {
  animation: blown-in 1.2s ease-out;
}
@keyframes sway {
  50% {
    translate: 3cqw 0;
    rotate: 6deg;
  }
}
@keyframes blown-in {
  0% {
    translate: 20cqw -60cqw;
    rotate: 140deg;
  }
}
.scene--feather .friend {
  left: auto;
  right: 6cqw;
}

/* the end: a happy wobble of the whole picture */
.scene--done {
  animation: cheer 0.7s var(--bz-spring);
}
@keyframes cheer {
  40% {
    transform: scale(1.03);
  }
}
@media (prefers-reduced-motion: reduce) {
  .flame,
  .wave,
  .boat-art,
  .bubble--up,
  .feather,
  .scene--done {
    animation: none;
  }
}
</style>
