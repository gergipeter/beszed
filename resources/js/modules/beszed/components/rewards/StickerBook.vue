<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useModuleContext } from '../../composables/useModuleContext'
import { useTimers } from '../../composables/useTimers'
import { usePinchZoom } from '../../composables/usePinchZoom'
import { ICONS } from '../../config/icons'
import { formatDate, t } from '../../i18n'
import { sparkle } from '../../services/audio/sfx'
import { burst } from '../../services/effects/burst'
import { flyTo } from '../../services/effects/fly'
import { useGuideStore } from '../../stores/guide'
import BzButton from '../ui/BzButton.vue'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * The sticker book. Every sticker has its own place on a page (the pages swipe
 * sideways); a missing one is a dashed shadow that tells how to earn it. A
 * sticker earned since the child last looked arrives as a shiny wrapped pack:
 * tap it, it shakes, bursts open, and after "Beragasztom!" the sticker flies
 * into its place. Tapping a stuck sticker makes it peel up and wiggle while
 * Csillám says its name. What has been unwrapped is remembered on the device.
 */
const props = defineProps({
  /** @type {import('vue').PropType<import('../../types').Badge[]>} every sticker, earned or not, in album order */
  badges: { type: Array, required: true },
})

const { childId } = useModuleContext()
const guide = useGuideStore()
const { later } = useTimers()

// --- pinch zoom for the whole book ---
const bookContainer = ref(null)
const { scale, resetZoom } = usePinchZoom(bookContainer)

// --- unwrapped stickers (the rest of the earned ones are packs) ---
const seenKey = () => `beszed.stickers.seen.${childId.value}`
const seen = ref(new Set())
try {
  seen.value = new Set(JSON.parse(localStorage.getItem(seenKey()) ?? '[]'))
} catch {
  /* private mode: every earned sticker is a pack once per visit */
}
function remember(id) {
  seen.value = new Set([...seen.value, id])
  try {
    localStorage.setItem(seenKey(), JSON.stringify([...seen.value]))
  } catch {
    /* not kept on this device */
  }
}
const earned = computed(() => props.badges.filter(b => b.earned_at))
const packs = computed(() => earned.value.filter(b => !seen.value.has(b.id)))
const stuck = b => b.earned_at && seen.value.has(b.id)

// --- the pages ---
const book = ref(null)
const perPage = ref(6)
const page = ref(0)
let observer = null
onMounted(() => {
  observer = new ResizeObserver(([entry]) => (perPage.value = entry.contentRect.width < 520 ? 6 : 12))
  observer.observe(book.value)
})
onBeforeUnmount(() => observer?.disconnect())
const pages = computed(() => {
  const out = []
  for (let i = 0; i < props.badges.length; i += perPage.value) out.push(props.badges.slice(i, i + perPage.value))
  return out
})
function onScroll() {
  const el = book.value
  if (el) page.value = Math.round(el.scrollLeft / el.clientWidth)
}
function turnTo(n, smooth = true) {
  const el = book.value
  el?.scrollTo({ left: n * el.clientWidth, behavior: smooth ? 'smooth' : 'auto' })
}
/** A slightly different tilt for every sticker, the same each time. */
const tilt = i => ((i * 37) % 11) - 5

// --- opening a pack ---
const opening = ref(null)
/** 'shake' (the pack wobbles) → 'open' (the sticker is shown big) */
const phase = ref('')
const reveal = ref(null)

function openPack(badge) {
  if (opening.value) return
  opening.value = badge
  phase.value = 'shake'
  later(async () => {
    phase.value = 'open'
    sparkle()
    guide.celebrate()
    guide.speak([t('rewards.packSpeech', { name: badge.name })])
    await nextTick()
    const box = reveal.value?.getBoundingClientRect()
    if (box) burst({ x: box.left + box.width / 2, y: box.top + box.height / 2 }, { pieces: 18, reach: 140 })
  }, 650)
}

/** "Beragasztom!": to its page, then the sticker flies into its place. */
async function stickIt() {
  const badge = opening.value
  if (!badge || phase.value !== 'open') return
  phase.value = 'fly'
  const index = props.badges.findIndex(b => b.id === badge.id)
  turnTo(Math.floor(index / perPage.value))
  await new Promise(resolve => later(resolve, 380))
  const flight = flyTo(reveal.value, book.value?.querySelector(`[data-sticker="${badge.id}"]`))
  flying.value = true // its copy is in the air now
  await flight
  flying.value = false
  remember(badge.id)
  fresh.value = badge.id
  opening.value = null
  phase.value = ''
  later(() => (fresh.value = null), 900)
}
const fresh = ref(null)
const flying = ref(false)

