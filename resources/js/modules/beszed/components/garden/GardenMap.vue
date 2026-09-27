<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ICONS } from '../../config/icons'
import { buzz } from '../../services/touch/feel'
import { t } from '../../i18n'
import EmojiArt from '../ui/EmojiArt.vue'
import GameStop from './GameStop.vue'
import { plantSpots, trailLayout, trailPath } from './trail'

/**
 * Zoé's garden, the hub's game map: the meadow (simple games) and the enchanted
 * forest (advanced ones), each a winding trail of stepping stones. Every
 * finished game grows a plant along the trail; the ones grown since the last
 * visit sprout in front of the child.
 */
const props = defineProps({
  /** @type {import('vue').PropType<import('../../types').GameMeta[]>} */
  games: { type: Array, required: true },
  /** game id → 0–3 medals */
  medals: { type: Object, default: () => ({}) },
  /** @type {import('vue').PropType<import('../../types').DailyPath | null>} */
  path: { type: Object, default: null },
  spotlight: { type: String, default: null },
  /** Plants in the garden (one per finished game, up to the room there is). */
  plants: { type: Number, default: 0 },
  /** Plants from this index on are new since the last visit: they sprout. */
  sproutFrom: { type: Number, default: Infinity },
})
const emit = defineEmits(['play'])

const ZONES = [
  { id: 'meadow', tier: 'simple', icon: ICONS.tierSimple, growth: ['🌷', '🌼', '🌻', '🌸', '🌱', '🪻', '🌾', '🌿'] },
  { id: 'forest', tier: 'advanced', icon: ICONS.tierAdvanced, growth: ['🍄', '🌿', '🌱', '🍄', '🌰', '🪺', '🌿'] },
]
/** Fireflies of the forest night (% of the zone). */
const FIREFLIES = [
  [8, 12], [22, 30], [80, 18], [91, 44], [14, 58], [70, 66], [40, 80], [88, 86], [55, 40], [30, 92],
]

const root = ref(null)
const frame = ref(null)
const width = ref(0)
const height = ref(0)
let observer = null
onMounted(() => {
  observer = new ResizeObserver(([entry]) => {
    width.value = Math.round(entry.contentRect.width)
    height.value = Math.round(entry.contentRect.height)
  })
  observer.observe(root.value)
  frame.value.addEventListener('wheel', onWheel, { passive: false })
})
onBeforeUnmount(() => {
  observer?.disconnect()
  frame.value?.removeEventListener('wheel', onWheel)
})

/**
 * Pinch the garden like a map: fingers together → the whole garden on one
 * screen (a map to pick from), apart → back up close. A trackpad pinch
 * (ctrl + wheel) does the same, and so does the button, for one finger.
 * The browser's own page zoom is off here (touch-action), so the pinch is ours.
 */
const overview = ref(false)
const scale = computed(() =>
  overview.value && height.value ? Math.min(1, Math.max(0.3, (window.innerHeight - 150) / height.value)) : 1,
)
let pinch = null
const spread = touches => Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY)

function setOverview(on) {
  if (overview.value === on) return
  overview.value = on
  buzz(10)
  if (on) requestAnimationFrame(() => frame.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }))
}
function onTouchStart(event) {
  pinch = event.touches.length === 2 ? { start: spread(event.touches), done: false } : null
}
function onTouchMove(event) {
  if (!pinch || pinch.done || event.touches.length !== 2) return
  const ratio = spread(event.touches) / (pinch.start || 1)
  if (ratio < 0.8) setOverview(true)
  else if (ratio > 1.25) setOverview(false)
  else return
  pinch.done = true // one step per pinch
}
function onWheel(event) {
  if (!event.ctrlKey) return // an ordinary scroll
  event.preventDefault() // not the browser's zoom
  setOverview(event.deltaY > 0)
}

/** Deterministic shuffle, so each plant keeps its spot from visit to visit. */
function scatter(list, seed) {
  const out = [...list]
  for (let i = out.length - 1; i > 0; i--) {
    seed = (seed * 9301 + 49297) % 233280
    const j = Math.floor((seed / 233280) * (i + 1))
    ;[out[i], out[j]] = [out[j], out[i]]
  }
  return out
}

