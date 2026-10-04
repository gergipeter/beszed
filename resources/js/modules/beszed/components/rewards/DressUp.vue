<script setup>
import { computed, ref } from 'vue'
import { useDrag } from '../../composables/useDrag'
import { useModuleContext } from '../../composables/useModuleContext'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { sparkle } from '../../services/audio/sfx'
import { burst } from '../../services/effects/burst'
import { useGuideStore } from '../../stores/guide'
import { useRewardsStore } from '../../stores/rewards'
import { errorMessage } from '../../utils/errors'
import { pick } from '../../utils/random'
import CsillamAvatar from '../guide/CsillamAvatar.vue'
import { ACCESSORY_ART } from '../guide/accessories'
import BzButton from '../ui/BzButton.vue'
import BzNotice from '../ui/BzNotice.vue'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * Csillám's dressing room: she stands big in front of a mirror; under it, round
 * tabs pick a rack (hats, glasses, scarves, things to hold, mane colours).
 * Drag something up onto her (the mirror pulls it in) or tap it to put it on;
 * tap what she wears to take it off. She
 * twirls and says how she likes it. "Meglepetés!" picks a whole outfit.
 * Locked items tell at which level they come.
 */
const { childId, guideName } = useModuleContext()
const rewards = useRewardsStore()
const guide = useGuideStore()
const { later } = useTimers()

const SHELVES = [
  { slot: 'head', icon: '🎩' },
  { slot: 'face', icon: '🕶️' },
  { slot: 'neck', icon: '🧣' },
  { slot: 'extra', icon: '🎈' },
  { slot: 'mane', icon: '🎨' },
]
/** The "rewards.compliment.c{x}" texts she says after a change. */
const COMPLIMENTS = [1, 2, 3, 4, 5]

const shelves = computed(() =>
  SHELVES.map(s => ({ ...s, items: rewards.accessories.filter(a => a.slot === s.slot) })).filter(s => s.items.length),
)
const worn = computed(() => rewards.worn)
/** The rack on show. */
const slot = ref(SHELVES[0].slot)
const shelf = computed(() => shelves.value.find(s => s.slot === slot.value) ?? shelves.value[0])
const anyWorn = computed(() => Object.keys(worn.value).length > 0)
const nothingYet = computed(() => !rewards.accessories.some(a => a.unlocked))
const message = ref('')
/** Her little show after a change: 'pose' (a hop and a turn) or 'spin' (a whole outfit). */
const show = ref('')
const mirror = ref(null)

function perform(kind) {
  show.value = ''
  requestAnimationFrame(() => (show.value = kind))
  later(() => (show.value = ''), 900)
}

/** Wears `item` (or takes it off when she wears it already). */
async function toggle(item, { quiet = false } = {}) {
  if (!item.unlocked) {
    guide.speakUi([t('rewards.lockedAt', { level: item.level })])
    return false
  }
  message.value = ''
  const off = worn.value[item.slot] === item.id
  try {
    await rewards.wear(childId.value, item.slot, off ? null : item.id)
  } catch (e) {
    message.value = errorMessage(e, t('rewards.wearFailed'))
    return false
  }
  if (quiet) return true
  if (off) {
    guide.speakUi([t('rewards.tookOff', { name: item.name })])
    return true
  }
  guide.celebrate()
  sparkle()
  perform('pose')
  const box = mirror.value?.getBoundingClientRect()
  if (box) burst({ x: box.left + box.width / 2, y: box.top + box.height * 0.4 }, { pieces: 14, reach: 110 })
  guide.speak([item.name, t(`rewards.compliment.c${pick(COMPLIMENTS)}`)])
  return true
}

/** Dropped onto the mirror: put it on (dropping what she wears already changes nothing). */
const stage = ref(null)
const drag = useDrag({
  root: stage,
  onDrop: item => (worn.value[item.slot] === item.id ? true : toggle(item)),
})
function grab(event, item) {
  if (item.unlocked) drag.start(event, item)
}

/** A whole surprise outfit from what's unlocked: something in most slots, a new mane colour sometimes. */
async function surprise() {
  const outfit = shelves.value
    .map(shelf => {
      const open = shelf.items.filter(i => i.unlocked)
      const skip = shelf.slot === 'mane' ? 0.5 : 0.25
      return { slot: shelf.slot, item: open.length && Math.random() > skip ? pick(open) : null }
    })
    .filter(o => (worn.value[o.slot] ?? null) !== (o.item?.id ?? null))
  message.value = ''
  perform('spin')
  sparkle()
  try {
    for (const o of outfit) await rewards.wear(childId.value, o.slot, o.item?.id ?? null)
  } catch (e) {
    message.value = errorMessage(e, t('rewards.wearFailed'))
    return
  }
  guide.celebrate()
  guide.speakUi([t('rewards.surpriseSpeech')])
}

