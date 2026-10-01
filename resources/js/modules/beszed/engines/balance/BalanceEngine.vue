<script setup>
import { computed, ref } from 'vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useTimers } from '../../composables/useTimers'
import { t } from '../../i18n'
import { beep, tone } from '../../services/audio/sfx'
import { buzz } from '../../services/touch/feel'
import { engineEmits, engineProps } from '../contract'

/**
 * Mérleg: a balance with items in the left pan. The child taps items from the
 * tray into the right pan (and taps them in the pan to take them back) until the
 * beam is level. The beam tilts with a springy overshoot and the pans stay upright;
 * it glows when it balances. Mistakes are part of play, so only the win is
 * reported, graded by how often the right pan ended up heavier (tries 1–3).
 * data: BalanceData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const MAX_TILT = 16

/** Items already in the right pan that cannot be taken out, and the ones the child added. */
const fixed = props.data.right
const added = ref(0)
const right = computed(() => fixed + added.value)
const left = props.data.left
const trayLeft = computed(() => props.data.tray - added.value)
/** Times the right pan got heavier than the left. */
const over = ref(0)
const done = ref(false)
const pop = ref(0)

const tilt = computed(() => Math.max(-MAX_TILT, Math.min(MAX_TILT, (right.value - left) * 4.5)))
const level = computed(() => right.value === left)

const { later } = useTimers()

function add() {
  if (props.locked || done.value || trayLeft.value <= 0) return
  added.value++
  pop.value++
  beep(300 + right.value * 30, 0.1)
  buzz(8)
  check()
}
function take() {
  if (props.locked || done.value || added.value <= 0) return
  added.value--
  beep(200, 0.08)
}

function check() {
  if (right.value > left) over.value++
  if (right.value !== left) return
  done.value = true
  // a little chord when the beam settles
  later(() => {
    tone(523.25, 0.5)
    tone(659.25, 0.5)
    tone(783.99, 0.6)
  }, 450)
  later(() => emit('answer', { correct: true, say: props.data.onCorrect, tries: over.value === 0 ? 1 : over.value <= 2 ? 2 : 3 }), 900)
}

/** Items drawn in a pan, at most 10. */
const leftItems = computed(() => Array.from({ length: left }, (_, i) => i))
const rightItems = computed(() => Array.from({ length: right.value }, (_, i) => i))
const tray = computed(() => Array.from({ length: trayLeft.value }, (_, i) => i))
</script>

