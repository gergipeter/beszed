<script setup>
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * The switch between the games' two views, the folders and the garden. The "Játékok"
 * heading is only for screen readers: the tabs say what this is, and the room goes to the games.
 */
defineProps({
  view: { type: String, required: true },
})
defineEmits(['change'])

const VIEWS = [
  { id: 'folders', icon: '🗂️' },
  { id: 'garden', icon: '🌳' },
]
</script>

<template>
  <div class="games-tabs">
    <h2 class="bz-sr-only"><EmojiArt :char="ICONS.games" /> {{ t('hub.gamesTitle') }}</h2>
    <div class="views" role="tablist">
      <button
        v-for="v in VIEWS"
        :key="v.id"
        type="button"
        role="tab"
        class="view"
        :class="{ 'view--on': view === v.id }"
        :aria-selected="view === v.id"
        @click="$emit('change', v.id)"
      >
        <EmojiArt :char="v.icon" /> {{ t(`hub.views.${v.id}`) }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.games-tabs {
  margin: 0;
}
.views {
  display: flex;
  gap: 8px;
}
.view {
  padding: 6px 14px;
  border-radius: var(--bz-radius-pill);
  background: var(--bz-card);
  font-weight: 800;
  font-size: var(--bz-text-md);
  box-shadow: var(--bz-shadow-sm);
  transition: transform 0.3s var(--bz-spring), background 0.2s;
}
.view--on {
  background: var(--bz-sun);
  transform: scale(1.06);
}
/* a phone: smaller, so both tabs and the map button share one row */
@media (max-width: 519px) {
  .views {
    gap: 6px;
  }
  .view {
    padding: 6px 10px;
    font-size: var(--bz-text-sm);
  }
}
</style>
