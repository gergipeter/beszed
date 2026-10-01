<script setup>
import { onMounted, ref } from 'vue'
import LanguageSwitcher from '../../../components/LanguageSwitcher.vue'
import BzButton from '../components/ui/BzButton.vue'
import BzNotice from '../components/ui/BzNotice.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import { useModuleContext } from '../composables/useModuleContext'
import { t } from '../i18n'
import { useGuideStore } from '../stores/guide'
import { useMetaStore } from '../stores/meta'
import { useSettingsStore } from '../stores/settings'
import { errorMessage } from '../utils/errors'

/** Parent-facing: pick Csillám's voice, tune rate/pitch, server-vs-browser voice, mute. */
const { childId } = useModuleContext()
const meta = useMetaStore()
const settings = useSettingsStore()
const guide = useGuideStore()

const failed = ref(false)
const message = ref('')
const saving = ref(false)
const customWord = ref('')

async function load() {
  failed.value = false
  try {
    await Promise.all([meta.load(), settings.load()])
  } catch {
    failed.value = true
  }
}

async function save(patch) {
  message.value = ''
  saving.value = true
  try {
    await settings.save(patch)
  } catch (e) {
    message.value = errorMessage(e, t('settings.saveFailed'))
  } finally {
    saving.value = false
  }
}

function tryVoice() {
  guide.speak([t('settings.tryLine')])
}

function playCustomWord() {
  const word = customWord.value.trim()
  if (word) guide.speak([word])
}

onMounted(load)
</script>

<template>
  <PageHeader :title="t('settings.title')" :back-to="{ name: 'beszed.hub', params: { childId } }">
    <LanguageSwitcher />
  </PageHeader>

  <BzNotice v-if="failed" tone="warn">
    {{ t('settings.loadFailed') }}
    <BzButton size="sm" @click="load">{{ t('common.retry') }}</BzButton>
  </BzNotice>

  <template v-else-if="meta.loaded && settings.loaded">
    <BzNotice v-if="message" tone="warn">{{ message }}</BzNotice>

    <section class="card">
      <h2 class="heading">{{ t('settings.muteTitle') }}</h2>
      <label class="row">
        <span>{{ t('settings.mute') }}</span>
        <input type="checkbox" :checked="settings.muted" :disabled="saving" @change="e => save({ muted: e.target.checked })" />
      </label>
    </section>

    <section class="card" :class="{ 'card--disabled': settings.muted }">
      <h2 class="heading">{{ t('settings.voiceTitle') }}</h2>

      <div class="row row--wrap">
        <button
          v-for="v in meta.meta.voices"
          :key="v.id"
          type="button"
          class="voice-pick"
          :class="{ 'voice-pick--on': settings.voice === v.id }"
          :disabled="saving || settings.muted"
          @click="save({ voice: v.id })"
        >
          {{ v.label }}
        </button>
      </div>

      <label class="row">
        <span>{{ t('settings.rate') }}</span>
        <input
          type="range"
          :min="meta.meta.rateRange.min"
          :max="meta.meta.rateRange.max"
          :value="settings.rate ?? 0"
          :disabled="saving || settings.muted"
          @change="e => save({ rate: Number(e.target.value) })"
        />
      </label>

      <label class="row">
        <span>{{ t('settings.pitch') }}</span>
        <input
          type="range"
          :min="meta.meta.pitchRange.min"
          :max="meta.meta.pitchRange.max"
          :value="settings.pitch ?? 0"
          :disabled="saving || settings.muted"
          @change="e => save({ pitch: Number(e.target.value) })"
        />
      </label>

      <label class="row" v-if="meta.serverTts">
        <span>{{ t('settings.preferServerTts') }}</span>
        <input
          type="checkbox"
          :checked="settings.preferServerTts"
          :disabled="saving || settings.muted"
          @change="e => save({ preferServerTts: e.target.checked })"
        />
      </label>

      <BzButton :disabled="settings.muted" @click="tryVoice">{{ t('settings.tryVoice') }}</BzButton>
    </section>

    <section class="card" :class="{ 'card--disabled': settings.muted }">
      <h2 class="heading">{{ t('settings.customWordTitle') }}</h2>
      <p class="hint">{{ t('settings.customWordHint') }}</p>
      <div class="row">
        <input
          v-model="customWord"
          type="text"
          class="word-input"
          maxlength="40"
          :placeholder="t('settings.customWordPlaceholder')"
          :disabled="settings.muted"
          @keyup.enter="playCustomWord"
        />
        <BzButton :disabled="settings.muted || !customWord.trim()" @click="playCustomWord">{{ t('settings.customWordPlay') }}</BzButton>
      </div>
    </section>
  </template>

  <p v-else class="bz-loading" aria-busy="true">{{ t('common.loading') }}</p>
</template>

<style scoped>
.card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin: 0 0 20px;
  padding: 16px 18px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
}
.card--disabled {
  opacity: 0.6;
}
.heading {
  margin: 0;
  font-size: 20px;
}
.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-weight: 700;
}
.row--wrap {
  flex-wrap: wrap;
  justify-content: flex-start;
}
.row input[type='range'] {
  flex: 1;
  max-width: 220px;
}
.hint {
  margin: -6px 0 0;
  font-size: var(--bz-text-sm);
  font-weight: 500;
  color: var(--bz-muted);
}
.word-input {
  flex: 1;
  min-width: 0;
  padding: 10px 14px;
  border: 2px solid var(--bz-soft);
  border-radius: var(--bz-radius);
  background: var(--bz-bg);
  font-size: var(--bz-text-md);
  font-weight: 700;
}
.word-input:focus {
  outline: none;
  border-color: var(--bz-leaf);
}
.voice-pick {
  padding: 8px 16px;
  border: 3px solid transparent;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-bg);
  font-weight: 700;
}
.voice-pick--on {
  border-color: var(--bz-leaf);
  background: color-mix(in srgb, var(--bz-leaf) 16%, var(--bz-bg));
}
</style>