<template>
  <div class="scale" :class="{ 'scale--level': level }" :style="{ '--a': `${tilt}deg`, '--na': `${-tilt}deg` }" data-no-feel>
    <div class="stand" aria-hidden="true">
      <span class="post" />
      <span class="foot" />
    </div>

    <div class="beam" aria-hidden="true">
      <span class="bar" />
      <span class="hub" />

      <div class="pan pan--left">
        <span class="strings" />
        <div class="dish">
          <TransitionGroup name="it" tag="div" class="items">
            <EmojiArt v-for="i in leftItems" :key="i" class="it" :char="data.emoji" />
          </TransitionGroup>
        </div>
      </div>

      <div class="pan pan--right">
        <span class="strings" />
        <div class="dish dish--mine" role="button" tabindex="0" :aria-label="t('balance.takeBack')" @click="take" @keydown.enter="take">
          <TransitionGroup name="it" tag="div" class="items">
            <EmojiArt v-for="i in rightItems" :key="i" class="it" :class="{ 'it--fixed': i < fixed }" :char="data.emoji" />
          </TransitionGroup>
        </div>
      </div>
    </div>

    <p class="counts" aria-live="polite">
      <b :class="{ 'is-level': level }">{{ left }}</b>
      <span class="sign">{{ level ? '＝' : right > left ? '<' : '>' }}</span>
      <b :class="{ 'is-level': level }">{{ right }}</b>
    </p>
  </div>

  <div class="tray" :class="{ 'tray--done': done }" data-no-feel>
    <p class="tray-hint">{{ t('balance.tray') }}</p>
    <TransitionGroup name="it" tag="div" class="tray-items">
      <button v-for="i in tray" :key="i" type="button" class="tray-it" :aria-label="t('balance.add')" @click="add">
        <EmojiArt :char="data.emoji" />
      </button>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.scale {
  position: relative;
  width: 100%;
  max-width: 520px;
  height: clamp(250px, 46vh, 330px);
  border-radius: 28px;
  background: linear-gradient(180deg, #fff6df, #ffe6b8);
  box-shadow: var(--bz-shadow-lg);
  overflow: hidden;
}
.stand {
  position: absolute;
  left: 50%;
  bottom: 0;
  width: 0;
  height: 100%;
}
.post {
  position: absolute;
  left: -7px;
  top: 24%;
  width: 14px;
  height: 70%;
  border-radius: 8px 8px 0 0;
  background: linear-gradient(90deg, #b98a5a, #d9ae7c, #b98a5a);
}
.foot {
  position: absolute;
  left: -48px;
  bottom: 0;
  width: 96px;
  height: 16px;
  border-radius: 14px 14px 0 0;
  background: #a9784a;
}
/* the beam turns about the hub; the pans hang from its ends and stay upright */
.beam {
  position: absolute;
  left: 7%;
  right: 7%;
  top: 22%;
  height: 0;
  transform: rotate(var(--a));
  transition: transform 0.9s cubic-bezier(0.3, 1.7, 0.45, 1);
}
.bar {
  position: absolute;
  left: 0;
  right: 0;
  top: -6px;
  height: 12px;
  border-radius: 8px;
  background: linear-gradient(180deg, #e0b688, #b98a5a);
  box-shadow: 0 3px 0 rgba(0, 0, 0, 0.15);
}
.hub {
  position: absolute;
  left: 50%;
  top: -11px;
  width: 22px;
  height: 22px;
  margin-left: -11px;
  border-radius: 50%;
  background: radial-gradient(circle at 35% 30%, #ffe27a, #e7a93a);
  box-shadow: 0 2px 0 rgba(0, 0, 0, 0.2);
  transition: box-shadow 0.4s, transform 0.4s var(--bz-spring);
}
.scale--level .hub {
  box-shadow: 0 0 0 6px rgba(255, 226, 122, 0.6), 0 0 22px 8px rgba(255, 210, 80, 0.75);
  transform: scale(1.25);
}
.pan {
  position: absolute;
  top: 0;
  width: 40%;
  transform: rotate(var(--na));
  transform-origin: 50% 0;
  transition: transform 0.9s cubic-bezier(0.3, 1.7, 0.45, 1);
}
.pan--left {
  left: -6%;
}
.pan--right {
  right: -6%;
}
.strings {
  display: block;
  height: 46px;
  margin: 0 auto;
  width: 70%;
  background:
    linear-gradient(to bottom right, transparent 49%, #8a6a48 49%, #8a6a48 52%, transparent 52%) left / 50% 100% no-repeat,
    linear-gradient(to bottom left, transparent 49%, #8a6a48 49%, #8a6a48 52%, transparent 52%) right / 50% 100% no-repeat;
  transform-origin: top;
}
.dish {
  position: relative;
  display: block;
  width: 100%;
  min-height: 74px;
  padding: 6px 6px 12px;
  border-radius: 6px 6px 46px 46px;
  background: linear-gradient(180deg, #f3f3fa, #c9cbe0);
  box-shadow: 0 5px 0 rgba(90, 90, 130, 0.3);
}
.dish--mine {
  cursor: pointer;
}
.items {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-content: flex-end;
  gap: 1px 2px;
  min-height: 56px;
  font-size: clamp(20px, 6vw, 30px);
  line-height: 1.05;
}
.it {
  display: inline-block;
}
.it--fixed {
  filter: saturate(0.75) brightness(0.96);
}
.counts {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 12px;
  display: flex;
  justify-content: center;
  align-items: baseline;
  gap: 14px;
  margin: 0;
  font-size: 30px;
  font-weight: 900;
  color: #6b4b2a;
}
.counts b {
  min-width: 1.4em;
  text-align: center;
  transition: color 0.3s, transform 0.4s var(--bz-spring);
}
.counts b.is-level {
  color: #2f9e44;
  transform: scale(1.18);
}
.sign {
  font-size: 26px;
}

.tray {
  width: 100%;
  max-width: 520px;
  padding: 10px 12px 14px;
  border-radius: 24px;
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
  transition: opacity 0.4s;
}
.tray--done {
  opacity: 0.55;
}
.tray-hint {
  margin: 0 0 6px;
  text-align: center;
  font-size: 14px;
  font-weight: 800;
  color: var(--bz-muted);
}
.tray-items {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
  min-height: 56px;
}
.tray-it {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: 16px;
  background: var(--bz-soft);
  font-size: 34px;
  line-height: 1;
  touch-action: manipulation;
  transition: transform 0.2s var(--bz-spring);
}
.tray-it:active {
  transform: scale(0.85);
}

/* items drop into a pan with a bounce, and pop out of the tray */
.it-enter-active {
  animation: drop 0.55s var(--bz-spring);
}
.it-leave-active {
  position: absolute;
  animation: pop-out 0.25s ease-in forwards;
}
.it-move {
  transition: transform 0.35s var(--bz-spring);
}
@keyframes drop {
  from {
    opacity: 0;
    transform: translateY(-46px) scale(0.6);
  }
  60% {
    transform: translateY(4px) scale(1.12, 0.9);
  }
}
@keyframes pop-out {
  to {
    opacity: 0;
    transform: scale(0.4);
  }
}
@media (prefers-reduced-motion: reduce) {
  .beam,
  .pan {
    transition-duration: 0.01s;
  }
}
</style>