async function undress() {
  try {
    for (const slot of Object.keys(worn.value)) await rewards.wear(childId.value, slot, null)
  } catch (e) {
    message.value = errorMessage(e, t('rewards.wearFailed'))
    return
  }
  perform('spin')
  guide.speakUi([t('rewards.undressSpeech')])
}

const swatch = item => ACCESSORY_ART[item.id]?.palette ?? []
</script>

<template>
  <div ref="stage" class="dressing">
    <div
      ref="mirror"
      class="mirror"
      :class="{ 'mirror--over': drag.over.value === 'csillam' }"
      data-drop="csillam"
      role="img"
      :aria-label="t('rewards.mirror', { guide: guideName })"
    >
      <span class="glint" aria-hidden="true" />
      <div class="me" :class="show && `me--${show}`"><CsillamAvatar :name="guideName" /></div>
      <span class="rug" aria-hidden="true" />
    </div>

    <div class="actions">
      <BzButton variant="primary" :icon="ICONS.dice" :disabled="nothingYet" @click="surprise">{{ t('rewards.surprise') }}</BzButton>
      <BzButton :icon="ICONS.basket" :disabled="!anyWorn" @click="undress">{{ t('rewards.undress') }}</BzButton>
    </div>
    <p class="hint">{{ nothingYet ? t('rewards.wardrobeEmpty') : t('rewards.wardrobeHint') }}</p>
    <BzNotice v-if="message" tone="warn">{{ message }}</BzNotice>

    <div class="slots" role="tablist" :aria-label="t('rewards.wardrobe')">
      <button
        v-for="s in shelves"
        :key="s.slot"
        type="button"
        role="tab"
        class="slot-tab"
        :class="{ 'slot-tab--on': shelf?.slot === s.slot }"
        :aria-selected="shelf?.slot === s.slot"
        :aria-label="t(`rewards.slots.${s.slot}`)"
        @click="slot = s.slot"
      >
        <EmojiArt :char="s.icon" />
        <b v-if="worn[s.slot]" class="worn-dot" aria-hidden="true" />
      </button>
    </div>

    <section v-if="shelf" class="shelf" role="tabpanel" :aria-label="t(`rewards.slots.${shelf.slot}`)">
      <h3 class="shelf-title">{{ t(`rewards.slots.${shelf.slot}`) }}</h3>
      <div class="rack">
        <button
          v-for="item in shelf.items"
          :key="item.id"
          type="button"
          class="hanger"
          :class="{ 'hanger--on': worn[item.slot] === item.id, 'hanger--locked': !item.unlocked, 'bz-draggable': item.unlocked }"
          :aria-pressed="worn[item.slot] === item.id"
          :aria-label="item.unlocked ? item.name : `${item.name}: ${t('rewards.unlockAt', { level: item.level })}`"
          @pointerdown="grab($event, item)"
          @click="toggle(item)"
        >
          <span class="hook" aria-hidden="true" />
          <span v-if="shelf.slot === 'mane'" class="swatch" aria-hidden="true">
            <i v-for="(c, i) in swatch(item)" :key="i" :style="{ background: c }" />
          </span>
          <EmojiArt v-else class="hanger-art" :char="item.emoji" />
          <small class="hanger-name">{{ item.unlocked ? item.name : t('rewards.unlockAt', { level: item.level }) }}</small>
          <EmojiArt v-if="!item.unlocked" class="lock" :char="ICONS.lock" />
          <EmojiArt v-else-if="worn[item.slot] === item.id" class="check" :char="ICONS.check" />
        </button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.dressing {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
/* the mirror: an oval with a golden frame, a soft light, a rug to stand on */
.mirror {
  position: relative;
  align-self: center;
  display: grid;
  place-items: end center;
  width: min(100%, 320px);
  aspect-ratio: 5 / 6;
  padding-bottom: 18px;
  border: 12px solid transparent;
  border-radius: 50% / 46%;
  background:
    radial-gradient(70% 55% at 50% 40%, color-mix(in srgb, var(--bz-sky-bottom) 70%, #fff), color-mix(in srgb, var(--bz-sky-top) 45%, var(--bz-card))) padding-box,
    linear-gradient(135deg, #ffe89a, #d8a23a 35%, #fff2c2 55%, #c98f2c) border-box;
  box-shadow:
    0 10px 0 rgba(59, 31, 74, 0.18),
    inset 0 0 30px rgba(255, 255, 255, 0.6);
  transition: transform 0.3s var(--bz-spring);
}
/* something dragged near: the mirror leans in to take it */
.mirror--over {
  transform: scale(1.04);
  box-shadow:
    0 0 0 6px var(--bz-sun),
    0 10px 0 rgba(59, 31, 74, 0.18);
}
.glint {
  position: absolute;
  top: 10%;
  left: 16%;
  width: 18%;
  height: 34%;
  border-radius: 50%;
  background: linear-gradient(160deg, rgba(255, 255, 255, 0.8), transparent 70%);
  transform: rotate(18deg);
  pointer-events: none;
}
.me {
  position: relative;
  z-index: 1;
  width: 72%;
}
.me--pose {
  animation: pose 0.8s var(--bz-spring);
}
.me--spin {
  animation: spin 0.8s cubic-bezier(0.4, 0, 0.3, 1);
}
.rug {
  position: absolute;
  bottom: 8%;
  width: 70%;
  height: 12%;
  border-radius: 50%;
  background: radial-gradient(closest-side, color-mix(in srgb, var(--bz-coral) 55%, #fff), color-mix(in srgb, var(--bz-coral) 25%, transparent));
}
.actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
}
.hint {
  margin: 0;
  text-align: center;
  color: var(--bz-muted);
}
/* the racks: one round tab each, a dot when she wears something from it */
.slots {
  display: flex;
  justify-content: center;
  gap: 10px;
}
.slot-tab {
  position: relative;
  display: grid;
  place-items: center;
  width: 58px;
  height: 58px;
  border: 4px solid transparent;
  border-radius: 50%;
  background: var(--bz-card);
  font-size: 30px;
  box-shadow: var(--bz-shadow);
  transition: transform 0.38s var(--bz-spring);
}
.slot-tab:active {
  transform: scale(0.9);
  transition-duration: 0.07s;
}
.slot-tab--on {
  border-color: var(--bz-sun);
  transform: translateY(-4px);
}
.worn-dot {
  position: absolute;
  right: 2px;
  bottom: 2px;
  width: 14px;
  height: 14px;
  border: 2px solid var(--bz-card);
  border-radius: 50%;
  background: var(--bz-leaf);
}
.shelf-title {
  margin: 0 0 4px;
  text-align: center;
  font-size: var(--bz-text-md);
}
/* a rack: a wooden rail the items hang from, scrolling sideways when full */
.rack {
  position: relative;
  display: flex;
  gap: 10px;
  padding: 16px 4px 10px;
  overflow-x: auto;
  scroll-snap-type: x proximity;
  background: linear-gradient(to bottom, var(--bz-bark) 0 7px, transparent 7px) 0 6px / 100% 13px no-repeat;
}
.hanger {
  position: relative;
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  width: 96px;
  padding: 12px 6px 8px;
  border: 4px solid transparent;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
  font-weight: 700;
  scroll-snap-align: start;
  /* sideways swipes scroll the rack; an upward drag carries the item to the mirror */
  touch-action: pan-x;
  transform-origin: 50% -12px;
  transition: transform 0.38s var(--bz-spring);
}
.hanger:active {
  transform: rotate(-4deg) scale(0.95);
  transition-duration: 0.08s;
}
/* the hook it hangs from */
.hook {
  position: absolute;
  top: -12px;
  left: 50%;
  width: 16px;
  height: 14px;
  border: 3px solid var(--bz-guide);
  border-bottom: 0;
  border-radius: 10px 10px 0 0;
  transform: translateX(-50%);
}
.hanger--on {
  border-color: var(--bz-leaf);
  background: color-mix(in srgb, var(--bz-leaf) 16%, var(--bz-card));
}
.hanger--locked {
  cursor: default;
}
.hanger--locked .hanger-art,
.hanger--locked .swatch {
  filter: var(--bz-silhouette);
  opacity: 0.35;
}
.hanger-art {
  font-size: 44px;
  line-height: 1.05;
}
.hanger-name {
  font-size: 13px;
  line-height: 1.15;
  text-align: center;
}
.hanger--locked .hanger-name {
  color: var(--bz-muted);
}
/* a mane colour: four dabs of paint */
.swatch {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 3px;
  width: 44px;
  height: 44px;
}
.swatch i {
  border-radius: 50%;
  box-shadow: inset 0 -2px 0 rgba(0, 0, 0, 0.12);
}
.lock,
.check {
  position: absolute;
  top: 6px;
  right: 6px;
  font-size: 18px;
}
@keyframes pose {
  30% {
    transform: translateY(-14%) rotate(-6deg) scale(1.04);
  }
  60% {
    transform: translateY(0) rotate(4deg);
  }
}
@keyframes spin {
  50% {
    transform: scale(0.9) rotateY(180deg);
  }
  to {
    transform: rotateY(360deg);
  }
}
</style>
