<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { isNative } from '../app/native'
import { LANGUAGES as OFFERED, currentLanguage, setLanguage } from '../modules/beszed/i18n'

/**
 * hu/en switch: flips the module's own i18n (index.js) right away – every
 * `t()` call re-renders – and best-effort tells the server-side LanguageService
 * too, in case anything there ever reads the session language. Nothing server-
 * side currently does (checked app/ and resources/ for readers of it), so that
 * call is fire-and-forget and never blocks the UI switch.
 */
const LANGUAGES = Object.fromEntries(OFFERED.map(l => [l.code, { native_name: l.name, flag: l.flag }]))

const isOpen = ref(false)
const current = computed(() => LANGUAGES[currentLanguage.value] ?? LANGUAGES.hu)

function pick(code) {
  setLanguage(code)
  isOpen.value = false
  // In the app there is no server session to tell (the page is a local bundle on its own origin).
  if (isNative()) return
  fetch('/api/language/switch', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
    },
    body: JSON.stringify({ lang: code }),
  }).catch(() => {
    /* server-side session language is a nice-to-have, not required */
  })
}

function closeDropdown(event) {
  if (!event.target.closest('.language-switcher')) isOpen.value = false
}

onMounted(() => document.addEventListener('click', closeDropdown))
onUnmounted(() => document.removeEventListener('click', closeDropdown))
</script>

<template>
  <div class="language-switcher">
    <button type="button" class="btn-language" @click="isOpen = !isOpen">
      <span class="flag">{{ current.flag }}</span>
      <span class="name">{{ currentLanguage.toUpperCase() }}</span>
      <span class="icon">▼</span>
    </button>

    <div v-if="isOpen" class="dropdown-menu">
      <button
        v-for="(lang, code) in LANGUAGES"
        :key="code"
        type="button"
        class="language-option"
        :class="{ active: code === currentLanguage }"
        @click="pick(code)"
      >
        <span class="flag">{{ lang.flag }}</span>
        <span class="name">{{ lang.native_name }}</span>
        <span v-if="code === currentLanguage" class="checkmark">✓</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.language-switcher {
  position: relative;
  display: inline-block;
}
.btn-language {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 6px 12px;
  border: none;
  border-radius: var(--bz-radius-pill, 999px);
  background: var(--bz-card, #f3f4f6);
  box-shadow: var(--bz-shadow-sm, none);
  font-weight: 700;
  font-size: 14px;
  color: inherit;
  cursor: pointer;
}
.flag {
  font-size: 1.1rem;
  line-height: 1;
}
.icon {
  font-size: 0.6rem;
  opacity: 0.6;
}
.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 6px;
  min-width: 150px;
  padding: 4px;
  border-radius: var(--bz-radius, 12px);
  background: var(--bz-card, #fff);
  box-shadow: var(--bz-shadow, 0 4px 14px rgba(0, 0, 0, 0.15));
  z-index: 20;
}
.language-option {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.6rem;
  padding: 8px 10px;
  border: none;
  border-radius: 8px;
  background: none;
  font-size: 14px;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}
.language-option:hover {
  background: var(--bz-soft, #f3f4f6);
}
.language-option.active {
  background: var(--bz-soft, #eff6ff);
}
.language-option .name {
  flex: 1;
}
.checkmark {
  color: #10b981;
  font-weight: bold;
}
</style>
