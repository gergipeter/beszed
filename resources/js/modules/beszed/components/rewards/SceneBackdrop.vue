<script setup>
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * The backgrounds of the sticker picture (config rewards.backgrounds), drawn in
 * CSS with a few pictures, some of them moving: a meadow, the night sky, a
 * castle under a rainbow, the beach, under the sea, a snowy land, the enchanted
 * forest. `mini` is the still thumbnail in the picker. Nothing here can be
 * tapped; only transform/opacity move.
 */
defineProps({
  scene: { type: String, default: null },
  mini: { type: Boolean, default: false },
})

/** Pictures per scene: [picture, left %, top %, size (% of the width), how it moves] */
const PROPS = {
  meadow: [['🌳', 12, 58, 20], ['🌼', 80, 86, 8], ['🌷', 90, 80, 7], ['🦋', 70, 30, 7, 'flutter']],
  sky: [['🌙', 84, 18, 14], ['☁️', 20, 72, 16, 'drift']],
  castle: [['🏰', 50, 70, 38], ['☁️', 14, 20, 14, 'drift'], ['🎈', 82, 40, 8, 'float']],
  beach: [['🌴', 86, 58, 22], ['🐚', 18, 88, 8], ['⛵', 30, 50, 10, 'float']],
  underwater: [['🐠', 22, 36, 10, 'swim'], ['🐙', 82, 80, 12], ['🌿', 8, 84, 14], ['🌿', 94, 86, 12]],
  snow: [['⛄', 82, 72, 16], ['🌲', 12, 62, 20], ['🌲', 24, 70, 14]],
  forest: [['🌲', 6, 56, 24], ['🌳', 92, 58, 24], ['🍄', 26, 88, 8], ['🦉', 88, 26, 9]],
}
const BUBBLES = [8, 22, 38, 55, 67, 79, 91]
const FLAKES = Array.from({ length: 18 }, (_, i) => [(i * 57) % 100, (i * 31) % 100, 2 + (i % 3)])
const STARS = Array.from({ length: 16 }, (_, i) => [(i * 43 + 7) % 100, (i * 29 + 5) % 64, 1.5 + (i % 3) * 0.6])
</script>

<template>
  <div class="scene-art" :class="[`scene-art--${scene || 'blank'}`, { 'scene-art--mini': mini }]" aria-hidden="true">
    <span v-if="scene === 'meadow' || scene === 'beach'" class="sun" />
    <span v-if="scene === 'meadow'" class="hill hill--back" />
    <span v-if="scene === 'meadow'" class="hill hill--front" />
    <template v-if="scene === 'sky'">
      <i v-for="([x, y, s], i) in STARS" :key="i" class="star" :style="{ left: `${x}%`, top: `${y}%`, width: `${s}%`, '--i': i }" />
      <i class="shooting" />
    </template>
    <span v-if="scene === 'castle'" class="rainbow" />
    <template v-if="scene === 'beach'">
      <span class="sea" />
      <span class="sand" />
    </template>
    <template v-if="scene === 'underwater'">
      <span class="rays" />
      <span class="seabed" />
      <i v-for="(x, i) in BUBBLES" :key="i" class="bubble" :style="{ left: `${x}%`, '--i': i }" />
    </template>
    <template v-if="scene === 'snow'">
      <span class="drift drift--back" />
      <span class="drift drift--front" />
      <i v-for="([x, y, s], i) in FLAKES" :key="i" class="flake" :style="{ left: `${x}%`, top: `${y}%`, width: `${s}%`, '--i': i }" />
    </template>
    <span v-if="scene === 'forest'" class="treeline" />

    <EmojiArt
      v-for="([char, x, y, size, move], i) in PROPS[scene] ?? []"
      :key="`p${i}`"
      class="prop"
      :class="move && `prop--${move}`"
      :char="char"
      :style="{ left: `${x}%`, top: `${y}%`, '--size': size, '--i': i }"
    />
  </div>
</template>

<style scoped>
.scene-art {
  position: absolute;
  inset: 0;
  overflow: hidden;
  container-type: inline-size;
  pointer-events: none;
}
.scene-art--mini * {
  animation: none !important;
}
.prop {
  position: absolute;
  font-size: calc(var(--size) * 1cqi);
  line-height: 1;
  translate: -50% -50%;
}

