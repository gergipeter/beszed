<script setup>
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import BzIconButton from '../ui/BzIconButton.vue'
import EmojiArt from '../ui/EmojiArt.vue'

/** Top bar while playing: home, round progress, stars, mute, "say it again, slowly". */
defineProps({
  total: { type: Number, default: 0 },
  /** Rounds completed so far. */
  done: { type: Number, default: 0 },
  stars: { type: Number, default: 0 },
  showProgress: { type: Boolean, default: false },
  muted: { type: Boolean, default: false },
})

const emit = defineEmits(['exit', 'toggleMute', 'replaySlow'])
</script>

<template>
  <div class="hud">
    <BzIconButton :icon="ICONS.home" :label="t('common.back')" @click="emit('exit')" />
    <div
      v-if="showProgress"
      class="dots"
      role="progressbar"
      :aria-label="t('game.progressLabel')"
      aria-valuemin="0"
      :aria-valuemax="total"
      :aria-valuenow="done"
    >
      <i v-for="n in total" :key="n" :class="{ on: n <= done }" />
    </div>
    <span class="stars" :aria-label="t('game.starsLabel', { count: stars })">
      <EmojiArt :char="ICONS.star" /> {{ stars }}
    </span>
    <BzIconButton
      :icon="muted ? ICONS.mute : ICONS.speaker"
      :label="t(muted ? 'game.unmuteLabel' : 'game.muteLabel')"
      @click="emit('toggleMute')"
    />
    <BzIconButton :icon="ICONS.turtle" :label="t('game.replaySlowLabel')" @click="emit('replaySlow')" />
  </div>
</template>

<style scoped>
.hud {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}
.dots {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px;
}
.dots i {
  display: block;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: var(--bz-card);
}
.dots i.on {
  background: var(--bz-sun);
}
.stars {
  flex: none;
  margin-left: auto;
  padding: 4px 12px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-sun);
  color: var(--bz-on-bright);
  font-size: 19px;
  font-weight: 800;
}
.dots + .stars {
  margin-left: 0;
}
</style>
