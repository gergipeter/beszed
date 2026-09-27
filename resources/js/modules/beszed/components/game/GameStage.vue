<script setup>
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * The scene a game is played in (GameMeta.stage): a beehive for Zümi, a
 * theatre for the choir, a magician's night for "Mi tűnt el?"… Drawn with CSS
 * and a few pictures around the edges, behind the round; nothing in it can be
 * tapped, and only transform/opacity move. Unknown stages show the meadow.
 */
defineProps({
  stage: { type: String, default: 'meadow' },
})

/** A few pictures per scene, placed around the edges (% of the scene), some of them moving. */
const PROPS = {
  meadow: [
    { char: '🌼', x: 4, y: 88, size: 34 },
    { char: '🌷', x: 93, y: 90, size: 30 },
    { char: '🦋', x: 88, y: 12, size: 30, move: 'flutter' },
  ],
  hive: [
    { char: '🐝', x: 8, y: 14, size: 30, move: 'buzz' },
    { char: '🍯', x: 92, y: 88, size: 34 },
  ],
  theatre: [{ char: '🎭', x: 50, y: 5, size: 26 }],
  magic: [
    { char: '🌙', x: 90, y: 10, size: 32 },
    { char: '✨', x: 8, y: 20, size: 24, move: 'twinkle' },
    { char: '✨', x: 86, y: 78, size: 20, move: 'twinkle' },
  ],
  workshop: [
    { char: '🔨', x: 6, y: 90, size: 28 },
    { char: '🪚', x: 94, y: 88, size: 28 },
  ],
  pond: [
    { char: '🪷', x: 6, y: 86, size: 34 },
    { char: '🐸', x: 93, y: 84, size: 32, move: 'hop' },
    { char: '🐟', x: 10, y: 18, size: 24, move: 'swim' },
  ],
  forest: [
    { char: '🌲', x: 3, y: 70, size: 64 },
    { char: '🌳', x: 97, y: 76, size: 60 },
    { char: '🦔', x: 12, y: 92, size: 28, move: 'hop' },
  ],
  market: [
    { char: '🍎', x: 6, y: 90, size: 28 },
    { char: '🥕', x: 94, y: 90, size: 28 },
  ],
  storybook: [{ char: '🔖', x: 92, y: 3, size: 30 }],
}
</script>

<template>
  <div class="scene" :class="`scene--${stage}`">
    <div class="backdrop" aria-hidden="true">
      <template v-if="stage === 'theatre'">
        <span class="curtain curtain--left" />
        <span class="curtain curtain--right" />
        <span class="valance" />
      </template>
      <span v-if="stage === 'pond'" class="ripple" />
      <EmojiArt
        v-for="(p, i) in PROPS[stage] ?? PROPS.meadow"
        :key="i"
        class="prop"
        :class="p.move && `prop--${p.move}`"
        :char="p.char"
        :style="{ left: `${p.x}%`, top: `${p.y}%`, fontSize: `${p.size}px`, '--i': i }"
      />
    </div>
    <div class="content"><slot /></div>
  </div>
</template>

