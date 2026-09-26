<script setup>
import { onMounted, ref, watch } from 'vue'
import { t } from '../../i18n'

/** Player level badge + stars-to-next-level bar; fills up with a short animation. */
const props = defineProps({
  /** @type {import('vue').PropType<import('../../types').PlayerLevel>} */
  level: { type: Object, required: true },
  compact: { type: Boolean, default: false },
})

const shown = ref(0)
const fill = () => requestAnimationFrame(() => (shown.value = props.level.progress))

onMounted(fill)
watch(() => props.level.progress, fill)
</script>

<template>
  <div class="level" :class="{ 'level--compact': compact }">
    <span class="badge" :aria-label="t('rewards.level', { level: level.number })">{{ level.number }}</span>
    <div class="body">
      <div
        class="track"
        role="progressbar"
        aria-valuemin="0"
        :aria-valuemax="level.to - level.from"
        :aria-valuenow="level.stars - level.from"
        :aria-label="t('rewards.toNext', { count: level.to - level.stars })"
      >
        <span class="fill" :style="{ '--p': shown }" />
      </div>
      <small v-if="!compact" class="note">{{ t('rewards.toNext', { count: level.to - level.stars }) }}</small>
    </div>
  </div>
</template>

<style scoped>
.level {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.badge {
  flex: none;
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: radial-gradient(circle at 35% 30%, #fff6c2, var(--bz-sun) 60%);
  color: var(--bz-on-bright);
  font-size: 24px;
  font-weight: 800;
  box-shadow: var(--bz-shadow-sm), inset 0 0 0 3px rgba(255, 255, 255, 0.6);
}
.level--compact .badge {
  width: 40px;
  height: 40px;
  font-size: 20px;
}
.body {
  flex: 1;
  min-width: 0;
}
.track {
  position: relative;
  height: 16px;
  overflow: hidden;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
  box-shadow: inset 0 2px 0 rgba(59, 31, 74, 0.08);
}
.level--compact .track {
  height: 12px;
}
/* A full-width bar slid left by the missing share: transform only, so the fill-up runs on the GPU. */
.fill {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--bz-sun), var(--bz-coral));
  transform: translateX(calc((var(--p, 0) - 1) * 100%));
  transition: transform 0.9s cubic-bezier(0.2, 0.8, 0.2, 1);
  will-change: transform;
}
.note {
  display: block;
  margin-top: 4px;
  font-size: 15px;
  color: var(--bz-muted);
}
</style>
