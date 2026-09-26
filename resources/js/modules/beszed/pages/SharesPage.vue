<script setup>
import { computed, onMounted, ref } from 'vue'
import { createShare, fetchShares, revokeShare } from '../api'
import BzButton from '../components/ui/BzButton.vue'
import BzNotice from '../components/ui/BzNotice.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useAsync } from '../composables/useAsync'
import { useModuleContext } from '../composables/useModuleContext'
import { ICONS } from '../config/icons'
import { formatDate, t } from '../i18n'
import { errorMessage } from '../utils/errors'

/**
 * Parent-facing: read-only progress links for the speech therapist. A new link is
 * shown once (only its hash is stored); links expire and can be revoked any time.
 */
const DAYS = [7, 30, 90]

const { childId, childName } = useModuleContext()
const days = ref(30)
const label = ref('')
const busy = ref(false)
const message = ref('')
/** The link just created: shown until the page is left. */
const fresh = ref(null)
const copied = ref(false)

const { data, error, run: load } = useAsync(() => fetchShares(childId.value), { fallback: t('share.loadFailed') })
const shares = computed(() => data.value?.shares ?? [])

async function create() {
  busy.value = true
  message.value = ''
  copied.value = false
  try {
    const { url } = await createShare(childId.value, { days: days.value, label: label.value.trim() || null })
    fresh.value = url
    label.value = ''
    await load()
  } catch (e) {
    message.value = errorMessage(e, t('share.createFailed'))
  } finally {
    busy.value = false
  }
}

async function copy() {
  try {
    await navigator.clipboard.writeText(fresh.value)
    copied.value = true
  } catch {
    // No clipboard (http, old browser): the link is selectable in the field.
    document.getElementById('share-url')?.select()
  }
}

async function share() {
  try {
    await navigator.share({ title: t('share.shareTitle', { name: childName.value }), url: fresh.value })
  } catch {
    /* cancelled */
  }
}

async function revoke(s) {
  message.value = ''
  try {
    await revokeShare(childId.value, s.id)
    await load()
  } catch (e) {
    message.value = errorMessage(e, t('share.revokeFailed'))
  }
}

const canNativeShare = typeof navigator !== 'undefined' && 'share' in navigator
const status = s =>
  s.active ? t('share.activeUntil', { date: formatDate(s.expiresAt) }) : s.revokedAt ? t('share.revoked') : t('share.expired')

onMounted(load)
</script>

<template>
  <PageHeader :title="t('share.title')" :back-to="{ name: 'beszed.progress', params: { childId } }" />

  <p class="lead">{{ t('share.intro', { name: childName }) }}</p>

  <BzNotice v-if="message" tone="warn">{{ message }}</BzNotice>

  <section v-if="fresh" class="card fresh" aria-live="polite">
    <b>{{ t('share.ready') }}</b>
    <input id="share-url" class="url" :value="fresh" readonly @focus="$event.target.select()" />
    <div class="bz-row">
      <BzButton variant="primary" :icon="ICONS.copy" @click="copy">
        {{ copied ? t('share.copied') : t('share.copy') }}
      </BzButton>
      <BzButton v-if="canNativeShare" :icon="ICONS.share" @click="share">{{ t('share.send') }}</BzButton>
    </div>
    <small class="muted">{{ t('share.onlyNow') }}</small>
  </section>

  <form class="card form" @submit.prevent="create">
    <label class="field">
      <span>{{ t('share.labelField') }}</span>
      <input v-model="label" maxlength="80" :placeholder="t('share.labelHint')" />
    </label>
    <label class="field">
      <span>{{ t('share.validity') }}</span>
      <select v-model.number="days">
        <option v-for="n in DAYS" :key="n" :value="n">{{ t('share.days', { count: n }) }}</option>
      </select>
    </label>
    <BzButton variant="primary" :icon="ICONS.share" :disabled="busy" @click="create">{{ t('share.create') }}</BzButton>
  </form>

  <BzNotice v-if="error" tone="warn">
    {{ error }}
    <BzButton size="sm" @click="load">{{ t('common.retry') }}</BzButton>
  </BzNotice>

  <section v-else-if="shares.length" class="card">
    <h2 class="list-title">{{ t('share.listTitle') }}</h2>
    <ul class="list">
      <li v-for="s in shares" :key="s.id" class="row" :class="{ 'row--off': !s.active }">
        <div class="row-main">
          <b>{{ s.label || t('share.noLabel') }}</b>
          <span class="muted">
            {{ status(s) }} ·
            {{ s.views ? t('share.viewed', { count: s.views, date: formatDate(s.lastViewedAt) }) : t('share.notViewed') }}
          </span>
        </div>
        <BzButton v-if="s.active" size="sm" variant="danger" @click="revoke(s)">{{ t('share.revoke') }}</BzButton>
      </li>
    </ul>
  </section>

  <p class="muted note">{{ t('share.privacy') }}</p>
</template>

<style scoped>
.lead {
  margin: 0 0 14px;
  font-size: var(--bz-text);
}
.card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 14px;
  padding: 14px 16px;
  border-radius: var(--bz-radius);
  background: var(--bz-card);
}
.fresh {
  border: 3px solid var(--bz-leaf);
}
.url {
  width: 100%;
  padding: 10px 12px;
  border: 2px solid var(--bz-guide);
  border-radius: 12px;
  background: var(--bz-soft);
  color: var(--bz-ink);
  font: inherit;
  font-size: 15px;
}
.form {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: end;
  gap: 12px;
}
@media (max-width: 560px) {
  .form {
    grid-template-columns: 1fr;
  }
}
.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-weight: 700;
  font-size: var(--bz-text-sm);
}
.field input,
.field select {
  padding: 9px 12px;
  border: 2px solid var(--bz-guide);
  border-radius: 12px;
  background: var(--bz-soft);
  color: var(--bz-ink);
  font: inherit;
  font-weight: 400;
}
.list-title {
  margin: 0;
  font-size: 18px;
}
.list {
  margin: 0;
  padding: 0;
  list-style: none;
}
.row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-top: 1px solid var(--bz-soft);
}
.row--off {
  opacity: 0.6;
}
.row-main {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}
.muted {
  color: var(--bz-muted);
  font-size: 14px;
}
.note {
  margin-top: 8px;
}
</style>
