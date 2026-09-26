<script setup>
import { ref, watch } from 'vue'
import BzButton from '../../components/ui/BzButton.vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import OptionGrid from '../../components/ui/OptionGrid.vue'
import OptionTile from '../../components/ui/OptionTile.vue'
import { useShake } from '../../composables/useShake'
import { useTimers } from '../../composables/useTimers'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import { engineEmits, engineProps } from '../contract'

/**
 * "Mi tűnt el?" (Kim's game). Look: the pictures stay for `lookMs` after the
 * prompt (a picture says its name when tapped; "Megvan!" skips the wait).
 * Hide: a sparkle cloud covers them. Guess: they come back with one gap; the
 * child picks the missing one from the choices.
 * data: VanishData
 */
const props = defineProps(engineProps)
const emit = defineEmits(engineEmits)

const HIDE_MS = 1100

/** 'look' → 'hide' → 'guess' */
const phase = ref('look')
const good = ref(null)
const { shaking, shake } = useShake()
const { later } = useTimers()
let timer = null

function hide() {
  if (phase.value !== 'look') return
  clearTimeout(timer)
  phase.value = 'hide'
  later(() => {
    phase.value = 'guess'
    emit('say', props.data.question)
  }, HIDE_MS)
}

// The looking time starts once Csillám has said the prompt.
watch(
  () => props.promptDone,
  done => {
    if (done && phase.value === 'look' && !timer) timer = later(hide, props.data.lookMs)
  },
  { immediate: true },
)

function look(item) {
  if (phase.value === 'look') emit('say', item.label)
}

function choose(id) {
  if (props.locked || phase.value !== 'guess') return
  if (id === props.data.missing) {
    good.value = id
    emit('answer', { correct: true, say: props.data.onCorrect })
    return
  }
  shake(id)
  emit('answer', { correct: false, say: props.data.onWrong })
}
</script>

<template>
  <div class="board" :class="`board--${phase}`" :style="{ '--n': data.items.length }">
    <template v-for="(item, i) in data.items" :key="item.id">
      <span v-if="phase === 'guess' && item.id === data.missing" class="gap" :class="{ 'gap--found': good }">
        <EmojiArt v-if="good" class="art" :char="item.emoji" />
        <template v-else>?</template>
      </span>
      <button
        v-else
        type="button"
        class="item"
        :style="{ '--i': i }"
        :aria-label="item.label"
        :disabled="phase !== 'look'"
        @click="look(item)"
      >
        <EmojiArt class="art" :char="item.emoji" />
      </button>
    </template>
    <EmojiArt class="cloud" :char="ICONS.sparkles" aria-hidden="true" />
  </div>

  <BzButton v-if="phase === 'look'" :icon="ICONS.thumbsUp" @click="hide">{{ t('vanish.ready') }}</BzButton>

  <OptionGrid v-if="phase === 'guess'" :columns="data.options.length === 4 ? 4 : 3">
    <OptionTile
      v-for="o in data.options"
      :key="o.id"
      :emoji="o.emoji"
      :good="good === o.id"
      :shake="shaking === o.id"
      :aria-label="o.label"
      @click="choose(o.id)"
    />
  </OptionGrid>
</template>

<style scoped>
.board {
  position: relative;
  display: grid;
  grid-template-columns: repeat(min(var(--n), 3), 1fr);
  gap: 12px;
  width: 100%;
  max-width: 460px;
  padding: 16px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
}
.item,
.gap {
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  border-radius: var(--bz-radius);
  background: var(--bz-soft);
  transition:
    transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1),
    opacity 0.3s;
}
.item:disabled {
  cursor: default;
}
.art {
  font-size: clamp(40px, 12vw, 68px);
  line-height: 1;
}
.gap {
  border: 4px dashed var(--bz-coral);
  background: transparent;
  color: var(--bz-coral);
  font-size: 40px;
  font-weight: 800;
}
.gap--found {
  border-style: solid;
  border-color: var(--bz-leaf);
  background: color-mix(in srgb, var(--bz-leaf) 16%, var(--bz-card));
}
/* hide: the pictures shrink away under a sparkle cloud (transform/opacity only) */
.cloud {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-size: 110px;
  opacity: 0;
  transform: scale(0.4);
  pointer-events: none;
}
.board--hide .item {
  opacity: 0;
  transform: scale(0.5);
  transition-delay: calc(var(--i) * 40ms);
}
.board--hide .cloud {
  opacity: 1;
  transform: scale(1);
  transition:
    transform 0.45s cubic-bezier(0.2, 1.5, 0.4, 1),
    opacity 0.25s;
  animation: twinkle 0.9s ease-in-out infinite alternate;
}
@keyframes twinkle {
  to {
    transform: scale(1.12) rotate(12deg);
  }
}
</style>
