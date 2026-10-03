<script setup>
import { computed } from 'vue'
import { t } from '../../i18n'

/**
 * An analogue clock face showing `hour`:`minute`, for reading the time (Óra és idő).
 * The short hand is the hour (it creeps on with the minutes), the long one the minutes.
 */
const props = defineProps({
  hour: { type: Number, required: true },
  minute: { type: Number, required: true },
})

const hourAngle = computed(() => (props.hour % 12) * 30 + props.minute * 0.5)
const minuteAngle = computed(() => props.minute * 6)
const numbers = Array.from({ length: 12 }, (_, i) => {
  const a = ((i + 1) * 30 * Math.PI) / 180
  return { n: i + 1, x: 100 + 74 * Math.sin(a), y: 100 - 74 * Math.cos(a) }
})
const ticks = Array.from({ length: 60 }, (_, i) => i)
</script>

<template>
  <svg class="clock" viewBox="0 0 200 200" role="img" :aria-label="t('clock.label', { hour, minute })">
    <circle cx="100" cy="100" r="96" class="rim" />
    <circle cx="100" cy="100" r="88" class="face" />
    <line
      v-for="i in ticks"
      :key="i"
      x1="100"
      :y1="i % 5 === 0 ? 14 : 16"
      x2="100"
      :y2="i % 5 === 0 ? 24 : 20"
      class="tick"
      :class="{ 'tick--hour': i % 5 === 0 }"
      :transform="`rotate(${i * 6} 100 100)`"
    />
    <text v-for="p in numbers" :key="p.n" :x="p.x" :y="p.y" class="num">{{ p.n }}</text>
    <line x1="100" y1="100" x2="100" y2="54" class="hand hand--hour" :transform="`rotate(${hourAngle} 100 100)`" />
    <line x1="100" y1="100" x2="100" y2="30" class="hand hand--minute" :transform="`rotate(${minuteAngle} 100 100)`" />
    <circle cx="100" cy="100" r="6" class="pin" />
  </svg>
</template>

<style scoped>
.clock {
  width: min(62vw, 240px);
  height: auto;
  filter: drop-shadow(0 6px 0 rgba(59, 31, 74, 0.18));
}
.rim {
  fill: var(--bz-coral);
}
.face {
  fill: #fff;
}
.tick {
  stroke: #8f7ba3;
  stroke-width: 1.4;
}
.tick--hour {
  stroke: #3b1f4a;
  stroke-width: 2.6;
}
.num {
  fill: #3b1f4a;
  font-size: 17px;
  font-weight: 800;
  text-anchor: middle;
  dominant-baseline: central;
}
.hand {
  stroke-linecap: round;
}
.hand--hour {
  stroke: #3b1f4a;
  stroke-width: 7;
}
.hand--minute {
  stroke: #c4372b;
  stroke-width: 4.5;
}
.pin {
  fill: #3b1f4a;
}
</style>