// --- tapping a place ---
const wiggling = ref(null)
function tap(badge) {
  wiggling.value = null
  requestAnimationFrame(() => (wiggling.value = badge.id))
  later(() => {
    if (wiggling.value === badge.id) wiggling.value = null
  }, 700)
  if (stuck(badge)) guide.speak([badge.name])
  else if (badge.earned_at) openPack(badge)
  else guide.speak([badge.hint])
}
</script>

<template>
  <div ref="bookContainer" class="sticker-book" :style="{ '--bz-scale': scale }">
    <div class="progress">
      <b class="count"><EmojiArt :char="ICONS.star" /> {{ t('rewards.stickers', { count: earned.length, total: badges.length }) }}</b>
      <span class="bar" aria-hidden="true"><i :style="{ transform: `scaleX(${badges.length ? earned.length / badges.length : 0})` }" /></span>
    </div>

    <section v-if="packs.length" class="packs" :aria-label="t('rewards.packsTitle')">
      <p class="packs-title">{{ t('rewards.packsTitle') }}</p>
      <div class="pack-row">
        <button
          v-for="(badge, i) in packs"
          :key="badge.id"
          type="button"
          class="pack"
          :class="{ 'pack--opening': opening?.id === badge.id }"
          :style="{ '--i': i }"
          :aria-label="t('rewards.openPack')"
          @click="openPack(badge)"
        >
          <span class="pack-shine" aria-hidden="true" />
          <EmojiArt class="pack-icon" :char="ICONS.gift" />
        </button>
      </div>
    </section>

    <div ref="book" class="book" @scroll.passive="onScroll">
      <div v-for="(sheet, p) in pages" :key="p" class="page" :aria-label="t('rewards.page', { n: p + 1, total: pages.length })" role="group">
        <button
          v-for="(badge, j) in sheet"
          :key="badge.id"
          type="button"
          class="place"
          :class="{
            'place--stuck': stuck(badge),
            'place--fresh': fresh === badge.id,
            'place--wiggle': wiggling === badge.id,
          }"
          :data-sticker="badge.id"
          :style="{ '--tilt': `${tilt(p * perPage + j)}deg` }"
          :aria-label="stuck(badge) ? `${badge.name}, ${t('rewards.earnedOn', { date: formatDate(badge.earned_at) })}` : badge.earned_at ? t('rewards.openPack') : `${badge.name}: ${badge.hint}`"
          @click="tap(badge)"
        >
          <span class="spot">
            <EmojiArt class="sticker" :char="badge.emoji" />
            <b v-if="!badge.earned_at" class="ask" aria-hidden="true">?</b>
          </span>
          <small class="place-name">{{ stuck(badge) ? badge.name : badge.earned_at ? t('rewards.inPack') : badge.hint }}</small>
        </button>
      </div>
    </div>

    <div v-if="pages.length > 1" class="dots">
      <button
        v-for="(sheet, p) in pages"
        :key="p"
        type="button"
        class="dot"
        :class="{ 'dot--on': page === p }"
        :aria-label="t('rewards.page', { n: p + 1, total: pages.length })"
        @click="turnTo(p)"
      />
    </div>

    <!-- a pack being opened: the sticker, big, over everything -->
    <Transition name="reveal">
      <div v-if="opening" class="overlay" :class="`overlay--${phase}`" @click="stickIt">
        <div class="opened">
          <div v-if="phase === 'shake'" class="pack pack--big pack--opening" aria-hidden="true">
            <span class="pack-shine" />
            <EmojiArt class="pack-icon" :char="ICONS.gift" />
          </div>
          <template v-else>
            <p class="opened-title">{{ t('rewards.newSticker') }}</p>
            <div ref="reveal" class="revealed" :class="{ 'revealed--gone': flying }"><EmojiArt class="sticker" :char="opening.emoji" /></div>
            <p class="opened-name">{{ opening.name }}</p>
            <BzButton variant="primary" :icon="ICONS.check" :disabled="phase !== 'open'" @click.stop="stickIt">{{ t('rewards.stickIt') }}</BzButton>
          </template>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
:root {
  --bz-scale: 1;
}

