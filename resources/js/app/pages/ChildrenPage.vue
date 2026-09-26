<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { BzButton, BzNotice, CsillamAvatar } from '../../modules/beszed'
import '../../modules/beszed/styles/index.css'
import { useSessionStore } from '../stores/session'
import { fill, texts } from '../texts'

/** "Who is playing today?": pick a child, add one, or remove one. */
const session = useSessionStore()
const router = useRouter()

const COLORS = ['#FFE27A', '#FFB8A8', '#BDE7C5', '#C9D7FF', '#FFD1E8', '#D8C8FF', '#B8ECE6', '#FFC9A8']
const colorOf = child => COLORS[child.id % COLORS.length]

const adding = ref(false)
const editing = ref(false)
const name = ref('')
const error = ref('')
const saving = ref(false)
const showForm = computed(() => adding.value || !session.children.length)

async function add() {
  if (!name.value.trim()) return
  saving.value = true
  error.value = ''
  try {
    const child = await session.addChild({ name: name.value.trim() })
    name.value = ''
    adding.value = false
    router.push({ name: 'beszed.hub', params: { childId: child.id } })
  } catch (e) {
    error.value = e.response?.data?.message || texts.saveFailed
  } finally {
    saving.value = false
  }
}

async function remove(child) {
  if (!window.confirm(fill(texts.removeConfirm, { name: child.name }))) return
  try {
    await session.removeChild(child.id)
  } catch (e) {
    error.value = e.response?.data?.message || texts.saveFailed
  }
}

async function logout() {
  await session.logout().catch(() => session.signedOut())
  router.replace({ name: 'login' })
}

async function deleteAccount() {
  if (window.prompt(texts.deletePrompt) !== texts.deleteWord) return
  try {
    await session.deleteAccount(texts.deleteWord)
    router.replace({ name: 'login' })
  } catch (e) {
    error.value = e.response?.data?.message || texts.deleteFailed
  }
}
</script>

<template>
  <main class="bz family">
    <header class="top">
      <img v-if="session.user?.avatar" class="parent-avatar" :src="session.user.avatar" alt="" referrerpolicy="no-referrer" />
      <span class="parent">{{ session.user?.name }}</span>
      <BzButton size="sm" variant="soft" @click="logout">{{ texts.logout }}</BzButton>
    </header>

    <div class="hello">
      <div class="avatar"><CsillamAvatar :accessory="null" /></div>
      <h1 class="title">{{ session.children.length ? texts.whoPlays : texts.firstChild }}</h1>
    </div>

    <BzNotice v-if="error" tone="warn">{{ error }}</BzNotice>

    <div v-if="session.children.length" class="kids">
      <div v-for="child in session.children" :key="child.id" class="kid-wrap">
        <RouterLink class="kid" :style="{ '--kid': colorOf(child) }" :to="{ name: 'beszed.hub', params: { childId: child.id } }">
          <span class="initial" aria-hidden="true">{{ child.name.charAt(0).toUpperCase() }}</span>
          <span class="kid-name">{{ child.name }}</span>
        </RouterLink>
        <button v-if="editing" type="button" class="remove" :aria-label="fill(texts.remove, { name: child.name })" @click="remove(child)">✕</button>
      </div>
      <button v-if="!showForm" type="button" class="kid kid--add" @click="adding = true">
        <span class="initial" aria-hidden="true">+</span>
        <span class="kid-name">{{ texts.addChild }}</span>
      </button>
    </div>

    <form v-if="showForm" class="add" @submit.prevent="add">
      <label class="field">
        <span>{{ texts.childName }}</span>
        <input v-model="name" maxlength="40" required :placeholder="texts.childNamePlaceholder" autocomplete="off" />
      </label>
      <div class="bz-row">
        <BzButton type="submit" variant="primary" :disabled="saving || !name.trim()">{{ texts.save }}</BzButton>
        <BzButton v-if="session.children.length" @click="adding = false">{{ texts.cancel }}</BzButton>
      </div>
    </form>

    <div v-if="session.children.length" class="manage">
      <BzButton size="sm" variant="soft" @click="editing = !editing">{{ editing ? texts.done : texts.edit }}</BzButton>
    </div>

    <!-- the parent's own data -->
    <footer class="account">
      <RouterLink :to="{ name: 'privacy' }">{{ texts.privacyLink }}</RouterLink>
      <a href="/api/me/export" download>{{ texts.exportData }}</a>
      <button type="button" class="danger" @click="deleteAccount">{{ texts.deleteAccount }}</button>
    </footer>
  </main>
</template>

<style scoped>
.account {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px 22px;
  margin-top: 36px;
  font-size: 15px;
  font-weight: 700;
  color: var(--bz-muted);
}
.account a,
.account button {
  text-decoration: underline;
  text-underline-offset: 3px;
}
.account .danger {
  color: var(--bz-coral);
}
.top {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
}
.parent-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
}
.parent {
  font-weight: 700;
  color: var(--bz-muted);
}
.hello {
  display: flex;
  align-items: center;
  gap: 14px;
  margin: 10px 0 20px;
}
.avatar {
  flex: none;
  width: clamp(90px, 24vw, 130px);
}
.title {
  margin: 0;
  font-size: clamp(28px, 6vw, 42px);
  font-weight: 800;
  line-height: 1.1;
}
.kids {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 16px;
}
.kid-wrap {
  position: relative;
}
.kid {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 20px 10px 16px;
  border-radius: 30px;
  background: var(--kid, var(--bz-card));
  color: var(--bz-on-bright);
  box-shadow: 0 7px 0 rgba(59, 31, 74, 0.18);
  transition: transform 0.08s;
}
.kid:active {
  transform: translateY(5px);
}
.kid--add {
  border: 4px dashed color-mix(in srgb, var(--bz-guide) 60%, transparent);
  background: var(--bz-card);
  color: var(--bz-ink);
  box-shadow: none;
}
.initial {
  display: grid;
  place-items: center;
  width: 76px;
  height: 76px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.75);
  font-size: 42px;
  font-weight: 800;
}
.kid-name {
  font-size: 24px;
  font-weight: 800;
}
.remove {
  position: absolute;
  top: -8px;
  right: -8px;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: var(--bz-coral);
  color: #fff;
  font-size: 18px;
  font-weight: 800;
  box-shadow: var(--bz-shadow-sm);
}
.add {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: 20px;
  padding: 18px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 19px;
  font-weight: 700;
}
.field input {
  padding: 12px 16px;
  border: 3px solid var(--bz-soft);
  border-radius: var(--bz-radius);
  background: var(--bz-soft);
  color: var(--bz-ink);
  font: inherit;
  font-size: 22px;
}
.field input:focus {
  border-color: var(--bz-guide);
  outline: none;
}
.manage {
  display: flex;
  justify-content: center;
  margin-top: 26px;
}
</style>