<style scoped>
.scene {
  position: relative;
  isolation: isolate;
  min-height: 360px;
  padding: 26px clamp(10px, 3vw, 22px) 34px;
  overflow: hidden;
  border-radius: 34px;
  background: linear-gradient(to bottom, #e8f7ff, #d9f2c8);
  box-shadow: inset 0 0 0 4px rgba(255, 255, 255, 0.5);
}
.backdrop {
  position: absolute;
  inset: 0;
  z-index: -1;
  pointer-events: none;
}
/* a plain block: the round (SessionRunner .round) spans the whole stage and centres its own parts */
.content {
  position: relative;
}
.prop {
  position: absolute;
  line-height: 1;
  /* centred on its spot with translate, so the animations below only move it from there */
  translate: -50% -50%;
  opacity: 0.95;
}

/* --- the scenes --- */
/* meadow: sky over rolling grass */
.scene--meadow {
  background:
    radial-gradient(90% 38% at 20% 104%, var(--bz-grass-deep) 60%, transparent 61%),
    radial-gradient(80% 34% at 85% 106%, var(--bz-grass) 60%, transparent 61%),
    linear-gradient(to bottom, color-mix(in srgb, var(--bz-sky-top) 35%, #fff), color-mix(in srgb, var(--bz-sky-bottom) 70%, #fff));
}
/* beehive: warm honeycomb */
.scene--hive {
  background:
    radial-gradient(circle at 50% 0, rgba(255, 255, 255, 0.55), transparent 60%),
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='97' viewBox='0 0 56 97'%3E%3Cpath d='M28 0 56 16v32L28 64 0 48V16zM28 64l28 16v32M28 64 0 80v32' fill='none' stroke='%23e9a92a' stroke-opacity='.45' stroke-width='3'/%3E%3C/svg%3E") 0 0 / 42px 73px,
    linear-gradient(to bottom, #fff2b8, #ffd66b);
}
/* theatre: curtains, a spotlight and a wooden stage */
.scene--theatre {
  padding-top: 44px;
  background:
    radial-gradient(60% 55% at 50% 42%, rgba(255, 244, 200, 0.55), transparent 70%),
    linear-gradient(to bottom, #5b2344 0, #3d1830 70%, #8a5a3c 70%, #6f462d 100%);
}
.curtain {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 18%;
  background: repeating-linear-gradient(90deg, #c2273d 0 14px, #9e1830 14px 22px, #d6394e 22px 30px);
  box-shadow: inset -8px 0 16px rgba(0, 0, 0, 0.25);
  /* the curtains open when the game starts */
  animation: open 1.1s cubic-bezier(0.5, 0, 0.2, 1) both;
}
.curtain--left {
  left: 0;
  border-radius: 0 0 60% 0;
  transform-origin: left;
}
.curtain--right {
  right: 0;
  border-radius: 0 0 0 60%;
  transform-origin: right;
}
.valance {
  position: absolute;
  inset: 0 0 auto;
  height: 30px;
  background: radial-gradient(circle at 50% 0, #c2273d 70%, transparent 71%) 0 0 / 44px 44px repeat-x;
}
/* magic: a starry violet night */
.scene--magic {
  background:
    radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.9) 0 1.5px, transparent 2.5px) 0 0 / 53px 61px,
    radial-gradient(circle at 70% 60%, rgba(255, 240, 180, 0.8) 0 1.2px, transparent 2px) 0 0 / 71px 47px,
    radial-gradient(70% 60% at 50% 40%, #5a3d9e, #2b1d5c);
}
/* the dark scenes: the engines' status lines in light text */
.scene--magic :deep(.count),
.scene--magic :deep(.hint),
.scene--magic :deep(.status),
.scene--theatre :deep(.count),
.scene--theatre :deep(.hint),
.scene--theatre :deep(.status) {
  color: #fbeeff;
}
/* workshop: warm wooden planks */
.scene--workshop {
  background:
    linear-gradient(to bottom, rgba(255, 255, 255, 0.35), transparent 40%),
    repeating-linear-gradient(to bottom, #e6b989 0 36px, #d9a877 36px 38px),
    #e6b989;
}
/* pond: water with lily pads */
.scene--pond {
  background:
    radial-gradient(40% 18% at 80% 20%, rgba(255, 255, 255, 0.35), transparent 70%),
    linear-gradient(to bottom, #b9ecff, #6cc6e8);
}
.ripple {
  position: absolute;
  left: 22%;
  top: 70%;
  width: 90px;
  height: 36px;
  border: 3px solid rgba(255, 255, 255, 0.7);
  border-radius: 50%;
  animation: ripple 3.2s ease-out infinite;
}
/* forest: green shade with dappled light */
.scene--forest {
  background:
    radial-gradient(circle at 30% 20%, rgba(255, 255, 210, 0.4) 0 18px, transparent 20px) 0 0 / 130px 110px,
    linear-gradient(to bottom, #cfeec4, #8fcf8a);
}
/* market: a striped awning over the stall */
.scene--market {
  padding-top: 52px;
  background:
    radial-gradient(circle at 50% 0, #ff6f61 70%, transparent 71%) 0 30px / 40px 24px repeat-x,
    repeating-linear-gradient(90deg, #ff6f61 0 40px, #fff4ee 40px 80px) 0 0 / 100% 36px no-repeat,
    linear-gradient(to bottom, #fff9ef, #ffe7cf);
}
/* storybook: a cream page with faint lines */
.scene--storybook {
  background:
    linear-gradient(90deg, rgba(160, 110, 70, 0.18), transparent 7%, transparent 93%, rgba(160, 110, 70, 0.18)),
    repeating-linear-gradient(to bottom, transparent 0 34px, rgba(120, 150, 200, 0.18) 34px 36px),
    #fffaf0;
}

/* --- props that move --- */
.prop--flutter {
  animation: flutter 5s ease-in-out infinite alternate;
}
.prop--buzz {
  animation: buzz 7s ease-in-out infinite alternate;
}
.prop--twinkle {
  animation: twinkle 1.8s ease-in-out infinite alternate;
  animation-delay: calc(var(--i) * -0.6s);
}
.prop--hop {
  animation: hop 2.6s ease-in-out infinite;
}
.prop--swim {
  animation: swim 9s ease-in-out infinite alternate;
}
@keyframes open {
  from {
    transform: scaleX(2.8);
  }
}
@keyframes ripple {
  from {
    transform: scale(0.4);
    opacity: 1;
  }
  to {
    transform: scale(1.8);
    opacity: 0;
  }
}
@keyframes flutter {
  50% {
    transform: translate(-160%, 40%) rotate(-12deg);
  }
  to {
    transform: translate(-260%, -20%) rotate(8deg);
  }
}
@keyframes buzz {
  33% {
    transform: translate(120%, 30%) rotate(10deg);
  }
  66% {
    transform: translate(240%, -20%) rotate(-8deg);
  }
  to {
    transform: translate(380%, 20%);
  }
}
@keyframes twinkle {
  to {
    transform: scale(1.4) rotate(20deg);
    opacity: 0.4;
  }
}
@keyframes hop {
  40%,
  70% {
    transform: none;
  }
  55% {
    transform: translateY(-35%);
  }
}
@keyframes swim {
  to {
    transform: translate(300%, 20%) scaleX(-1);
  }
}
</style>
