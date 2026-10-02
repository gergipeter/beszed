<script setup>
import { ref } from 'vue'
import { t } from '../../i18n'

/**
 * Pick a pálya by hand (Kirakó has 200): type a number or tap a jump. The choice
 * is applied to the next start, after which the adaptive level carries on from it.
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
    <button type="button" class="chip" @click="open = !open">
      🎚️ {{ current ? t('levelPicker.titleCurrent', { current }) : t('levelPicker.title') }} · {{ t('levelPicker.choose') }}
    </button>
    <div v-if="open" class="panel">
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
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  margin: 0 auto 10px;
}
.chip {
  padding: 5px 14px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-weight: 800;
  font-size: 15px;
  box-shadow: var(--bz-shadow-sm);
}
.panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  border-radius: 20px;
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
  max-width: 100%;
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
  border-radius: 14px;
  font: inherit;
  font-weight: 800;
  font-size: 18px;
}
.go {
  padding: 8px 16px;
  border-radius: 14px;
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
  border-radius: 12px;
  background: var(--bz-soft);
  font-weight: 800;
}
</style>
