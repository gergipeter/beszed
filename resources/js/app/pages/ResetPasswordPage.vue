<script setup>
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { BzButton, BzNotice, CsillamAvatar } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { useSessionStore } from '../stores/session'
import { firstError, texts } from '../texts'

/** The page the password-reset e-mail links to: a new password, then straight in. */
const route = useRoute()
const router = useRouter()
const session = useSessionStore()

const email = ref(String(route.query.email ?? ''))
const token = String(route.query.token ?? '')
const password = ref('')
const busy = ref(false)
const error = ref('')

async function submit() {
  busy.value = true
  error.value = ''
  try {
    await session.resetPassword({ token, email: email.value, password: password.value })
    await session.emailLogin(email.value, password.value)
    router.replace({ name: 'home' })
  } catch (e) {
    error.value = firstError(e, texts.auth.resetFailed)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <main class="bz login">
    <form class="card" @submit.prevent="submit">
      <div class="avatar"><CsillamAvatar :worn="{}" /></div>
      <h1 class="title">{{ texts.auth.resetTitle }}</h1>
      <p v-if="!token" class="fine">{{ texts.auth.resetNoToken }}</p>
      <BzNotice v-if="error" tone="warn">{{ error }}</BzNotice>

      <template v-if="token">
        <label class="field">
          <span>{{ texts.auth.email }}</span>
          <input v-model="email" type="email" autocomplete="email" required />
        </label>
        <label class="field">
          <span>{{ texts.auth.newPassword }}</span>
          <input v-model="password" type="password" autocomplete="new-password" minlength="10" required />
          <small>{{ texts.auth.passwordHint }}</small>
        </label>
        <BzButton variant="primary" :disabled="busy" @click="submit">{{ texts.auth.resetSubmit }}</BzButton>
      </template>

      <RouterLink class="privacy-link" :to="{ name: 'login' }">{{ texts.auth.backToLogin }}</RouterLink>
    </form>
  </main>
</template>

<style scoped>
.login {
  display: grid;
  place-items: center;
}
.card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  width: min(100%, 440px);
  padding: 28px 24px 22px;
  border-radius: 32px;
  background: var(--bz-card);
  text-align: center;
  box-shadow: var(--bz-shadow-lg);
}
.avatar {
  width: 120px;
  margin-top: -8px;
}
.title {
  margin: 0;
  font-size: 28px;
  font-weight: 800;
  line-height: 1.1;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
  text-align: left;
  font-weight: 700;
}
.field input {
  min-height: 48px;
  padding: 8px 14px;
  border: 2px solid color-mix(in srgb, var(--bz-ink) 25%, transparent);
  border-radius: 16px;
  background: #fff;
  color: #1f1f1f;
  font: inherit;
  font-weight: 600;
}
.field small {
  font-weight: 600;
  color: var(--bz-muted);
}
.fine {
  margin: 0;
  color: var(--bz-muted);
}
.privacy-link {
  font-weight: 700;
  text-decoration: underline;
}
</style>
