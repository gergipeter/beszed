<script setup>
import { computed, nextTick, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useTimers } from '../../composables/useTimers'
import { t } from '../../i18n'
import { beep, tone } from '../../services/audio/sfx'
import { buzz } from '../../services/touch/feel'
import { engineEmits, engineProps } from '../contract'

/**
 * Osztozkodás: fair sharing. Items wait on a tablecloth; tapping a plate sends one
 * over with a flying arc, tapping an item on a plate sends it back. It is done
 * when every plate holds the same and only data.left items stay on the table.
 * Only the win is reported, graded by the moves taken back (tries 1–3).
 * data: ShareData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const FLY_MS = 520

const plates = ref(Array.from({ length: props.data.plates }, () => 0))
const table = computed(() => props.data.count - plates.value.reduce((a, b) => a + b, 0))
const undone = ref(0)
const done = ref(false)
const flying = ref(false)
const bounced = ref(null)
const root = ref(null)
const tableEl = ref(null)
const flierEl = ref(null)
const plateEls = ref([])
const { later } = useTimers()

/**
 * The item flies from `from` to `to` along an arc; both are elements inside the root. The flier is drawn
 * by EmojiArt like the items on the table and plates, so a pictogram stays the same picture all the way.
 */
async function fly(from, to) {
  flying.value = true
  await nextTick()
  const el = flierEl.value
  if (!el) return
  const box = root.value.getBoundingClientRect()
  const a = from.getBoundingClientRect()
  const b = to.getBoundingClientRect()
  const size = 40
  const x0 = a.left + a.width / 2 - box.left - size / 2
  const y0 = a.top + a.height / 2 - box.top - size / 2
  const x1 = b.left + b.width / 2 - box.left - size / 2
  const y1 = b.top + b.height / 2 - box.top - size / 2
  const anim = el.animate(
    [
      { transform: `translate(${x0}px, ${y0}px) scale(1)` },
      { transform: `translate(${(x0 + x1) / 2}px, ${Math.min(y0, y1) - 70}px) scale(1.35) rotate(${x1 > x0 ? 18 : -18}deg)`, offset: 0.5 },
      { transform: `translate(${x1}px, ${y1}px) scale(1)` },
    ],
    { duration: FLY_MS, easing: 'cubic-bezier(0.4, 0, 0.3, 1)', fill: 'forwards' },
  )
  await anim.finished.catch(() => {})
}

async function give(i) {
  if (props.locked || done.value || flying.value || table.value <= 0) return
  flying.value = true
  beep(380 + i * 60, 0.08)
  await fly(tableEl.value, plateEls.value[i])
  plates.value[i]++
  bounced.value = i
  tone(440 + plates.value[i] * 45, 0.16)
  buzz(10)
  later(() => bounced.value === i && (bounced.value = null), 500)
  flying.value = false
  check()
}

async function back(i) {
  if (props.locked || done.value || flying.value || plates.value[i] <= 0) return
  flying.value = true
  undone.value++
  plates.value[i]--
  await fly(plateEls.value[i], tableEl.value)
  flying.value = false
}

function check() {
  const equal = plates.value.every(n => n === plates.value[0])
  if (!equal || table.value !== props.data.left) return
  done.value = true
  later(() => {
    tone(523.25, 0.4)
    tone(659.25, 0.4)
    tone(783.99, 0.6)
  }, 200)
  later(() => emit('answer', { correct: true, say: props.data.onCorrect, tries: undone.value === 0 ? 1 : undone.value <= 2 ? 2 : 3 }), 800)
}
</script>

<template>
  <div ref="root" class="share" data-no-feel>
    <div class="plates">
      <div v-for="(n, i) in plates" :key="i" class="plate-wrap" :class="{ 'plate-wrap--bounce': bounced === i }">
        <button type="button" class="plate" :aria-label="t('split.plate', { n: i + 1 })" @click="give(i)">
          <span :ref="el => (plateEls[i] = el)" class="rim" />
          <span class="food">
            <EmojiArt
              v-for="k in n"
              :key="k"
              class="it"
              :char="data.emoji"
              role="button"
              @click.stop="back(i)"
            />
          </span>
        </button>
        <b class="count" :class="{ 'count--done': done }">{{ n }}</b>
      </div>
    </div>

    <div class="cloth" :class="{ 'cloth--empty': table === 0 }">
      <span ref="tableEl" class="cloth-mark" aria-hidden="true" />
      <p class="cloth-items">
        <EmojiArt v-for="k in table" :key="k" class="it it--cloth" :char="data.emoji" />
      </p>
      <small class="cloth-count">{{ table }}</small>
    </div>

    <p class="hint">{{ t('split.hint') }}</p>

    <span v-if="flying" ref="flierEl" class="flier" aria-hidden="true"><EmojiArt :char="data.emoji" /></span>
  </div>
