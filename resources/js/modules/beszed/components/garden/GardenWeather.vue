<script setup>
import { ref } from 'vue'
import { t } from '../../i18n'
import { giggle } from '../../services/audio/sfx'
import { buzz } from '../../services/touch/feel'
import EmojiArt from '../ui/EmojiArt.vue'

/** A little animal sometimes wanders through the meadow: tap it to say hi (nothing to collect, it just leaves). */
defineProps({ visitor: { type: Boolean, default: false } })
const VISITORS = ['🐞', '🐇', '🦔', '🐿️', '🐥', '🦆', '🐌']
const guest = Math.random() < 0.4 ? VISITORS[Math.floor(Math.random() * VISITORS.length)] : null
const guestTop = 25 + Math.floor(Math.random() * 50)
const hello = ref(false)
function greetGuest() {
  giggle()
  buzz(15)
  hello.value = false
  requestAnimationFrame(() => (hello.value = true))
}

/**
 * Life in the garden, which changes with the day and the season: butterflies and a bee by day,
 * and petals (spring), leaves (autumn) or snowflakes (winter) drifting down. Around Christmas and
 * Easter the falling set swaps for a holiday one, same mechanism, just for a few days a year.
 * Pure decoration (pointer-events off); the evening fireflies live in GardenMap.
 */
const SEASONS = {
  spring: ['🌸', '🌸', '🌼', '🌸', '🌸'],
  autumn: ['🍂', '🍁', '🍂', '🍃', '🍂'],
  winter: ['❄️', '❄️', '❄️', '❄️', '❄️'],
  summer: [],
}
const HOLIDAYS = {
  christmas: ['❄️', '✨', '🎄', '✨', '❄️'],
  easter: ['🌼', '🐣', '🌸', '🥚', '🌼'],
}

/** Easter Sunday (Gregorian), by the anonymous/Gauss algorithm. Returns a Date at local midnight. */
function easterSunday(year) {
  const a = year % 19
  const b = Math.floor(year / 100)
  const c = year % 100
  const d = Math.floor(b / 4)
  const e = b % 4
  const f = Math.floor((b + 8) / 25)
  const g = Math.floor((b - f + 1) / 3)
  const h = (19 * a + b - d - g + 15) % 30
  const i = Math.floor(c / 4)
  const k = c % 4
  const l = (32 + 2 * e + 2 * i - h - k) % 7
  const m = Math.floor((a + 11 * h + 22 * l) / 451)
  const month = Math.floor((h + l - 7 * m + 114) / 31) // 3 = March, 4 = April
  const day = ((h + l - 7 * m + 114) % 31) + 1
  return new Date(year, month - 1, day)
}

/** The day itself, plus a day either side so the decor eases in and out. */
function withinDays(date, target, days) {
  return Math.abs(date.setHours(12, 0, 0, 0) - target.setHours(12, 0, 0, 0)) <= days * 86400000
}

function currentHoliday(now) {
  if (withinDays(new Date(now), new Date(now.getFullYear(), 11, 25), 1)) return 'christmas'
  if (withinDays(new Date(now), easterSunday(now.getFullYear()), 1)) return 'easter'
  return null
}

const month = new Date().getMonth()
const season = month >= 2 && month <= 4 ? 'spring' : month >= 5 && month <= 7 ? 'summer' : month >= 8 && month <= 10 ? 'autumn' : 'winter'
const holiday = currentHoliday(new Date())

/** Where they start (% from the left); each has its own pace. */
const FALLING = [12, 30, 52, 71, 90]
const falling = (holiday ? HOLIDAYS[holiday] : SEASONS[season]).map((char, i) => ({ char, left: FALLING[i], i }))
const FLYERS = [
  { top: 18, delay: 0, char: '🦋' },
  { top: 52, delay: -9, char: '🦋' },
  { top: 78, delay: -17, char: '🐝' },
]
</script>

<template>
  <div class="weather">
    <span v-for="p in falling" :key="p.i" class="fall" aria-hidden="true" :style="{ left: `${p.left}%`, '--i': p.i }">
      <EmojiArt :char="p.char" />
    </span>
    <button
      v-if="visitor && guest"
      type="button"
      class="guest"
      :class="{ 'guest--hello': hello }"
      :style="{ top: `${guestTop}%` }"
      :aria-label="t('hub.visitor')"
      @click="greetGuest"
    >
      <EmojiArt :char="guest" />
      <i v-if="hello" class="heart">💛</i>
    </button>
    <span v-for="(b, i) in FLYERS" :key="i" class="fly" aria-hidden="true" :style="{ top: `${b.top}%`, '--d': `${b.delay}s` }">
      <EmojiArt class="wings" :char="b.char" />
    </span>
  </div>
</template>

<style scoped>
.weather {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  z-index: 1;
}
.fall {
  position: absolute;
  top: -30px;
  font-size: 18px;
  opacity: 0.85;
  animation: fall calc(11s + var(--i) * 2.3s) linear infinite;
  animation-delay: calc(var(--i) * -3.1s);
}
.fly {
  display: none;
  position: absolute;
  left: -40px;
  font-size: 22px;
  animation: cross 26s linear infinite;
  animation-delay: var(--d);
}
.wings {
  display: block;
  animation: flutter 0.9s ease-in-out infinite alternate;
}
/* butterflies and the bee are out by day only */
:global(.bz[data-daytime='morning']) .fly,
:global(.bz[data-daytime='day']) .fly {
  display: block;
}
/* the visitor strolls across the meadow, stopping nowhere, and can be tapped */
.guest {
  position: absolute;
  left: -50px;
  font-size: 30px;
  line-height: 1;
  pointer-events: auto;
  animation: stroll 38s linear 2s both;
}
.guest--hello {
  scale: 1.25;
  transition: scale 0.3s var(--bz-spring);
}
.heart {
  position: absolute;
  left: 50%;
  top: -14px;
  font-size: 18px;
  font-style: normal;
  animation: float-up 1.2s ease-out forwards;
}
@keyframes stroll {
  to {
    transform: translateX(calc(100vw + 100px));
  }
}
@keyframes float-up {
  to {
    transform: translateY(-34px);
    opacity: 0;
  }
}
@keyframes fall {
  to {
    transform: translate(40px, 110vh) rotate(300deg);
  }
}
@keyframes cross {
  0% {
    transform: translate(0, 0);
  }
  25% {
    transform: translate(28vw, -26px);
  }
  50% {
    transform: translate(58vw, 18px);
  }
  75% {
    transform: translate(86vw, -20px);
  }
  100% {
    transform: translate(120vw, 6px);
  }
}
@keyframes flutter {
  to {
    transform: translateY(-5px) rotate(12deg) scaleX(0.78);
  }
}
</style>