.sticker-book {
  display: flex;
  flex-direction: column;
  gap: 12px;
  --bz-scale: 1;
}
.progress {
  display: flex;
  align-items: center;
  gap: 12px;
}
.count {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: var(--bz-text-md);
  white-space: nowrap;
}
.bar {
  flex: 1;
  height: 14px;
  overflow: hidden;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
}
.bar i {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, var(--bz-sun), var(--bz-coral));
  transform-origin: left;
  transition: transform 0.6s var(--bz-spring);
}

/* --- packs: shiny foil, wobbling to be opened --- */
.packs-title {
  margin: 0 0 6px;
  font-weight: 800;
  font-size: var(--bz-text-md);
}
.pack-row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.pack {
  position: relative;
  display: grid;
  place-items: center;
  width: 78px;
  height: 96px;
  overflow: hidden;
  border-radius: var(--bz-radius-sm);
  background: linear-gradient(135deg, #ff9ec7, #b9a6ff 45%, #7cc7ff 70%, #9ee6c9);
  box-shadow:
    inset 0 0 0 3px rgba(255, 255, 255, 0.6),
    var(--bz-shadow),
    0 8px 20px rgba(185, 166, 255, 0.4);
  animation: nudge 3.2s cubic-bezier(0.68, -0.55, 0.27, 1.55) infinite, bounce 1.6s ease-in-out infinite;
  animation-delay: calc(var(--i, 0) * -0.4s);
}
/* a zigzag, torn-off top */
.pack::before {
  content: '';
  position: absolute;
  inset: 0 0 auto;
  height: 10px;
  background: radial-gradient(circle at 50% 100%, transparent 5px, rgba(255, 255, 255, 0.7) 5.5px) 0 0 / 12px 10px repeat-x;
}
.pack-shine {
  position: absolute;
  inset: -40% auto -40% -60%;
  width: 40%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.75), transparent);
  transform: rotate(20deg);
  animation: shine 2.6s ease-in-out infinite;
}
.pack-icon {
  position: relative;
  font-size: 40px;
}
.pack--opening {
  animation: rattle 0.13s linear 5;
}
.pack--big {
  width: 150px;
  height: 184px;
}
.pack--big .pack-icon {
  font-size: 80px;
}

/* --- the book: pages side by side, swiped one at a time --- */
.book {
  display: flex;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
  border-radius: var(--bz-radius-lg);
  background: #fff7e6;
  box-shadow:
    inset 0 0 0 5px color-mix(in srgb, var(--bz-bark) 35%, transparent),
    var(--bz-shadow-lg);
  transform: scale(var(--bz-scale));
  transform-origin: center;
  transition: transform 0.1s linear;
}
.book::-webkit-scrollbar {
  display: none;
}
.page {
  flex: 0 0 100%;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
  align-content: start;
  gap: 14px 8px;
  min-height: 330px;
  padding: 22px 16px 26px;
  scroll-snap-align: start;
  /* the page: warm paper with a faint dotted grid */
  background:
    radial-gradient(circle, rgba(160, 110, 70, 0.14) 1px, transparent 1.5px) 0 0 / 18px 18px,
    linear-gradient(90deg, rgba(160, 110, 70, 0.12), transparent 6%, transparent 94%, rgba(160, 110, 70, 0.12));
  color: #3b1f4a;
}
.place {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  text-align: center;
}
.spot {
  position: relative;
  display: grid;
  place-items: center;
  width: 78px;
  height: 78px;
  border: 3px dashed rgba(120, 90, 140, 0.35);
  border-radius: 50%;
}
.sticker {
  font-size: 50px;
  line-height: 1;
  filter: brightness(0) opacity(0.12);
}
.ask {
  position: absolute;
  font-size: 26px;
  font-weight: 800;
  color: rgba(120, 90, 140, 0.55);
}
/* a stuck sticker: die-cut with a white edge and a soft shadow, a little askew */
.place--stuck .spot {
  border-color: transparent;
  transform: rotate(var(--tilt));
}
.place--stuck .sticker {
  font-size: 56px;
  filter: drop-shadow(3px 0 0 #fff) drop-shadow(-3px 0 0 #fff) drop-shadow(0 3px 0 #fff) drop-shadow(0 -3px 0 #fff)
    drop-shadow(0 5px 3px rgba(59, 31, 74, 0.28));
}
.place-name {
  max-width: 100%;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.15;
  color: rgba(59, 31, 74, 0.6);
}
.place--stuck .place-name {
  font-size: 14px;
  font-weight: 800;
  color: #3b1f4a;
}
.place--wiggle .spot {
  animation: peel 0.65s var(--bz-spring);
}
.place--fresh .spot {
  animation: stick 0.8s var(--bz-spring);
}
.dots {
  display: flex;
  justify-content: center;
  gap: 8px;
}
.dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--bz-guide) 40%, transparent);
  transition: transform 0.3s var(--bz-spring);
}
.dot--on {
  background: var(--bz-sun);
  transform: scale(1.3);
}

