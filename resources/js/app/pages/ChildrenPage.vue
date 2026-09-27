<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { BzButton, BzNotice, CsillamAvatar, EmojiArt } from '../../modules/beszed'
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
const birthDate = ref('')
/** The óvodai jel picked in the form, and the child whose jel is being changed (the sheet). */
const sign = ref(null)
const signFor = ref(null)
const signOf = child => session.signs.find(x => x.id === child.sign)?.emoji ?? null
const today = new Date().toISOString().slice(0, 10)
const error = ref('')
const saving = ref(false)
const showForm = computed(() => adding.value || !session.children.length)

async function add() {
  if (!name.value.trim()) return
  saving.value = true
  error.value = ''
  try {
    const child = await session.addChild({ name: name.value.trim(), birth_date: birthDate.value || null, sign: sign.value })
    name.value = ''
    birthDate.value = ''
    sign.value = null
    adding.value = false
    router.push({ name: 'beszed.hub', params: { childId: child.id } })
  } catch (e) {
    error.value = e.response?.data?.message || texts.saveFailed
  } finally {
    saving.value = false
  }
}

/** The birth date only sets the age band: games start at a fitting level and pick age-appropriate words. */
async function setBirthDate(child, value) {
  error.value = ''
  try {
    await session.updateChild(child.id, { name: child.name, birth_date: value || null, sign: child.sign ?? null })
  } catch (e) {
    error.value = e.response?.data?.message || texts.saveFailed
  }
}

