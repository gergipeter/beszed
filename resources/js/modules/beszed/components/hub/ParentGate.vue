<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { currentLanguage, t } from '../../i18n'
import { buzz } from '../../services/touch/feel'
import { numberWords } from '../../utils/numberWords'

/**
 * A parental gate (App Store guideline 1.3): before purchases, links out and the parents' menu, the adult is asked to
 * read a three-digit number written out in words and type it. A 4–5-year-old cannot read it, and a guess is right
 * one time in 900. Three wrong answers lock it for half a minute and pick a new number.
 *
 * Use it with a template ref: `if (await gate.value.ask()) { …the parent's action… }`.
 */
const dialog = ref(null)
const target = ref(100)
const typed = ref('')
const wrong = ref(false)
const failures = ref(0)
const lockedFor = ref(0)

let resolver = null
let lockTimer = null

const words = computed(() => numberWords(target.value, currentLanguage.value === 'en' ? 'en' : 'hu'))
const locked = computed(() => lockedFor.value > 0)
const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'back', '0', 'ok']

function newNumber() {
  // 101–999 without round hundreds, so the answer is never just "one digit and two zeros"
  do target.value = 100 + Math.floor(Math.random() * 900)
  while (target.value % 100 === 0)
  typed.value = ''
}

/** Opens the gate; resolves true when the number was typed right, false when it was closed. */
function ask() {
  newNumber()
  wrong.value = false
  dialog.value?.showModal()
  return new Promise(resolve => {
    resolver = resolve
  })
}

function finish(ok) {
  const done = resolver
  resolver = null
  if (dialog.value?.open) dialog.value.close()
  done?.(ok)
}

function press(key) {
  if (locked.value) return
  buzz(6)
  wrong.value = false
  if (key === 'back') typed.value = typed.value.slice(0, -1)
  else if (key === 'ok') check()
  else if (typed.value.length < 3) typed.value += key
}

function check() {
  if (Number(typed.value) === target.value) return finish(true)
  wrong.value = true
  failures.value += 1
  buzz(30)
  if (failures.value >= 3) {
    failures.value = 0
    lockedFor.value = 30
    clearInterval(lockTimer)
    lockTimer = setInterval(() => {
      lockedFor.value -= 1
      if (lockedFor.value <= 0) {
        clearInterval(lockTimer)
        newNumber()
        wrong.value = false
      }
    }, 1000)
  }
  typed.value = ''
}

/** The physical keyboard works too (adults on a computer or tablet with a keyboard). */
function onKey(event) {
  if (/^\d$/.test(event.key)) press(event.key)
  else if (event.key === 'Backspace') press('back')
  else if (event.key === 'Enter') press('ok')
  else return
  event.preventDefault()
}

onBeforeUnmount(() => {
  clearInterval(lockTimer)
  resolver?.(false)
})

defineExpose({ ask })
</script>

<template>
  <dialog ref="dialog" class="gate" :aria-label="t('gate.title')" @keydown="onKey" @cancel.prevent="finish(false)" @click.self="finish(false)">
    <div class="box">
      <header class="head">
        <h2>{{ t('gate.title') }}</h2>
        <button type="button" class="close" :aria-label="t('gate.cancel')" @click="finish(false)"><span aria-hidden="true">✕</span></button>
      </header>

      <p class="ask">{{ t('gate.ask') }}</p>
      <p class="words" data-testid="gate-words">{{ words }}</p>

      <div class="display" :class="{ 'display--wrong': wrong }" role="status" :aria-label="typed || t('gate.empty')">
        <span v-for="i in 3" :key="i" class="cell">{{ typed[i - 1] ?? '' }}</span>
      </div>
      <p class="message" role="alert">
        <template v-if="locked">{{ t('gate.locked', { seconds: lockedFor }) }}</template>
        <template v-else-if="wrong">{{ t('gate.wrong') }}</template>
      </p>

      <div class="pad">
        <button
          v-for="k in KEYS"
          :key="k"
          type="button"
          class="key"
          :class="{ 'key--ok': k === 'ok', 'key--back': k === 'back' }"
          :disabled="locked"
          :aria-label="k === 'back' ? t('gate.backspace') : k === 'ok' ? t('gate.ok') : k"
          @click="press(k)"
        >
          <span aria-hidden="true">{{ k === 'back' ? '⌫' : k === 'ok' ? '✓' : k }}</span>
        </button>
      </div>
    </div>
  </dialog>
</template>

<style scoped>
.gate {
  position: fixed;
  inset: 0;
  margin: auto;
  width: min(360px, 94vw);
  max-height: 96dvh;
  padding: 0;
  border: 0;
  border-radius: 32px;
  background: var(--bz-card);
  color: var(--bz-ink);
  font-family: var(--bz-font);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
  overflow: auto;
}
.gate[open] {
  animation: pop 0.3s var(--bz-spring);
}
.gate::backdrop {
  background: rgba(30, 20, 60, 0.6);
}
.box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 18px 18px 20px;
}
.head {
  display: flex;
  align-items: center;
  gap: 10px;
}
h2 {
  flex: 1;
  margin: 0;
  font-size: 24px;
}
.close {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--bz-soft);
  font-size: 20px;
  line-height: 1;
}
.ask {
  margin: 0;
  font-size: 16px;
  color: var(--bz-muted);
}
.words {
  margin: 0;
  padding: 10px 12px;
  border-radius: 16px;
  background: var(--bz-soft);
  font-size: 22px;
  font-weight: 800;
  line-height: 1.2;
  text-align: center;
  overflow-wrap: anywhere;
  user-select: none;
}
.display {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin-top: 4px;
}
.display--wrong {
  animation: shake 0.4s;
}
.cell {
  display: grid;
  place-items: center;
  width: 56px;
  height: 64px;
  border: 3px solid color-mix(in srgb, var(--bz-ink) 30%, transparent);
  border-radius: 16px;
  background: #fff;
  color: #1f1f1f;
  font-size: 34px;
  font-weight: 800;
}
.message {
  min-height: 24px;
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  text-align: center;
  color: #c0392b;
}
.pad {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}
.key {
  min-height: 58px;
  border-radius: 18px;
  background: var(--bz-soft);
  box-shadow: 0 4px 0 rgba(0, 0, 0, 0.14);
  font-size: 28px;
  font-weight: 800;
  transition: transform 0.2s var(--bz-spring);
}
.key:active:not(:disabled) {
  transform: scale(0.92);
}
.key:disabled {
  opacity: 0.4;
}
.key--ok {
  background: var(--bz-leaf, #3aa76d);
  color: var(--bz-on-accent, #fff);
}
@keyframes pop {
  from {
    opacity: 0;
    transform: scale(0.85);
  }
}
@keyframes shake {
  25% {
    transform: translateX(-8px);
  }
  75% {
    transform: translateX(8px);
  }
}
@media (prefers-reduced-motion: reduce) {
  .gate[open],
  .display--wrong {
    animation: none;
  }
}
</style>
