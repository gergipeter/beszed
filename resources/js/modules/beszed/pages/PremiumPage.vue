<script setup>
import { computed } from 'vue'
import BzButton from '../components/ui/BzButton.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useModuleContext } from '../composables/useModuleContext'
import { ICONS } from '../config/icons'
import { config } from '../config/options'
import { t } from '../i18n'
import { useMetaStore } from '../stores/meta'

/**
 * Parent-facing: what the premium plan adds to the free levels. Checkout is the host
 * app's job (`upgradeTo` option); nothing here is shown to the child while playing.
 */
const { childId } = useModuleContext()
const meta = useMetaStore()
const hub = computed(() => ({ name: 'beszed.hub', params: { childId: childId.value } }))
const level = computed(() => meta.meta?.freeMaxLevel ?? 3)
const BENEFITS = ['b1', 'b2', 'b3', 'b4']
</script>

<template>
  <PageHeader :title="t('premium.title')" :back-to="hub" />

  <p class="lead">{{ t('premium.lead', { level }) }}</p>

  <section class="card" :aria-label="t('premium.benefitsTitle')">
    <h2>{{ t('premium.benefitsTitle') }}</h2>
    <ul>
      <li v-for="b in BENEFITS" :key="b">{{ t(`premium.benefits.${b}`) }}</li>
    </ul>
  </section>

  <p v-if="!config.upgradeTo" class="soon" role="status">{{ t('premium.soon') }}</p>

  <div class="actions">
    <BzButton v-if="config.upgradeTo" :to="config.upgradeTo" :icon="ICONS.star">{{ t('premium.upgrade') }}</BzButton>
    <BzButton :to="hub" :icon="ICONS.home">{{ t('premium.tryFree') }}</BzButton>
  </div>
</template>

<style scoped>
.lead {
  margin: 0 0 14px;
  font-size: var(--bz-text);
}
.card {
  margin-bottom: 14px;
  padding: 14px 18px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-sm);
}
.card h2 {
  margin: 0 0 8px;
  font-size: var(--bz-text-md);
}
.card ul {
  margin: 0;
  padding-left: 20px;
  display: grid;
  gap: 6px;
}
.soon {
  margin: 0 0 14px;
  font-weight: 700;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
</style>