/** Changes a child's óvodai jel from the sheet. */
async function setSign(child, id) {
  error.value = ''
  signFor.value = null
  try {
    await session.updateChild(child.id, { name: child.name, birth_date: child.birth_date ?? null, sign: id })
  } catch (e) {
    error.value = e.response?.data?.message || texts.saveFailed
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

async function toggleMilestoneEmails(event) {
  const enabled = event.target.checked
  try {
    await session.savePreferences({ milestone_emails_enabled: enabled })
  } catch {
    event.target.checked = !enabled
    error.value = texts.milestoneEmailsSaveFailed
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
      <div class="avatar"><CsillamAvatar :worn="{}" /></div>
      <h1 class="title">{{ session.children.length ? texts.whoPlays : texts.firstChild }}</h1>
    </div>

    <BzNotice v-if="error" tone="warn">{{ error }}</BzNotice>

    <div v-if="session.children.length" class="kids">
      <div v-for="child in session.children" :key="child.id" class="kid-wrap">
        <RouterLink class="kid" :style="{ '--kid': colorOf(child) }" :to="{ name: 'beszed.hub', params: { childId: child.id } }">
          <!-- the óvodai jel, big: the child finds themselves by it before they can read -->
          <span class="initial" aria-hidden="true">
            <EmojiArt v-if="signOf(child)" class="sign" :char="signOf(child)" />
            <template v-else>{{ child.name.charAt(0).toUpperCase() }}</template>
          </span>
          <span class="kid-name">{{ child.name }}</span>
        </RouterLink>
        <button v-if="editing" type="button" class="remove" :aria-label="fill(texts.remove, { name: child.name })" @click="remove(child)">✕</button>
        <button v-if="editing" type="button" class="pick-sign" @click="signFor = child">{{ texts.pickSign }}</button>
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
      <label class="field">
        <span>{{ texts.birthDate }}</span>
        <input v-model="birthDate" type="date" min="2010-01-02" :max="today" />
        <small class="hint">{{ texts.birthDateHint }}</small>
      </label>
      <fieldset v-if="session.signs.length" class="field signs-field">
        <legend>{{ texts.sign }}</legend>
        <div class="signs" role="radiogroup" :aria-label="texts.sign">
          <button
            v-for="x in session.signs"
            :key="x.id"
            type="button"
            class="sign-pick"
            :class="{ 'sign-pick--on': sign === x.id }"
            role="radio"
            :aria-checked="sign === x.id"
            :aria-label="x.name"
            @click="sign = sign === x.id ? null : x.id"
          >
            <EmojiArt :char="x.emoji" />
          </button>
        </div>
        <small class="hint">{{ texts.signHint }}</small>
      </fieldset>
      <div class="bz-row">
        <BzButton type="submit" variant="primary" :disabled="saving || !name.trim()">{{ texts.save }}</BzButton>
        <BzButton v-if="session.children.length" @click="adding = false">{{ texts.cancel }}</BzButton>
      </div>
    </form>

    <ul v-if="editing" class="birthdays">
      <li v-for="child in session.children" :key="child.id">
        <label>
          <span>{{ fill(texts.birthDateOf, { name: child.name }) }}</span>
          <input
            type="date"
            min="2010-01-02"
            :max="today"
            :value="child.birth_date ?? ''"
            @change="setBirthDate(child, $event.target.value)"
          />
        </label>
      </li>
    </ul>

    <div v-if="session.children.length" class="manage">
      <BzButton size="sm" variant="soft" @click="editing = !editing">{{ editing ? texts.done : texts.edit }}</BzButton>
    </div>

    <!-- the parent's own data -->
    <label class="milestone-toggle">
      <input type="checkbox" :checked="session.user?.milestone_emails_enabled" @change="toggleMilestoneEmails" />
      <span>{{ texts.milestoneEmails }}</span>
    </label>

    <footer class="account">
      <RouterLink :to="{ name: 'privacy' }">{{ texts.privacyLink }}</RouterLink>
      <RouterLink v-if="session.user?.can_edit_content" :to="{ name: 'content' }">{{ texts.contentEditor }}</RouterLink>
      <a href="/api/me/export" download>{{ texts.exportData }}</a>
      <button type="button" class="danger" @click="deleteAccount">{{ texts.deleteAccount }}</button>
    </footer>

    <!-- changing a child's óvodai jel -->
    <div v-if="signFor" class="sign-sheet" role="dialog" aria-modal="true" :aria-label="fill(texts.signOf, { name: signFor.name })" @click.self="signFor = null">
      <div class="sheet">
        <h2 class="sheet-title">{{ fill(texts.signOf, { name: signFor.name }) }}</h2>
        <div class="signs">
          <button
            v-for="x in session.signs"
            :key="x.id"
            type="button"
            class="sign-pick"
            :class="{ 'sign-pick--on': signFor.sign === x.id }"
            :aria-label="x.name"
            @click="setSign(signFor, x.id)"
          >
            <EmojiArt :char="x.emoji" />
          </button>
        </div>
        <BzButton @click="signFor = null">{{ texts.cancel }}</BzButton>
      </div>
    </div>
  </main>
</template>

<style scoped>
.hint {
  font-size: 14px;
  font-weight: 400;
  color: var(--bz-muted);
}
.birthdays {
  display: grid;
  gap: 8px;
  max-width: 420px;
  margin: 16px auto 0;
  padding: 0;
  list-style: none;
}
.birthdays label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-weight: 700;
}
.birthdays input {
  padding: 6px 10px;
  border: 2px solid var(--bz-guide);
  border-radius: 12px;
  background: var(--bz-soft);
  color: var(--bz-ink);
  font: inherit;
}
.milestone-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 28px;
  font-size: 15px;
  font-weight: 700;
  color: var(--bz-muted);
}
.account {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px 22px;
  margin-top: 12px;
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
.initial .sign {
  font-size: 50px;
}
.pick-sign {
  margin-top: 6px;
  padding: 4px 10px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-weight: 700;
  font-size: 14px;
  box-shadow: var(--bz-shadow-sm);
}
.signs-field {
  border: 0;
  margin: 0;
  padding: 0;
}
.signs {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(52px, 1fr));
  gap: 8px;
}
.sign-pick {
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  border: 3px solid transparent;
  border-radius: 16px;
  background: var(--bz-soft);
  font-size: 30px;
  transition: transform 0.3s var(--bz-spring);
}
.sign-pick:active {
  transform: scale(0.9);
}
.sign-pick--on {
  border-color: var(--bz-leaf);
  background: color-mix(in srgb, var(--bz-leaf) 18%, var(--bz-card));
  transform: scale(1.08);
}
.sign-sheet {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: grid;
  align-items: end;
  background: rgba(30, 20, 60, 0.45);
}
.sheet {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 560px;
  width: 100%;
  margin: 0 auto;
  padding: 20px 18px calc(20px + env(safe-area-inset-bottom, 0px));
  border-radius: 28px 28px 0 0;
  background: var(--bz-card);
}
.sheet-title {
  margin: 0;
  font-size: 22px;
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
