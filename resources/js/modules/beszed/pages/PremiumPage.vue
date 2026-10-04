<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import ParentGate from '../components/hub/ParentGate.vue'
import BzButton from '../components/ui/BzButton.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useModuleContext } from '../composables/useModuleContext'
import { ICONS } from '../config/icons'
import { config } from '../config/options'
import { t } from '../i18n'
import * as store from '../services/billing/purchases'
import { useMetaStore } from '../stores/meta'

/**
 * Parent-facing paywall: what the premium plan adds to the free levels, what it costs, and how to buy it.
 *
 * Purchases go through the store (Apple In-App Purchase / Google Play Billing) and only exist in the native app; in a
 * browser the page just describes the plan. App Store rules this page follows (3.1.1, 3.1.2, 1.3): the price and
 * period come from the store, with the renewal and cancellation terms next to them; Terms of Use and Privacy links;
 * a "Restore purchases" button; and every buy or restore sits behind the parental gate. No outside payment is
 * mentioned or linked.
 */
const { childId } = useModuleContext()
const meta = useMetaStore()
const router = useRouter()
const hub = computed(() => ({ name: 'beszed.hub', params: { childId: childId.value } }))
const level = computed(() => meta.meta?.freeMaxLevel ?? 3)
const BENEFITS = ['b1', 'b2', 'b3', 'b4']

const native = store.available() && Boolean(config.billing.info && config.billing.sync)
const gate = ref(null)
const plans = ref([])
const loading = ref(native)
const busy = ref(false)
const error = ref('')
const done = ref(false)

const cancelWhere = computed(() => (store.platform() === 'ios' ? t('premium.cancelIos') : t('premium.cancelAndroid')))
const chargedTo = computed(() => (store.platform() === 'ios' ? t('premium.chargedIos') : t('premium.chargedAndroid')))

async function load() {
  loading.value = true
  error.value = ''
  try {
    plans.value = await store.loadPlans()
    if (!plans.value.length) error.value = t('premium.loadFailed')
  } catch {
    error.value = t('premium.loadFailed')
  } finally {
    loading.value = false
  }
}

/** Runs a purchase or restore after the parent has passed the gate. The action resolves true (premium now), false (not premium) or null (backed out). */
async function guarded(action, notPremiumText) {
  if (busy.value || !(await gate.value.ask())) return
  busy.value = true
  error.value = ''
  try {
    const premium = await action()
    if (premium) {
      done.value = true
      setTimeout(() => router.push(hub.value), 1800)
    } else if (premium === false) {
      error.value = notPremiumText
    }
  } catch {
    error.value = t('premium.failed')
  } finally {
    busy.value = false
  }
}

const buy = plan => guarded(() => store.purchase(plan), t('premium.notActive'))
const restore = () => guarded(store.restore, t('premium.nothingToRestore'))

const priceLine = plan => t(plan.period === 'year' ? 'premium.perYear' : 'premium.perMonth', { price: plan.price })
const trialLine = plan => (plan.trialDays ? t('premium.trial', { days: plan.trialDays }) : '')

/**
 * How much cheaper the yearly plan is per month than paying monthly, worked out from the store's own numeric
 * prices (never a hand-typed number, per App Store rule 3.1.1: only the store's price may be shown). Needs both
 * plans loaded; on a single-plan offering (or if the monthly price is 0) nothing is shown.
 */
const yearlySaving = computed(() => {
  const monthly = plans.value.find(p => p.period === 'month')
  const yearly = plans.value.find(p => p.period === 'year')
  if (!monthly?.rawPrice || !yearly?.rawPrice) return null
  const percent = Math.round((1 - yearly.rawPrice / 12 / monthly.rawPrice) * 100)
  return percent > 0 ? percent : null
})

onMounted(() => {
  if (native) load()
})
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

  <template v-if="native">
    <p v-if="done" class="done" role="status">{{ t('premium.thanks') }}</p>

    <template v-else>
      <p v-if="loading" class="soon" role="status">{{ t('premium.loading') }}</p>

      <section v-if="plans.length" class="plans" :aria-label="t('premium.plansTitle')">
        <div v-for="plan in plans" :key="plan.id" class="plan" :class="{ best: plan.period === 'year' && yearlySaving }">
          <div class="plan-text">
            <span v-if="plan.period === 'year' && yearlySaving" class="badge">{{ t('premium.bestValue', { percent: yearlySaving }) }}</span>
            <strong class="price">{{ priceLine(plan) }}</strong>
            <span v-if="trialLine(plan)" class="trial">{{ trialLine(plan) }}</span>
          </div>
          <BzButton :icon="ICONS.star" :disabled="busy" @click="buy(plan)">{{ t('premium.subscribe') }}</BzButton>
        </div>
      </section>

      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <BzButton v-if="error && !plans.length && !loading" :icon="ICONS.again" @click="load">{{ t('premium.retry') }}</BzButton>

      <section v-if="plans.length" class="terms" :aria-label="t('premium.termsTitle')">
        <p>{{ t('premium.renews') }}</p>
        <p>{{ chargedTo }}</p>
        <p>{{ cancelWhere }}</p>
        <p>{{ t('premium.allChildren') }}</p>
        <p class="links">
          <RouterLink v-if="config.billing.termsTo" :to="config.billing.termsTo">{{ t('premium.terms') }}</RouterLink>
          <RouterLink v-if="config.billing.privacyTo" :to="config.billing.privacyTo">{{ t('premium.privacy') }}</RouterLink>
        </p>
      </section>

      <div class="actions">
        <BzButton :icon="ICONS.again" :disabled="busy || loading" @click="restore">{{ t('premium.restore') }}</BzButton>
      </div>
    </template>
  </template>

  <p v-else class="soon" role="status">{{ t('premium.inApp') }}</p>

  <div class="actions">
    <BzButton :to="hub" :icon="ICONS.home">{{ t('premium.tryFree') }}</BzButton>
  </div>

  <ParentGate ref="gate" />
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
.soon,
.done,
.error {
  margin: 0 0 14px;
  font-weight: 700;
}
.error {
  color: #c0392b;
}
.plans {
  display: grid;
  gap: 10px;
  margin-bottom: 14px;
}
.plan {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 14px 18px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-sm);
  border: 2px solid transparent;
}
.plan.best {
  border-color: var(--bz-accent, #f0b429);
}
.plan-text {
  display: grid;
  gap: 2px;
}
.badge {
  justify-self: start;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  color: #7a4a00;
  background: var(--bz-accent, #f0b429);
}
.price {
  font-size: var(--bz-text-md);
}
.trial {
  color: var(--bz-muted);
}
.terms {
  margin-bottom: 14px;
  font-size: 14px;
  line-height: 1.4;
  color: var(--bz-muted);
}
.terms p {
  margin: 0 0 6px;
}
.links {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}
.links a {
  color: inherit;
  font-weight: 700;
  text-decoration: underline;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 10px;
}
</style>
