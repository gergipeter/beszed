<script setup>
import { computed, ref } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { beep } from '../../services/audio/sfx'
import { engineEmits, engineProps } from '../contract'

/**
 * A board game with speaking tasks (Lépegető): roll the die, the token steps
 * along the path, and each field it lands on asks something to say, copy,
 * count or do; the parent (or the child) confirms it was done. Reaching Cél wins.
 * data: BoardData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const COLS = 4
const STEP_MS = 380

const at = ref(0)
const rolled = ref(0)
const phase = ref('roll') // roll → moving → task → roll … → done
const { later } = useTimers()

const tiles = computed(() => props.data.tiles)
const last = computed(() => tiles.value.length - 1)
const task = computed(() => tiles.value[at.value])

/** Snake path: every second row runs right to left. */
function place(i) {
  const row = Math.floor(i / COLS)
  const col = i % COLS
  return { gridRow: row + 1, gridColumn: (row % 2 === 0 ? col : COLS - 1 - col) + 1 }
}

function roll() {
  if (props.locked || phase.value !== 'roll') return
  rolled.value = 1 + Math.floor(Math.random() * props.data.dieMax)
  phase.value = 'moving'
  hop(rolled.value)
}

function hop(left) {
  if (left === 0 || at.value >= last.value) return land()
  at.value++
  beep(300 + at.value * 12, 0.08)
  later(() => hop(left - 1), STEP_MS)
}

function land() {
  if (at.value >= last.value) {
    phase.value = 'done'
    emit('answer', { correct: true, say: props.data.onCorrect, tries: 1 })
    return
  }
  phase.value = 'task'
  emit('say', task.value.text)
}

function finishTask() {
  if (props.locked || phase.value !== 'task') return
  phase.value = 'roll'
}
</script>

<template>
  <div class="board" role="list" :aria-label="t('board.label', { theme: data.theme })">
    <div
      v-for="(tile, i) in tiles"
      :key="i"
      role="listitem"
      class="tile"
      :class="{ 'tile--here': i === at, 'tile--past': i < at, 'tile--edge': i === 0 || i === last }"
      :style="place(i)"
    >
      <EmojiArt class="tile-art" :char="tile.emoji" />
      <small v-if="i === 0 || i === last" class="tile-name">{{ tile.text }}</small>
      <span v-if="i === at" class="token" aria-hidden="true"><EmojiArt :char="data.token" /></span>
    </div>
  </div>

  <div v-if="phase === 'task'" class="task" role="group" :aria-label="t('board.task')">
    <EmojiArt class="task-art" :char="task.emoji" />
    <p class="task-text">{{ task.text }}</p>
    <div class="bz-row">
      <BzButton :icon="ICONS.speaker" @click="emit('say', task.text)">{{ t('board.again') }}</BzButton>
      <BzButton variant="primary" :icon="ICONS.thumbsUp" @click="finishTask">{{ t('board.done') }}</BzButton>
    </div>
  </div>

  <div v-else-if="phase !== 'done'" class="bz-row">
    <BzButton variant="primary" :icon="ICONS.dice" :disabled="phase !== 'roll'" @click="roll">
      {{ phase === 'moving' ? t('board.rolled', { n: rolled }) : t('board.roll') }}
    </BzButton>
  </div>
</template>

<style scoped>
.board {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  width: 100%;
  max-width: 420px;
}
.tile {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1;
  border-radius: var(--bz-radius-sm);
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  box-shadow: 0 3px 0 rgba(59, 31, 74, 0.18);
}
.tile--edge {
  background: var(--bz-coral);
}
.tile--past {
  opacity: 0.45;
}
.tile--here {
  outline: 4px solid var(--bz-coral-deep);
}
.tile-art {
  font-size: 30px;
  line-height: 1;
}
.tile-name {
  font-size: 12px;
  font-weight: 800;
}
.token {
  position: absolute;
  top: -14px;
  right: -8px;
  font-size: 30px;
  line-height: 1;
  filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.3));
}
.task {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  width: 100%;
  max-width: 420px;
  padding: 14px 16px;
  border-radius: var(--bz-radius-sm);
  background: var(--bz-sun);
  color: var(--bz-on-bright);
}
.task-art {
  font-size: 56px;
  line-height: 1;
}
.task-text {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  text-align: center;
}
</style>
