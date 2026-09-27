<script setup>
import { computed, ref } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import PictureCard from '../../components/ui/PictureCard.vue'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { numberWord, t } from '../../i18n'
import { beep } from '../../services/audio/sfx'
import { capitalize } from '../../utils/text'
import { engineEmits, engineProps } from '../contract'

/**
 * Count by tapping. "drum": one beat per syllable (Dobolós szavak).
 * "basket": put exactly `target` fruits in the basket (Számolós).
 * data: TapCountData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const MAX_BEATS = 6
/** Wrong drum answers before Csillám claps the syllables herself. */
const HELP_AFTER = 2
const HELP_DELAY_MS = 1200

const count = ref(0)
const misses = ref(0)
const hint = ref('')
const pool = computed(() => (props.data.pool ?? 10) - count.value)
const { later } = useTimers()

function drum() {
  if (props.locked || count.value >= MAX_BEATS) return
  count.value++
  beep()
}

function putIn() {
  if (props.locked || pool.value <= 0) return
  count.value++
  beep(300, 0.08)
}

function takeOut() {
  if (props.locked || count.value <= 0) return
  count.value--
  beep(220, 0.08)
}

function help() {
  const syllables = props.data.help
  if (!syllables) return
  hint.value = syllables.join(' – ')
  emit('say', syllables.join(', '))
}

function done() {
  if (props.locked) return
  const d = props.data
  if (count.value === d.target) {
    emit('answer', { correct: true, say: d.onCorrect })
    return
  }

  if (d.mode === 'basket') {
    emit('answer', {
      correct: false,
      say: t('tapcount.basketWrong', {
        have: numberWord(count.value),
        want: capitalize(numberWord(d.target)),
        what: d.accusative,
      }),
    })
    return
  }

  misses.value++
  count.value = 0
  emit('answer', { correct: false })
  if (misses.value >= HELP_AFTER) later(help, HELP_DELAY_MS)
}
</script>

<template>
  <template v-if="data.mode === 'drum'">
    <PictureCard
      v-if="data.stimulus"
      :emoji="data.stimulus.emoji"
      :label="data.stimulus.label"
      :pressable="Boolean(data.stimulus.say)"
      @click="data.stimulus.say && emit('say', data.stimulus.say)"
    />
    <div class="beats" aria-live="polite">
      <EmojiArt v-for="n in count" :key="n" :char="ICONS.tap" />
      <span class="bz-sr-only">{{ t('tapcount.taps', { count }) }}</span>
    </div>
    <button type="button" class="drum" data-no-feel :aria-label="t('tapcount.drum')" @click="drum">
      <EmojiArt :char="ICONS.drum" />
    </button>
    <div class="hint">{{ hint }}</div>
    <div class="bz-row">
      <BzButton :icon="ICONS.reset" @click="count = 0">{{ t('common.reset') }}</BzButton>
      <BzButton :icon="ICONS.hint" @click="help">{{ t('tapcount.help') }}</BzButton>
      <BzButton variant="primary" :icon="ICONS.done" @click="done">{{ t('common.done') }}</BzButton>
    </div>
  </template>

  <template v-else>
    <div class="basket">
      <EmojiArt class="basket-icon" :char="ICONS.basket" />
      <div class="fruits">
        <button
          v-for="n in count"
          :key="n"
          type="button"
          class="fruit"
          :aria-label="t('tapcount.takeOut', { name: data.name })"
          @click="takeOut"
        >
          <EmojiArt :char="data.emoji" />
        </button>
      </div>
    </div>
    <div class="fruits fruits--pile">
      <button
        v-for="n in pool"
        :key="n"
        type="button"
        class="fruit"
        :aria-label="t('tapcount.putIn', { name: data.name })"
        @click="putIn"
      >
        <EmojiArt :char="data.emoji" />
      </button>
    </div>
    <div class="bz-row">
      <BzButton :icon="ICONS.reset" @click="count = 0">{{ t('common.reset') }}</BzButton>
      <BzButton variant="primary" :icon="ICONS.done" @click="done">{{ t('common.done') }}</BzButton>
    </div>
  </template>
</template>

<style scoped>
.beats {
  min-height: 48px;
  font-size: 36px;
  letter-spacing: 6px;
}
.drum {
  display: grid;
  place-items: center;
  width: 160px;
  height: 160px;
  border-radius: 50%;
  background: var(--bz-sun);
  font-size: 96px;
  line-height: 1;
  box-shadow: 0 8px 0 rgba(59, 31, 74, 0.2);
  transition: transform 0.06s;
}
.drum:active {
  transform: translateY(5px) scale(0.97);
}
.hint {
  min-height: 1.2em;
  font-size: 26px;
  font-weight: 800;
  color: var(--bz-coral);
}
.basket {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  max-width: 520px;
  min-height: 130px;
  padding: 10px 16px;
  border-radius: 30px 30px 60px 60px;
  background: var(--bz-sun);
  color: var(--bz-on-bright);
}
.basket-icon {
  font-size: 56px;
}
.fruits {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.fruits--pile {
  justify-content: center;
  max-width: 520px;
  min-height: 70px;
}
.fruit {
  padding: 4px;
  border-radius: var(--bz-radius-sm);
  font-size: 44px;
  line-height: 1;
  transition: transform 0.06s;
}
.fruit:active {
  transform: scale(0.9);
}
</style>
