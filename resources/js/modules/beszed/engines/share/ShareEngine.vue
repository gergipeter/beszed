<script setup>
import { computed, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useTimers } from '../../composables/useTimers'
import { t } from '../../i18n'
import { beep, tone } from '../../services/audio/sfx'
import { buzz } from '../../services/touch/feel'
import { engineEmits, engineProps } from '../contract'

/**
 * Osztozkodás: fair sharing. Items wait in a basket; tapping a plate sends one
 * over with a flying arc, tapping an item on a plate sends it back. It is done
 * when every plate holds the same and only data.left items stay in the basket.
 * Only the win is reported, graded by the moves taken back (tries 1–3).
 * data: ShareData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const FLY_MS = 520

const plates = ref(Array.from({ length: props.data.plates }, () => 0))
const basket = computed(() => props.data.count - plates.value.reduce((a, b) => a + b, 0))
const undone = ref(0)
const done = ref(false)
const flying = ref(false)
const bounced = ref(null)
const root = ref(null)
const basketEl = ref(null)
const plateEls = ref([])
const { later } = useTimers()

/** A copy of the item flies from `from` to `to` along an arc; both are elements inside the root. */
function fly(from, to) {
  const box = root.value.getBoundingClientRect()
  const a = from.getBoundingClientRect()
  const b = to.getBoundingClientRect()
  const size = 40
  const x0 = a.left + a.width / 2 - box.left - size / 2
  const y0 = a.top + a.height / 2 - box.top - size / 2
  const x1 = b.left + b.width / 2 - box.left - size / 2
  const y1 = b.top + b.height / 2 - box.top - size / 2
  const el = document.createElement('span')
  el.className = 'flier'
  el.textContent = props.data.emoji
  root.value.appendChild(el)
  const anim = el.animate(
    [
      { transform: `translate(${x0}px, ${y0}px) scale(1)` },
      { transform: `translate(${(x0 + x1) / 2}px, ${Math.min(y0, y1) - 70}px) scale(1.35) rotate(${x1 > x0 ? 18 : -18}deg)`, offset: 0.5 },
      { transform: `translate(${x1}px, ${y1}px) scale(1)` },
    ],
    { duration: FLY_MS, easing: 'cubic-bezier(0.4, 0, 0.3, 1)' },
  )
  return anim.finished.catch(() => {}).finally(() => el.remove())
}

async function give(i) {
  if (props.locked || done.value || flying.value || basket.value <= 0) return
  flying.value = true
  beep(380 + i * 60, 0.08)
  await fly(basketEl.value, plateEls.value[i])
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
  await fly(plateEls.value[i], basketEl.value)
  flying.value = false
}

function check() {
  const equal = plates.value.every(n => n === plates.value[0])
  if (!equal || basket.value !== props.data.left) return
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

    <div class="basket" :class="{ 'basket--empty': basket === 0 }">
      <span ref="basketEl" class="basket-mark" aria-hidden="true" />
      <span class="weave" aria-hidden="true" />
      <p class="basket-items">
        <EmojiArt v-for="k in basket" :key="k" class="it it--basket" :char="data.emoji" />
      </p>
      <small class="basket-count">🧺 {{ basket }}</small>
    </div>

    <p class="hint">{{ t('split.hint') }}</p>
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
.basket {
  position: relative;
  width: min(100%, 420px);
  min-height: 110px;
  padding: 16px 16px 26px;
  border-radius: 14px 14px 40px 40px;
  background: linear-gradient(180deg, #e6b877, #c98f4c);
  box-shadow: 0 6px 0 rgba(120, 80, 30, 0.4), var(--bz-shadow-lg);
  transition: opacity 0.4s;
}
.basket--empty {
  opacity: 0.8;
}
.weave {
  position: absolute;
  inset: auto 0 0 0;
  height: 26px;
  border-radius: 0 0 40px 40px;
  background: repeating-linear-gradient(90deg, rgba(120, 80, 30, 0.22) 0 6px, transparent 6px 14px);
  pointer-events: none;
}
.basket-mark {
  position: absolute;
  left: 50%;
  top: 40%;
  width: 20px;
  height: 20px;
  margin: -10px 0 0 -10px;
  pointer-events: none;
}
.basket-items {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 2px;
  min-height: 50px;
  margin: 0;
  font-size: clamp(24px, 7.5vw, 34px);
  line-height: 1.05;
}
.it--basket {
  animation: none;
}
.basket-count {
  position: absolute;
  right: 14px;
  bottom: 5px;
  font-size: 14px;
  font-weight: 900;
  color: #5b3a12;
}
.hint {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  color: var(--bz-muted);
  text-align: center;
}
:deep(.flier) {
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
