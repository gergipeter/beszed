<script setup>
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import BzIconButton from './BzIconButton.vue'

/** Parent-page header: home button, title, and extra controls in the slot. */
defineProps({
  title: { type: String, required: true },
  backTo: { type: [String, Object], required: true },
})
</script>

<template>
  <header class="header">
    <BzIconButton :to="backTo" :icon="ICONS.home" :label="t('common.back')" />
    <h1 class="title">{{ title }}</h1>
    <slot />
  </header>
</template>

<style scoped>
.header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}
.title {
  flex: 1;
  margin: 0;
  font-size: var(--bz-text-lg);
  font-weight: 800;
}
</style>