/* --- the scenes --- */
.scene-art--blank {
  background: repeating-linear-gradient(45deg, var(--bz-soft) 0 10px, var(--bz-card) 10px 20px);
}
.scene-art--meadow {
  background: linear-gradient(to bottom, #9fd8ff, #dff4ff 55%);
}
.sun {
  position: absolute;
  top: 8%;
  right: 8%;
  width: 14%;
  aspect-ratio: 1;
  border-radius: 50%;
  background: radial-gradient(circle at 40% 38%, #fff6b8, #ffd84d 60%, #ffb627);
  box-shadow: 0 0 6cqi 2cqi rgba(255, 216, 77, 0.45);
}
.hill {
  position: absolute;
  border-radius: 50%;
}
.hill--back {
  left: -20%;
  right: 30%;
  bottom: -30%;
  height: 70%;
  background: #9ad97a;
}
.hill--front {
  left: 20%;
  right: -30%;
  bottom: -38%;
  height: 72%;
  background:
    radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.7) 0 0.5cqi, transparent 0.7cqi) 0 0 / 7cqi 6cqi,
    radial-gradient(circle at 60% 60%, rgba(255, 220, 90, 0.8) 0 0.5cqi, transparent 0.7cqi) 0 0 / 9cqi 7cqi,
    #7cc462;
}
.scene-art--sky {
  background: linear-gradient(to bottom, #1b1f4d, #4b3f80);
}
.star {
  position: absolute;
  aspect-ratio: 1;
  border-radius: 50%;
  background: #fff;
  animation: twinkle 2.4s ease-in-out infinite alternate;
  animation-delay: calc(var(--i) * -0.37s);
}
.shooting {
  position: absolute;
  top: 16%;
  left: -20%;
  width: 18%;
  height: 0.6cqi;
  border-radius: 1cqi;
  background: linear-gradient(90deg, transparent, #fff);
  rotate: 18deg;
  animation: shoot 7s ease-in infinite;
}
.scene-art--castle {
  background: linear-gradient(to bottom, #ffd9ea, #e6dcff 70%, #c9e9b8 70%);
}
.rainbow {
  position: absolute;
  left: 8%;
  right: 8%;
  bottom: 22%;
  aspect-ratio: 2 / 1;
  border-radius: 50% 50% 0 0 / 100% 100% 0 0;
  background: radial-gradient(
    circle at 50% 100%,
    transparent 44%,
    #b9a6ff 44% 49%,
    #7cc7ff 49% 54%,
    #9ee6c9 54% 59%,
    #ffe27a 59% 64%,
    #ffb38a 64% 69%,
    #ff8fa8 69% 74%,
    transparent 74%
  );
  opacity: 0.85;
}
.scene-art--beach {
  background: linear-gradient(to bottom, #8fd3ff, #d9f2ff 45%);
}
.sea {
  position: absolute;
  left: 0;
  right: 0;
  top: 48%;
  height: 26%;
  background:
    radial-gradient(circle at 50% 0, rgba(255, 255, 255, 0.6) 0 1.5cqi, transparent 1.7cqi) 0 0 / 8cqi 4cqi repeat-x,
    linear-gradient(to bottom, #3fa9e0, #7fd0f0);
}
.sand {
  position: absolute;
  left: -10%;
  right: -10%;
  bottom: -10%;
  height: 40%;
  border-radius: 50% 50% 0 0;
  background: #f6dfa6;
}
.scene-art--underwater {
  background: linear-gradient(to bottom, #6fd0f0, #2a86c2 60%, #1b5f96);
}
.rays {
  position: absolute;
  inset: -20% 0 30%;
  background: repeating-conic-gradient(from 170deg at 50% -10%, rgba(255, 255, 255, 0.14) 0 4deg, transparent 4deg 12deg);
}
.seabed {
  position: absolute;
  left: -10%;
  right: -10%;
  bottom: -14%;
  height: 30%;
  border-radius: 50% 50% 0 0;
  background: #e8cf95;
}
.bubble {
  position: absolute;
  bottom: -6%;
  width: 3cqi;
  aspect-ratio: 1;
  border: 0.4cqi solid rgba(255, 255, 255, 0.8);
  border-radius: 50%;
  animation: rise 6s ease-in infinite;
  animation-delay: calc(var(--i) * -0.9s);
}
.scene-art--snow {
  background: linear-gradient(to bottom, #c9e6ff, #eef7ff 60%);
}
.drift {
  position: absolute;
  border-radius: 50%;
  background: #fff;
}
.drift--back {
  left: -20%;
  right: 20%;
  bottom: -30%;
  height: 62%;
  background: #eef5fb;
}
.drift--front {
  left: 30%;
  right: -30%;
  bottom: -36%;
  height: 64%;
}
.flake {
  position: absolute;
  top: 0;
  aspect-ratio: 1;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 0 0.5cqi rgba(120, 160, 200, 0.6);
  animation: fall 7s linear infinite;
  animation-delay: calc(var(--i) * -0.41s);
}
.scene-art--forest {
  background: linear-gradient(to bottom, #d6f1c9, #8fcf8a 70%, #5fae78);
}
.treeline {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 28%;
  height: 26%;
  background: radial-gradient(circle at 50% 100%, #4f9e6a 60%, transparent 61%) 0 100% / 14cqi 100% repeat-x;
  opacity: 0.8;
}

/* --- moving pictures --- */
.prop--flutter {
  animation: flutter 6s ease-in-out infinite alternate;
}
.prop--drift {
  animation: drift 24s linear infinite alternate;
}
.prop--float {
  animation: float 3s ease-in-out infinite alternate;
}
.prop--swim {
  animation: swim 10s ease-in-out infinite alternate;
}
@keyframes twinkle {
  from {
    opacity: 0.3;
    transform: scale(0.7);
  }
}
@keyframes shoot {
  0%,
  80% {
    transform: translateX(0);
    opacity: 0;
  }
  84% {
    opacity: 1;
  }
  100% {
    transform: translateX(700%);
    opacity: 0;
  }
}
@keyframes rise {
  to {
    transform: translate(3cqi, -110cqi) scale(1.4);
    opacity: 0;
  }
}
@keyframes fall {
  from {
    transform: translate(0, -10cqi);
  }
  to {
    transform: translate(4cqi, 90cqi);
  }
}
@keyframes flutter {
  50% {
    transform: translate(-12cqi, 6cqi) rotate(-10deg);
  }
  to {
    transform: translate(-24cqi, -4cqi) rotate(8deg);
  }
}
@keyframes drift {
  to {
    transform: translateX(30cqi);
  }
}
@keyframes float {
  to {
    transform: translateY(-3cqi) rotate(4deg);
  }
}
@keyframes swim {
  to {
    transform: translateX(40cqi) scaleX(-1);
  }
}
</style>