const zones = computed(() => {
  if (!width.value) return []
  let enterX
  const laid = ZONES.map((zone, z) => {
    const games = props.games.filter(g => (g.tier ?? 'simple') === zone.tier)
    const layout = trailLayout(games.length, width.value)
    const d = trailPath(layout.points, layout.height, enterX)
    enterX = layout.points.at(-1)?.x
    return { ...zone, games, layout, d, spots: scatter(plantSpots(layout, width.value), 7 + z) }
  })
  // the child's plants: meadow and forest in turn; a full zone passes its plants to the other
  const grown = laid.map(() => [])
  for (let n = 0; n < props.plants; n++) {
    const z = (n % 2 ? [1, 0] : [0, 1]).find(i => grown[i].length < laid[i].spots.length)
    if (z === undefined) break
    const zone = laid[z]
    const sprout = n >= props.sproutFrom
    grown[z].push({
      ...zone.spots[grown[z].length],
      char: zone.growth[grown[z].length % zone.growth.length],
      n,
      sprout,
      delay: sprout ? n - props.sproutFrom : 0,
    })
  }
  return laid.map((zone, z) => ({ ...zone, grown: grown[z] })).filter(zone => zone.games.length)
})

const stepOf = id => (props.path?.games.indexOf(id) ?? -1) + 1
const doneOf = id => Boolean(props.path?.done.includes(id))
</script>

