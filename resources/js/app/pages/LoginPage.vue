<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { BzButton, BzNotice, CsillamAvatar } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { appConfig } from '../config'
import { useSessionStore } from '../stores/session'
import { texts } from '../texts'

const route = useRoute()
const router = useRouter()
const session = useSessionStore()
const busy = ref(false)
const demoError = ref('')

const error = computed(
  () => demoError.value || texts.errors[route.query.error] || (session.unreachable ? texts.errors.server : ''),
)

async function demo() {
  busy.value = true
  demoError.value = ''
  try {
    await session.demoLogin()
    router.replace({ name: 'home' })
  } catch {
    demoError.value = texts.errors.demo
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <main class="bz login">
    <div class="card">
      <div class="avatar"><CsillamAvatar :accessory="null" /></div>
      <h1 class="title">{{ appConfig.name }}</h1>
      <p class="tagline">{{ texts.tagline }}</p>

      <BzNotice v-if="error" tone="warn">{{ error }}</BzNotice>

      <div class="actions">
        <!-- Full page navigation: Google's consent screen, then back to /auth/google/callback. -->
        <a v-if="appConfig.auth.google" class="google" href="/auth/google/redirect">
          <svg class="g" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
          </svg>
          <span>{{ texts.google }}</span>
        </a>

        <BzButton v-if="appConfig.auth.demo" variant="soft" :disabled="busy" @click="demo">{{ texts.demo }}</BzButton>
      </div>

      <p v-if="appConfig.auth.demo && !appConfig.auth.google" class="dev">{{ texts.googleSetup }}</p>
      <p v-if="!appConfig.auth.google && !appConfig.auth.demo" class="dev">{{ texts.noLogin }}</p>
      <p class="fine">
        {{ texts.parentsOnly }}
        <RouterLink class="privacy-link" :to="{ name: 'privacy' }">{{ texts.privacyLink }}</RouterLink>
      </p>
    </div>
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
  gap: 10px;
  width: min(100%, 440px);
  padding: 28px 24px 22px;
  border-radius: 32px;
  background: var(--bz-card);
  text-align: center;
  box-shadow: var(--bz-shadow-lg);
}
.avatar {
  width: 150px;
  margin-top: -8px;
}
.title {
  margin: 0;
  font-size: 34px;
  font-weight: 800;
  line-height: 1.1;
}
.tagline {
  margin: 0 0 6px;
  font-size: 18px;
  color: var(--bz-muted);
}
.actions {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
  width: 100%;
  margin-top: 6px;
}
/* Google's sign-in button style: white, 4-colour G, Roboto-like weight. */
.google {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  min-height: 52px;
  padding: 10px 16px;
  border: 1px solid #747775;
  border-radius: var(--bz-radius-pill);
  background: #fff;
  color: #1f1f1f;
  font-family: Roboto, Arial, sans-serif;
  font-size: 16px;
  font-weight: 500;
  white-space: nowrap;
  transition: background 0.15s, box-shadow 0.15s;
}
.google:hover {
  background: #f8f9fa;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.18);
}
.g {
  width: 22px;
  height: 22px;
}
.dev {
  margin: 4px 0 0;
  font-size: 14px;
  color: var(--bz-muted);
}
.privacy-link {
  font-weight: 700;
  text-decoration: underline;
}
.fine {
  margin: 8px 0 0;
  font-size: 14px;
  line-height: 1.4;
  color: var(--bz-muted);
}
</style>
