<script setup>
import { ref } from 'vue'
import { t } from '../../i18n'

/**
 * Pick a pálya by hand (Kirakó has 100): type a number or tap a jump. The choice
 * is applied to the next start, after which the adaptive level carries on from it.
 *
 * Shown as a small floating circle (bottom-left, out of the guide/caption's way) that
 * pops open a dev-menu-style panel, instead of sitting inline in the layout.
 */
const props = defineProps({
  max: { type: Number, required: true },
  current: { type: Number, default: null },
})
const emit = defineEmits(['pick'])

const open = ref(false)
const value = ref(null)
const jumps = [1, 10, 25, 50, 75, 100, 125, 150, 175, 200].filter(n => n <= props.max)


function go(n) {
  const level = Math.max(1, Math.min(props.max, Math.round(Number(n))))
  if (!Number.isFinite(level)) return
  value.value = level
  open.value = false
  emit('pick', level)
}
</script>

<template>
  <div class="lp">
    <button type="button" class="fab" :class="{ open }" @click="open = !open" :aria-label="t('levelPicker.title')">
      🎚️
    </button>
    <div v-if="open" class="backdrop" @click="open = false" />
    <div v-if="open" class="panel">
      <p class="panel-title">{{ current ? t('levelPicker.titleCurrent', { current }) : t('levelPicker.title') }} · {{ t('levelPicker.choose') }}</p>
      <form class="row" @submit.prevent="go(value)">
        <input v-model.number="value" type="number" inputmode="numeric" :min="1" :max="max" :placeholder="`1–${max}`" />
        <button type="submit" class="go">{{ t('levelPicker.start') }}</button>
      </form>
      <div class="jumps">
        <button v-for="n in jumps" :key="n" type="button" @click="go(n)">{{ n }}</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.lp {
  position: relative;
}
/* ---- the floating circle: tucked in a corner, out of the play area's flow ---- */
.fab {
  position: fixed;
  left: 14px;
  bottom: calc(14px + env(safe-area-inset-bottom, 0px));
  z-index: 20;
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--bz-card) 82%, transparent);
  box-shadow: var(--bz-shadow-sm);
  backdrop-filter: blur(6px);
  font-size: 20px;
  transition: transform 0.3s var(--bz-spring);
}
.fab:active {
  transform: scale(0.88);
}
.fab.open {
  background: var(--bz-sun);
}
.backdrop {
  position: fixed;
  inset: 0;
  z-index: 20;
  background: rgba(0, 0, 0, 0.25);
}
/* ---- the popup panel, anchored above the circle ---- */
.panel {
  position: fixed;
  left: 14px;
  bottom: calc(66px + env(safe-area-inset-bottom, 0px));
  z-index: 21;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  width: min(280px, calc(100vw - 28px));
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
}
.panel-title {
  font-weight: 800;
  font-size: 14px;
  text-align: center;
}
.row {
  display: flex;
  gap: 8px;
}
input {
  flex: 1;
  min-width: 0;
  min-height: 44px;
  padding: 8px 12px;
  border: 3px solid var(--bz-soft);
  border-radius: var(--bz-radius-sm);
  font: inherit;
  font-weight: 800;
  font-size: 18px;
}
.go {
  padding: 8px 16px;
  border-radius: var(--bz-radius-sm);
  background: var(--bz-sun);
  font-weight: 800;
}
.jumps {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: center;
}
.jumps button {
  min-width: 46px;
  padding: 6px 10px;
  border-radius: var(--bz-radius-sm);
  background: var(--bz-soft);
  font-weight: 800;
}
</style>
