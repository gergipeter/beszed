<script setup>
import { computed } from 'vue'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import EmojiArt from '../ui/EmojiArt.vue'

/** "Mai kaland": Csillám's three games for today as a little path; played steps get a tick. */
const props = defineProps({
  /** @type {import('vue').PropType<import('../../types').DailyPath>} */
  path: { type: Object, required: true },
  /** @type {import('vue').PropType<import('../../types').GameMeta[]>} */
  games: { type: Array, required: true },
})
const emit = defineEmits(['play'])

const steps = computed(() =>
  props.path.games
    .map(id => props.games.find(g => g.id === id))
    .filter(Boolean)
    .map(game => ({ game, done: props.path.done.includes(game.id) })),
)
/** Letters in the longest word: the name shrinks until that word fits the tile. */
const longest = name => Math.max(...name.split(/\s+/).map(w => w.length))
</script>

<template>
  <section class="path" :class="{ 'path--done': path.completed }" :aria-label="t('daily.title')">
    <header class="head">
      <EmojiArt class="head-icon" :char="ICONS.map" />
      <h2 class="title">{{ t('daily.title') }}</h2>
      <span class="count">{{ t('daily.count', { done: path.done.length, total: steps.length }) }}</span>
    </header>

    <ol class="steps">
      <li v-for="(s, i) in steps" :key="s.game.id" class="step-wrap" :style="{ '--i': i }">
        <button
          type="button"
          class="step"
          :class="{ 'step--done': s.done }"
          :style="{ '--tile-color': s.game.color, '--len': longest(s.game.name) }"
          :aria-label="t(s.done ? 'daily.stepDone' : 'daily.step', { n: i + 1, game: s.game.name })"
          @click="emit('play', s.game.id)"
        >
          <span class="badge" aria-hidden="true">
            <EmojiArt v-if="s.done" :char="ICONS.check" />
            <template v-else>{{ i + 1 }}</template>
          </span>
          <EmojiArt class="art" :char="s.game.emoji" />
          <span class="name">{{ s.game.name }}</span>
        </button>
      </li>
    </ol>

    <p v-if="path.completed" class="finished">
      <EmojiArt :char="ICONS.party" /> {{ t('daily.completed') }}
    </p>
  </section>
</template>

<style scoped>
.path {
  margin: 0 0 12px;
  padding: 8px 10px 10px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow);
}
.head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.head-icon {
  font-size: 20px;
}
.title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
}
.count {
  margin-left: auto;
  padding: 2px 10px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-soft);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.steps {
  position: relative;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
/* the dotted trail between the steps */
.steps::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 12%;
  right: 12%;
  border-top: 4px dotted var(--bz-guide);
  opacity: 0.6;
}
.step-wrap {
  position: relative;
  animation: step-in 0.42s cubic-bezier(0.2, 0.8, 0.2, 1) backwards;
  animation-delay: calc(var(--i, 0) * 60ms);
}
@keyframes step-in {
  from {
    opacity: 0;
    transform: translate3d(0, 14px, 0) scale(0.94);
  }
}
.step {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  min-height: 78px;
  padding: 8px 4px 6px;
  border-radius: 18px;
  background: var(--tile-color, var(--bz-soft));
  color: var(--bz-on-bright);
  box-shadow: 0 5px 0 rgba(59, 31, 74, 0.18);
  transition: transform 0.1s ease;
  container-type: inline-size;
}
.step:active {
  transform: translateY(3px) scale(0.97);
}
.step--done .art,
.step--done .name {
  opacity: 0.55;
}
.badge {
  position: absolute;
  top: -8px;
  left: -6px;
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  font-weight: 800;
  box-shadow: var(--bz-shadow-sm);
}
.step--done .badge {
  background: var(--bz-leaf-deep, var(--bz-leaf));
  color: var(--bz-on-accent);
  font-size: 16px;
}
.art {
  font-size: 32px;
  line-height: 1.1;
}
.name {
  margin-top: 2px;
  /* ~0.57em per letter in Baloo 2 bold: long compounds (Árnyékkereső) get smaller instead of breaking */
  font-size: min(14px, calc(100cqi / (var(--len, 8) * 0.57)));
  font-weight: 800;
  line-height: 1.1;
  text-align: center;
  hyphens: auto;
  overflow-wrap: anywhere;
}
.finished {
  margin: 12px 0 0;
  font-size: var(--bz-text-md);
  font-weight: 800;
  text-align: center;
}
@media (max-width: 380px) {
  .steps {
    gap: 6px;
  }
  .art {
    font-size: 30px;
  }
  .name {
    font-size: min(13px, calc(100cqi / (var(--len, 8) * 0.57)));
  }
}
@media (prefers-reduced-motion: reduce) {
  .step-wrap {
    animation: none;
  }
}
</style>
