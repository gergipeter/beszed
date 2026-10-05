<script setup>
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { BzButton, BzNotice, CsillamAvatar } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { useSessionStore } from '../stores/session'
import { texts } from '../texts'
import LegalLinks from '../components/LegalLinks.vue'

/** One-time parental consent (again whenever the privacy notice version changes). */
const session = useSessionStore()
const router = useRouter()
const agreed = ref(false)
const saving = ref(false)
const error = ref('')

async function accept() {
  saving.value = true
  error.value = ''
  try {
    await session.acceptConsent()
    router.replace({ name: 'home' })
  } catch {
    error.value = texts.consent.failed
  } finally {
    saving.value = false
  }
}

async function logout() {
  await session.logout().catch(() => session.signedOut())
  router.replace({ name: 'login' })
}
</script>

<template>
  <main class="bz consent">
    <div class="card">
      <div class="avatar"><CsillamAvatar :worn="{}" /></div>
      <h1>{{ texts.consent.title }}</h1>
      <p>{{ texts.consent.intro }}</p>
      <h2>{{ texts.consent.stored }}</h2>
      <ul>
        <li v-for="item in texts.consent.storedItems" :key="item">{{ item }}</li>
      </ul>
      <p>{{ texts.consent.rights }}</p>
      <LegalLinks />

      <label class="check">
        <input v-model="agreed" type="checkbox" />
        <span>{{ texts.consent.checkbox }}</span>
      </label>

      <BzNotice v-if="error" tone="warn">{{ error }}</BzNotice>
      <div class="bz-row actions">
        <BzButton variant="primary" :disabled="!agreed || saving" @click="accept">{{ texts.consent.accept }}</BzButton>
        <BzButton variant="soft" @click="logout">{{ texts.logout }}</BzButton>
      </div>
    </div>
  </main>
</template>

<style scoped>
.consent {
  display: grid;
  place-items: center;
}
.card {
  width: min(100%, 560px);
  padding: 22px 22px 24px;
  border-radius: 30px;
  background: var(--bz-card);
  font-size: 17px;
  line-height: 1.5;
  box-shadow: var(--bz-shadow-lg);
}
.avatar {
  width: 110px;
  margin: -6px auto 0;
}
h1 {
  margin: 4px 0 8px;
  font-size: 30px;
  text-align: center;
}
h2 {
  margin: 14px 0 4px;
  font-size: 19px;
}
ul {
  margin: 0;
  padding-left: 22px;
}
.check {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin: 18px 0 8px;
  padding: 14px;
  border-radius: var(--bz-radius);
  background: var(--bz-soft);
  font-weight: 700;
  cursor: pointer;
}
.check input {
  flex: none;
  width: 26px;
  height: 26px;
  margin-top: 2px;
  appearance: none;
  border: 2px solid var(--bz-card);
  border-radius: 6px;
  background-color: var(--bz-card);
  cursor: pointer;
}
.check input:checked {
  border-color: var(--bz-leaf);
  background-color: var(--bz-leaf);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='12' viewBox='0 0 16 12'%3E%3Cpath d='M1 6l5 5 9-10' fill='none' stroke='white' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: center;
}
.actions {
  margin-top: 16px;
}
</style>
