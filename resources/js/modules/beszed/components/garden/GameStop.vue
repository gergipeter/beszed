<script setup>
import { computed } from 'vue'
import { t } from '../../i18n'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * A game on the garden trail: a stepping stone in the game's colour with its
 * picture, gently bobbing, and a little wooden sign with its name. Medals grow
 * as flowers around the stone; a flag marks a step of today's adventure.
 */
const props = defineProps({
  /** @type {import('vue').PropType<import('../../types').GameMeta>} */
  game: { type: Object, required: true },
  medal: { type: Number, default: 0 },
  /** 1–3: a step of today's "Mai kaland"; 0: not on it. */
  step: { type: Number, default: 0 },
  stepDone: { type: Boolean, default: false },
  /** Csillám suggests practising this one today. */
  spotlight: { type: Boolean, default: false },
  /** Order along the trail (the bob is out of step from stone to stone). */
  index: { type: Number, default: 0 },
})

/** Flowers around the stone, one per medal; their places around the rim. */
const FLOWERS = ['🌼', '🌸', '🌷']
const PLACES = [
  { left: '78%', top: '72%', rotate: 12 },
  { left: '-4%', top: '66%', rotate: -14 },
  { left: '84%', top: '10%', rotate: 20 },
]
const flowers = computed(() => PLACES.slice(0, props.medal).map((p, i) => ({ ...p, char: FLOWERS[i] })))
const label = computed(() =>
  [props.game.name, props.game.skill, props.medal ? t('rewards.medals', { count: props.medal }) : '']
    .filter(Boolean)
    .join('. '),
)
</script>

<template>
  <button
    type="button"
    class="stop"
    :class="{ 'stop--spotlight': spotlight }"
    :style="{ '--stop': game.color, '--i': index }"
    :data-game="game.id"
    :aria-label="label"
  >
    <span class="bob">
      <span class="stone">
        <EmojiArt class="art" :char="game.emoji" />
        <EmojiArt
          v-for="f in flowers"
          :key="f.left"
          class="flower"
          :char="f.char"
          :style="{ left: f.left, top: f.top, '--r': `${f.rotate}deg` }"
        />
        <b v-if="step" class="flag" :class="{ 'flag--done': stepDone }" aria-hidden="true">{{ stepDone ? '✓' : step }}</b>
      </span>
    </span>
    <span class="sign">{{ game.name }}</span>
  </button>
</template>

<style scoped>
.stop {
  position: absolute;
  display: flex;
  flex-direction: column;
  align-items: center;
  width: clamp(116px, 30vw, 150px);
  transform: translate(-50%, -46px);
}
.stone {
  position: relative;
  display: grid;
  place-items: center;
  width: clamp(80px, 22vw, 96px);
  aspect-ratio: 1;
  border-radius: 46% 54% 50% 50% / 52% 48% 52% 48%;
  background:
    radial-gradient(circle at 34% 28%, rgba(255, 255, 255, 0.75), transparent 38%),
    radial-gradient(circle at 50% 60%, var(--stop), color-mix(in srgb, var(--stop) 70%, #3b1f4a) 120%);
  box-shadow:
    inset 0 -6px 0 color-mix(in srgb, var(--stop) 60%, #3b1f4a),
    0 10px 0 -2px rgba(40, 60, 30, 0.25);
  transition: transform 0.38s var(--bz-spring);
}
/* bobbing on its own layer, each stone a little out of step with the last */
.bob {
  animation: bob 3.4s ease-in-out infinite;
  animation-delay: calc(var(--i) * -0.55s);
}
/* pressed anywhere (stone or sign): the stone squishes, then springs back */
.stop:active .stone {
  transform: scale(0.9);
  transition-duration: 0.07s;
  transition-timing-function: ease-out;
}
.art {
  font-size: clamp(42px, 12vw, 54px);
  line-height: 1;
  filter: drop-shadow(0 3px 0 rgba(59, 31, 74, 0.18));
}
.flower {
  position: absolute;
  font-size: 26px;
  transform: rotate(var(--r));
  animation: sway 2.8s ease-in-out infinite alternate;
}
.flag {
  position: absolute;
  top: -8px;
  left: -6px;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border: 3px solid #fff;
  border-radius: 50%;
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  font-size: 17px;
  font-weight: 800;
  box-shadow: var(--bz-shadow-sm);
}
.flag--done {
  background: var(--bz-leaf);
  color: var(--bz-on-accent);
}
.sign {
  max-width: 100%;
  margin-top: 8px;
  padding: 3px 10px 4px;
  border-radius: 10px 12px 10px 12px;
  background: var(--bz-bark);
  color: var(--bz-on-bark);
  font-size: clamp(14px, 3.8vw, 16px);
  font-weight: 800;
  line-height: 1.12;
  text-align: center;
  box-shadow: 0 3px 0 color-mix(in srgb, var(--bz-bark) 60%, #000);
  overflow-wrap: break-word;
}
/* "Ma ezt gyakoroljuk": a soft glow ring breathing around the stone */
.stop--spotlight .stone::after {
  content: '';
  position: absolute;
  inset: -10px;
  border-radius: 50%;
  background: radial-gradient(circle, transparent 58%, var(--bz-glow) 62%, transparent 74%);
  animation: glow 1.8s ease-in-out infinite;
  pointer-events: none;
}
@keyframes bob {
  50% {
    transform: translateY(-6px);
  }
}
@keyframes sway {
  to {
    transform: rotate(calc(var(--r) * -1)) scale(1.06);
  }
}
@keyframes glow {
  50% {
    opacity: 0.35;
    transform: scale(1.08);
  }
}
</style>