</template>

<style scoped>
.share {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
  width: 100%;
  max-width: 520px;
}
.plates {
  display: flex;
  justify-content: center;
  gap: clamp(10px, 4vw, 26px);
  width: 100%;
}
.plate-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  flex: 1;
  max-width: min(150px, 34vw);
}
.plate-wrap--bounce .plate {
  animation: plate-bounce 0.5s var(--bz-spring);
}
.plate {
  position: relative;
  display: grid;
  place-items: center;
  width: 100%;
  aspect-ratio: 1;
  border-radius: 50%;
  background: radial-gradient(circle at 50% 45%, #fff 0 52%, #e7e9f5 53% 62%, #fff 63%);
  box-shadow: 0 6px 0 rgba(90, 90, 130, 0.28), var(--bz-shadow);
  touch-action: manipulation;
  transition: transform 0.3s var(--bz-spring);
}
.plate:active {
  transform: scale(0.95);
}
.rim {
  position: absolute;
  left: 50%;
  top: 50%;
  width: 20px;
  height: 20px;
  margin: -10px 0 0 -10px;
  pointer-events: none;
}
.food {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-content: center;
  justify-content: center;
  gap: 1px;
  width: 66%;
  min-height: 40%;
  font-size: clamp(18px, 5.4vw, 26px);
  line-height: 1.05;
}
.it {
  display: inline-block;
  animation: land 0.5s var(--bz-spring);
}
.count {
  min-width: 2.2em;
  padding: 2px 12px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-size: 22px;
  text-align: center;
  box-shadow: var(--bz-shadow-sm);
  transition: background 0.3s, transform 0.4s var(--bz-spring);
}
.count--done {
  background: var(--bz-leaf);
  color: var(--bz-on-accent);
  transform: scale(1.15);
}
/* the table: a red-and-white checked tablecloth with a scalloped hem hanging over the front */
.cloth {
  position: relative;
  width: min(100%, 420px);
  min-height: 110px;
  margin-bottom: 14px;
  padding: 16px 16px 30px;
  border-radius: 14px 14px 6px 6px;
  background:
    repeating-linear-gradient(90deg, rgba(255, 111, 97, 0.18) 0 20px, transparent 20px 40px),
    repeating-linear-gradient(0deg, rgba(255, 111, 97, 0.18) 0 20px, transparent 20px 40px), #fff;
  box-shadow: 0 5px 0 rgba(150, 60, 50, 0.3), var(--bz-shadow-lg);
  transition: opacity 0.4s;
}
.cloth::after {
  content: '';
  position: absolute;
  inset: 100% 0 auto;
  height: 14px;
  background: radial-gradient(circle at 50% 0, #ff6f61 0 10px, transparent 11px) 0 0 / 22px 14px repeat-x;
  pointer-events: none;
}
.cloth--empty {
  opacity: 0.85;
}
.cloth-mark {
  position: absolute;
  left: 50%;
  top: 40%;
  width: 20px;
  height: 20px;
  margin: -10px 0 0 -10px;
  pointer-events: none;
}
.cloth-items {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-content: center;
  gap: 2px;
  min-height: 64px;
  margin: 0;
  font-size: clamp(28px, 8.5vw, 40px);
  line-height: 1.05;
}
.it--cloth {
  animation: none;
}
.cloth-count {
  position: absolute;
  right: 10px;
  bottom: 6px;
  min-width: 1.9em;
  padding: 1px 8px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-sm);
  font-size: 14px;
  font-weight: 900;
  text-align: center;
  color: var(--bz-ink);
}
.hint {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  color: var(--bz-muted);
  text-align: center;
}
.flier {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 5;
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  font-size: 32px;
  line-height: 1;
  pointer-events: none;
  filter: drop-shadow(0 6px 4px rgba(0, 0, 0, 0.25));
}
@keyframes land {
  from {
    opacity: 0;
    transform: scale(0.3) translateY(-14px);
  }
  60% {
    transform: scale(1.2, 0.85);
  }
}
@keyframes plate-bounce {
  30% {
    transform: scale(1.06, 0.94);
  }
  60% {
    transform: scale(0.97, 1.04);
  }
}
</style>
