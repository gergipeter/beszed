<script setup>
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { BzButton, BzNotice, CsillamAvatar } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { useSessionStore } from '../stores/session'
import { texts } from '../texts'

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
      <p><RouterLink class="link" :to="{ name: 'privacy' }">{{ texts.privacyLink }} →</RouterLink></p>

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
.link {
  font-weight: 700;
  text-decoration: underline;
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
  accent-color: var(--bz-leaf);
}
.actions {
  margin-top: 16px;
}
</style>
