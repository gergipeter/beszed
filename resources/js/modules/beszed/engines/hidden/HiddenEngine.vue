<script setup>
import { computed, reactive, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { t } from '../../i18n'
import { engineEmits, engineProps } from '../contract'

/**
 * Keresd meg! (hidden pictures): a busy picture laid out by the server
 * (x/y/size in % of a square board, rotate in degrees, drawing order = z).
 * Tap every target: each found one glows and fills a slot in the tray; a tap on
 * anything else shakes it and says why. Wrong taps never end the round, they
 * only grade the win (tries 1–3).
 *
 * A tap goes to the picture whose centre is nearest (relative to its size)
 * within a circle at least MIN_HIT px across, so small or partly covered
 * pictures stay easy to hit with a finger.
 * data: HiddenData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

/** Smallest tap circle, px (wider than the 44 px a finger needs). */
const MIN_HIT = 48
const WIN_DELAY_MS = 650

const board = ref(null)
const found = reactive(new Set())
const done = ref(false)
let wrong = 0
const { shaking, shake } = useShake()
const { later } = useTimers()

const targets = computed(() => props.data.items.filter(it => it.target))
/** The tray: one slot per target, filled in the order they were found. */
const foundOrder = ref([])
const slots = computed(() =>
  Array.from({ length: props.data.count }, (_, i) => {
    const id = foundOrder.value[i]
    return id ? props.data.items.find(it => it.id === id) : null
  }),
)
const grade = () => (wrong === 0 ? 1 : wrong <= 2 ? 2 : 3)

function pick(item) {
  if (props.locked || done.value || !item || found.has(item.id)) return
  if (!item.target) {
    wrong++
    shake(item.id)
    emit('say', item.say ?? props.data.wrong)
    return
  }
  found.add(item.id)
  foundOrder.value = [...foundOrder.value, item.id]
  if (found.size >= targets.value.length) {
    done.value = true
    later(() => emit('answer', { correct: true, say: props.data.onCorrect, tries: grade() }), WIN_DELAY_MS)
    return
  }
  emit('say', item.say ?? props.data.counts?.[found.size - 1] ?? '')
}

/** The picture under a finger: nearest centre (relative to its tap circle), not yet found ones first. */
function hit(event) {
  const rect = board.value.getBoundingClientRect()
  const px = event.clientX - rect.left
  const py = event.clientY - rect.top
  let best = null
  let bestScore = 1
  for (const it of props.data.items) {
    const radius = Math.max((it.size / 200) * rect.width, MIN_HIT / 2)
    const d = Math.hypot(px - (it.x / 100) * rect.width, py - (it.y / 100) * rect.height) / radius
    // an already found target doesn't steal taps from its neighbours
    const score = found.has(it.id) ? d + 0.5 : d
    if (score < bestScore) [best, bestScore] = [it, score]
  }
  return best
}

function onPointer(event) {
  if (event.button > 0) return
  pick(hit(event))
}
</script>

<template>
  <div class="hidden-game">
    <div
      ref="board"
      class="board"
      :class="`board--${data.backdrop}`"
      role="group"
      :aria-label="t('hidden.board')"
      @pointerdown.prevent="onPointer"
    >
      <span class="scenery" aria-hidden="true" />
      <button
        v-for="(it, i) in data.items"
        :key="it.id"
        type="button"
        class="pic"
        :class="{ 'pic--found': found.has(it.id), 'pic--shake': shaking === it.id }"
        :style="{ left: `${it.x}%`, top: `${it.y}%`, width: `${it.size}%`, '--s': it.size, zIndex: found.has(it.id) ? 200 : i + 1, '--turn': `${it.rotate}deg` }"
        :aria-label="t('hidden.picture', { n: i + 1 })"
        tabindex="0"
        @click.prevent="event => event.detail === 0 && pick(it)"
      >
        <EmojiArt class="art" :char="it.emoji" />
      </button>
    </div>

    <div class="tray" role="status" :aria-label="t('hidden.tray', { found: foundOrder.length, count: data.count })">
      <span v-if="data.tray?.swatch" class="clue clue--swatch" :style="{ background: data.tray.swatch }" aria-hidden="true" />
      <span v-else-if="data.tray?.sound" class="clue clue--sound" aria-hidden="true">{{ data.tray.sound }}</span>
      <span v-for="(slot, i) in slots" :key="i" class="slot" :class="{ 'slot--full': slot }">
        <EmojiArt v-if="slot" class="slot-art" :char="slot.emoji" />
        <EmojiArt v-else-if="data.tray?.emoji" class="slot-art slot-art--ghost" :char="data.tray.emoji" />
        <span v-else class="slot-q" aria-hidden="true">?</span>
      </span>
    </div>
  </div>
</template>

<style scoped>
.hidden-game {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  width: 100%;
}
.board {
  position: relative;
  width: min(100%, 640px, calc(100dvh - var(--bz-stage-room, 330px) - 74px));
  min-width: min(100%, 260px);
  aspect-ratio: 1;
  overflow: hidden;
  border-radius: var(--bz-radius-lg);
  box-shadow: var(--bz-shadow-lg);
  touch-action: manipulation;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
  container-type: inline-size;
  /* backdrop colours: day by default, night versions below */
  --sky-a: var(--bz-sky-top, #7cc8ff);
  --sky-b: var(--bz-sky-bottom, #e2f4ff);
  --grass-a: var(--bz-grass, #a9df86);
  --grass-b: var(--bz-grass-deep, #7cc462);
  --sea-a: #7fd3f0;
  --sea-b: #2a8fc4;
  --sand: #f3dca2;
  --tile-a: #fff6e6;
  --tile-b: #ffe3c2;
  --counter: #c98b5a;
  --wall-a: #ffe9f1;
  --wall-b: #fdd7e6;
  --floor: #d9a774;
  --forest-a: var(--bz-forest, #5fae78);
  --forest-b: var(--bz-forest-deep, #3f8a5d);
  --night-a: #141a45;
  --night-b: #33407e;
  --snow-a: #dff1ff;
  --snow-b: #ffffff;
  --sun: #ffe066;
}
.bz[data-daytime='night'] .board {
  --sea-a: #2d6f8f;
  --sea-b: #173f5e;
  --sand: #8d7c58;
  --tile-a: #4a456a;
  --tile-b: #3d3a5c;
  --counter: #6e4f3a;
  --wall-a: #4b3f66;
  --wall-b: #423759;
  --floor: #6b5038;
  --snow-a: #5a6c92;
  --snow-b: #7e8fb3;
  --sun: #f3efd2;
}
.scenery {
  position: absolute;
  inset: 0;
  pointer-events: none;
}
/* garden: sky, a sun, rolling grass */
.board--garden .scenery {
  background:
    radial-gradient(circle at 84% 12%, var(--sun) 0 6%, transparent 6.5%),
    radial-gradient(ellipse 70% 30% at 20% 62%, var(--grass-a) 0 99%, transparent 100%),
    radial-gradient(ellipse 80% 34% at 85% 66%, var(--grass-a) 0 99%, transparent 100%),
    linear-gradient(to bottom, var(--sky-a) 0, var(--sky-b) 52%, var(--grass-a) 52%, var(--grass-b) 100%);
}
/* sea: light at the top, deep below, waves and a sandy floor */
.board--sea .scenery {
  background:
    radial-gradient(ellipse 60% 14% at 30% 100%, var(--sand) 0 99%, transparent 100%),
    radial-gradient(ellipse 70% 16% at 85% 102%, var(--sand) 0 99%, transparent 100%),
    repeating-radial-gradient(circle at 50% -30%, transparent 0 14%, rgba(255, 255, 255, 0.12) 14% 15%),
    linear-gradient(to bottom, var(--sea-a), var(--sea-b));
}
/* kitchen: checked tiles on the wall, a wooden counter */
.board--kitchen .scenery {
  background:
    linear-gradient(to bottom, transparent 70%, var(--counter) 70% 73%, color-mix(in srgb, var(--counter) 80%, #000) 73%),
    repeating-conic-gradient(var(--tile-a) 0 25%, var(--tile-b) 0 50%) 0 0 / 12.5% 12.5%;
}
/* sky: big soft clouds */
.board--sky .scenery {
  background:
    radial-gradient(ellipse 22% 9% at 22% 30%, rgba(255, 255, 255, 0.85) 0 98%, transparent 100%),
    radial-gradient(ellipse 16% 8% at 30% 26%, rgba(255, 255, 255, 0.85) 0 98%, transparent 100%),
    radial-gradient(ellipse 26% 10% at 74% 70%, rgba(255, 255, 255, 0.8) 0 98%, transparent 100%),
    radial-gradient(ellipse 15% 8% at 66% 66%, rgba(255, 255, 255, 0.8) 0 98%, transparent 100%),
    linear-gradient(to bottom, var(--sky-a), var(--sky-b));
}
/* forest: dark green with rows of tree crowns */
.board--forest .scenery {
  background:
    radial-gradient(circle at 10% 30%, var(--forest-b) 0 14%, transparent 14.5%),
    radial-gradient(circle at 40% 22%, var(--forest-b) 0 16%, transparent 16.5%),
    radial-gradient(circle at 78% 28%, var(--forest-b) 0 15%, transparent 15.5%),
    linear-gradient(to bottom, var(--forest-a), var(--forest-b) 55%, var(--grass-b));
}
/* a child's room: striped wallpaper, a wooden floor */
.board--room .scenery {
  background:
    repeating-linear-gradient(to right, transparent 0 6%, rgba(0, 0, 0, 0.06) 6% 6.4%) 0 66% / 100% 34% no-repeat,
    linear-gradient(to bottom, transparent 66%, var(--floor) 66%),
    repeating-linear-gradient(to right, var(--wall-a) 0 5%, var(--wall-b) 5% 10%);
}
/* night sky: a deep blue with little stars */
.board--night .scenery {
  background:
    radial-gradient(circle at 15% 20%, #fff 0 0.5%, transparent 0.8%),
    radial-gradient(circle at 70% 12%, #fff 0 0.6%, transparent 0.9%),
    radial-gradient(circle at 45% 55%, #fff 0 0.4%, transparent 0.7%),
    radial-gradient(circle at 85% 75%, #fff 0 0.5%, transparent 0.8%),
    linear-gradient(to bottom, var(--night-a), var(--night-b));
}
/* snow: a pale sky over snowy hills */
.board--snow .scenery {
  background:
    radial-gradient(ellipse 70% 30% at 25% 70%, var(--snow-b) 0 99%, transparent 100%),
    radial-gradient(ellipse 70% 30% at 85% 75%, var(--snow-b) 0 99%, transparent 100%),
    linear-gradient(to bottom, #b8dcf5, var(--snow-a) 60%, var(--snow-b));
}

.pic {
  position: absolute;
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  padding: 0;
  border: 0;
  background: none;
  /* the board picks the picture (nearest centre); the button is there for keyboards and screen readers */
  pointer-events: none;
  transform: translate(-50%, -50%) rotate(var(--turn));
}
/* the picture's width is --s % of the board: the emoji fills most of it (container units = the board) */
.art {
  font-size: calc(var(--s) * 0.86cqw);
  line-height: 1;
}
/* the glow round a found picture: its own layer, only opacity and transform animate */
.pic::before {
  content: '';
  position: absolute;
  inset: -10%;
  border-radius: 50%;
  background: radial-gradient(circle, var(--bz-glow, rgba(255, 246, 170, 0.95)) 0 45%, transparent 70%);
  box-shadow: 0 0 0 4px var(--bz-sun);
  opacity: 0;
  transform: scale(0.6);
  z-index: -1;
}
.pic--found::before {
  opacity: 1;
  transform: scale(1);
  transition: opacity 0.2s, transform 0.35s var(--bz-spring, ease-out);
}
.pic--found .art {
  animation: found 0.45s var(--bz-spring, ease-out);
}
@keyframes found {
  from {
    transform: scale(1.35);
  }
}
.pic--shake .art {
  animation: shake 0.45s;
}
@keyframes shake {
  20% {
    transform: translateX(-12%);
  }
  40% {
    transform: translateX(12%);
  }
  60% {
    transform: translateX(-8%);
  }
  80% {
    transform: translateX(8%);
  }
}

.tray {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-sm);
}
.clue {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  margin-right: 4px;
}
.clue--swatch {
  border: 3px solid var(--bz-ink);
}
.clue--sound {
  background: var(--bz-soft);
  color: var(--bz-ink);
  font-weight: 800;
  font-size: var(--bz-text-md);
}
.slot {
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 50%;
  border: 3px dashed var(--bz-guide);
  font-size: 30px;
}
.slot--full {
  border-style: solid;
  border-color: var(--bz-leaf);
  background: var(--bz-soft);
  animation: pop 0.35s var(--bz-spring, ease-out);
}
@keyframes pop {
  from {
    transform: scale(0.4);
  }
}
.slot-art--ghost {
  opacity: 0.3;
  filter: grayscale(1);
}
.slot-q {
  color: var(--bz-muted);
  font-weight: 800;
  font-size: var(--bz-text-md);
}
</style>