/* --- the opened pack, over the page --- */
.overlay {
  position: fixed;
  inset: 0;
  z-index: 900;
  display: grid;
  place-items: center;
  padding: 24px;
  background: radial-gradient(circle at 50% 45%, rgba(255, 246, 200, 0.55), rgba(30, 20, 60, 0.72));
}
.overlay--fly {
  background: transparent;
  transition: background 0.4s;
}
.opened {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  color: #fff;
  text-align: center;
}
.opened-title {
  margin: 0;
  font-size: var(--bz-text-lg);
  font-weight: 800;
  text-shadow: 0 3px 0 rgba(0, 0, 0, 0.25);
}
.opened-name {
  margin: 0;
  font-size: 30px;
  font-weight: 800;
  text-shadow: 0 3px 0 rgba(0, 0, 0, 0.25);
}
.revealed {
  display: grid;
  place-items: center;
  width: 190px;
  height: 190px;
  animation: popout 0.7s var(--bz-spring) both;
}
.revealed .sticker {
  font-size: 150px;
  filter: drop-shadow(6px 0 0 #fff) drop-shadow(-6px 0 0 #fff) drop-shadow(0 6px 0 #fff) drop-shadow(0 -6px 0 #fff)
    drop-shadow(0 10px 8px rgba(0, 0, 0, 0.3));
}
.revealed--gone {
  visibility: hidden;
}
.overlay--fly .opened > :not(.revealed) {
  opacity: 0;
  transition: opacity 0.2s;
}
.reveal-enter-active {
  transition: opacity 0.25s;
}
.reveal-leave-active {
  transition: opacity 0.2s;
}
.reveal-enter-from,
.reveal-leave-to {
  opacity: 0;
}

@keyframes nudge {
  0%,
  78%,
  100% {
    transform: rotate(0deg) translateY(0);
  }
  80% {
    transform: rotate(-8deg) translateY(-4px);
  }
  85% {
    transform: rotate(8deg) translateY(2px);
  }
  90% {
    transform: rotate(-5deg) translateY(-2px);
  }
  95% {
    transform: rotate(4deg) translateY(1px);
  }
}

@keyframes shine {
  0%,
  55% {
    translate: 0 0;
  }
  100% {
    translate: 420% 0;
  }
}

@keyframes rattle {
  0%, 100% {
    transform: rotate(0deg) scale(1) skew(0deg);
  }
  12.5% {
    transform: rotate(-12deg) scale(1.08) skew(2deg);
  }
  25% {
    transform: rotate(12deg) scale(1.08) skew(-2deg);
  }
  37.5% {
    transform: rotate(-10deg) scale(1.06) skew(1deg);
  }
  50% {
    transform: rotate(10deg) scale(1.06) skew(-1deg);
  }
  62.5% {
    transform: rotate(-6deg) scale(1.03) skew(0.5deg);
  }
  75% {
    transform: rotate(6deg) scale(1.03) skew(-0.5deg);
  }
}

@keyframes popout {
  0% {
    transform: scale(0) rotate(-45deg);
    opacity: 0;
  }
  50% {
    transform: scale(1.15) rotate(15deg);
  }
  100% {
    transform: scale(1) rotate(0deg);
    opacity: 1;
  }
}

@keyframes peel {
  0% {
    transform: rotate(var(--tilt)) translateY(0) scale(1);
  }
  25% {
    transform: rotate(calc(var(--tilt) - 15deg)) translateY(-14px) scale(1.15);
  }
  50% {
    transform: rotate(calc(var(--tilt) + 12deg)) translateY(-8px) scale(1.12);
  }
  75% {
    transform: rotate(calc(var(--tilt) - 8deg)) translateY(-3px) scale(1.05);
  }
  100% {
    transform: rotate(var(--tilt)) translateY(0) scale(1);
  }
}

@keyframes stick {
  0% {
    transform: rotate(var(--tilt)) scale(1.8) translateY(-30px);
    opacity: 0;
  }
  50% {
    transform: rotate(var(--tilt)) scale(1.1);
  }
  100% {
    transform: rotate(var(--tilt)) scale(1);
    opacity: 1;
  }
}

@keyframes bounce {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-6px);
  }
}
</style>