<template>
  <button type="button" class="map-toggle" :aria-pressed="overview" @click="setOverview(!overview)">
    <EmojiArt :char="overview ? ICONS.zoomIn : ICONS.map" /> {{ t(overview ? 'hub.closer' : 'hub.wholeGarden') }}
  </button>
  <div
    ref="frame"
    class="garden-frame"
    :style="{ height: overview ? `${Math.round(height * scale)}px` : null }"
    @touchstart.passive="onTouchStart"
    @touchmove.passive="onTouchMove"
  >
    <div ref="root" class="garden" :style="{ transform: scale < 1 ? `scale(${scale})` : null }">
      <section v-for="zone in zones" :key="zone.id" class="zone" :class="`zone--${zone.id}`" :aria-labelledby="`zone-${zone.id}`">
        <header class="zone-head">
          <EmojiArt class="zone-icon" :char="zone.icon" />
          <div>
            <h2 :id="`zone-${zone.id}`" class="zone-title">{{ t(`hub.zones.${zone.id}`) }}</h2>
            <p class="zone-hint">
              <b>{{ t(`hub.tiers.${zone.tier}.title`) }}</b> · {{ t(`hub.tiers.${zone.tier}.hint`) }}
            </p>
          </div>
        </header>
  
        <div class="field" :style="{ height: `${zone.layout.height}px` }">
          <svg class="trail" :width="width" :height="zone.layout.height" aria-hidden="true">
            <path class="trail-bed" :d="zone.d" />
            <path class="trail-stones" :d="zone.d" />
          </svg>
  
          <span v-if="zone.id === 'forest'" class="fireflies" aria-hidden="true">
            <i v-for="([x, y], i) in FIREFLIES" :key="i" :style="{ left: `${x}%`, top: `${y}%`, '--i': i }" />
          </span>
  
          <EmojiArt
            v-for="p in zone.grown"
            :key="p.n"
            class="plant"
            :class="{ 'plant--sprout': p.sprout }"
            :char="p.char"
            :style="{ left: `${p.x}px`, top: `${p.y}px`, '--n': p.delay }"
          />
  
          <GameStop
            v-for="(game, i) in zone.games"
            :key="game.id"
            :game="game"
            :index="i"
            :medal="medals[game.id] ?? 0"
            :step="stepOf(game.id)"
            :step-done="doneOf(game.id)"
            :spotlight="spotlight === game.id"
            :style="{ left: `${zone.layout.points[i].x}px`, top: `${zone.layout.points[i].y}px` }"
            @click="emit('play', game.id, $event.currentTarget.querySelector('.stone'))"
          />
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
/* "Az egész kert": the map view on and off (the pinch does the same) */
.map-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 10px auto;
  padding: 6px 14px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-weight: 800;
  font-size: var(--bz-text-sm);
  box-shadow: var(--bz-shadow-sm);
}
/* the pinch belongs to the map here, not to the browser's page zoom */
.garden-frame {
  touch-action: pan-x pan-y;
  transition: height 0.45s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.garden {
  transform-origin: top center;
  transition: transform 0.45s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.garden {
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.zone {
  position: relative;
  border-radius: 44px 44px 36px 36px;
}
.zone-head {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 6px -18px;
  padding: 10px 16px 12px;
  border-radius: 22px 24px 20px 26px;
  background: var(--bz-bark);
  color: var(--bz-on-bark);
  box-shadow: 0 5px 0 color-mix(in srgb, var(--bz-bark) 60%, #000);
}
.zone-icon {
  flex: none;
  font-size: 38px;
}
.zone-title {
  margin: 0;
  font-size: var(--bz-text-lg);
  font-weight: 800;
  line-height: 1.05;
}
.zone-hint {
  margin: 2px 0 0;
  font-size: var(--bz-text-sm);
  line-height: 1.25;
  opacity: 0.9;
}
.field {
  position: relative;
  overflow: hidden;
  border-radius: 40px 40px 36px 36px;
}
/* the meadow: rolling grass with soft light patches */
.zone--meadow .field {
  background:
    /* tiny wild flowers speckled over the grass */
    radial-gradient(circle at 30% 40%, rgba(255, 255, 255, 0.7) 0 1.6px, transparent 2.4px) 0 0 / 47px 53px,
    radial-gradient(circle at 70% 20%, rgba(255, 232, 110, 0.75) 0 1.8px, transparent 2.6px) 0 0 / 61px 44px,
    radial-gradient(circle at 50% 80%, rgba(255, 170, 205, 0.6) 0 1.6px, transparent 2.4px) 0 0 / 73px 67px,
    radial-gradient(120% 60% at 20% 8%, color-mix(in srgb, var(--bz-grass) 60%, #fff) 0, transparent 60%),
    radial-gradient(90% 40% at 90% 55%, color-mix(in srgb, var(--bz-grass-deep) 55%, transparent) 0, transparent 70%),
    linear-gradient(to bottom, var(--bz-grass), var(--bz-grass-deep));
}
/* the enchanted forest: deeper green, tree crowns along both edges */
.zone--forest .field {
  background:
    /* dappled light through the leaves */
    radial-gradient(circle at 40% 30%, rgba(210, 255, 200, 0.16) 0 9px, transparent 10px) 0 0 / 83px 97px,
    radial-gradient(circle at 70% 70%, rgba(210, 255, 200, 0.12) 0 6px, transparent 7px) 0 0 / 59px 71px,
    radial-gradient(34px 44px at 0 8%, var(--bz-forest-deep) 98%, transparent) 0 0 / 100% 160px repeat-y,
    radial-gradient(34px 44px at 100% 60%, var(--bz-forest-deep) 98%, transparent) 0 0 / 100% 190px repeat-y,
    radial-gradient(120% 50% at 50% 0, color-mix(in srgb, var(--bz-forest) 55%, #fff) 0, transparent 55%),
    linear-gradient(to bottom, var(--bz-forest), var(--bz-forest-deep));
}
.trail {
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.trail-bed,
.trail-stones {
  fill: none;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.trail-bed {
  stroke: var(--bz-trail);
  stroke-width: 30;
  opacity: 0.95;
}
.trail-stones {
  stroke: var(--bz-trail-stone);
  stroke-width: 11;
  stroke-dasharray: 1 24;
}
.plant {
  position: absolute;
  font-size: 30px;
  line-height: 1;
  transform: translate(-50%, -80%);
  pointer-events: none;
}
/* grown since the last visit: pops out of the ground one after another */
.plant--sprout {
  animation: sprout 0.7s var(--bz-spring) backwards;
  animation-delay: calc(0.6s + var(--n) * 0.25s);
}
.fireflies {
  display: none;
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.bz[data-daytime='night'] .fireflies,
.bz[data-daytime='evening'] .fireflies {
  display: block;
}
.fireflies i {
  position: absolute;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #fff7b0;
  box-shadow: 0 0 10px 4px rgba(255, 240, 140, 0.65);
  animation: firefly 4.5s ease-in-out infinite alternate;
  animation-delay: calc(var(--i) * -0.7s);
}
@keyframes sprout {
  from {
    transform: translate(-50%, -20%) scale(0);
  }
}
@keyframes firefly {
  0% {
    opacity: 0.2;
    transform: translate(0, 0);
  }
  50% {
    opacity: 1;
  }
  100% {
    opacity: 0.3;
    transform: translate(18px, -22px);
  }
}
</style>
