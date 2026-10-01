<script setup>
import { computed, ref } from 'vue'
import { t } from '../../i18n'
import { buzz } from '../../services/touch/feel'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * The hub's folders: the games grouped by what they develop (config/beszed_folders.php).
 * A grid of folder cards; tapping one opens it (the card's colour floods the header,
 * its games pop in one after another). Each card shows how many medals are won in it.
 */
const props = defineProps({
  /** @type {import('vue').PropType<{ id: string, name: string, emoji: string, color: string, develops: string, games: string[] }[]>} */
  folders: { type: Array, required: true },
  /** @type {import('vue').PropType<import('../../types').GameMeta[]>} */
  games: { type: Array, required: true },
  /** game id → 0–3 medals */
  medals: { type: Object, default: () => ({}) },
  /** Today's adventure: game ids in order. */
  pathGames: { type: Array, default: () => [] },
})
const emit = defineEmits(['play'])

const openId = ref(null)
const open = computed(() => props.folders.find(f => f.id === openId.value) ?? null)
const byId = computed(() => Object.fromEntries(props.games.map(g => [g.id, g])))

const cards = computed(() =>
  props.folders
    .map(f => {
      const games = f.games.map(id => byId.value[id]).filter(Boolean)
      const won = games.reduce((sum, g) => sum + (props.medals[g.id] ?? 0), 0)
      return { ...f, list: games, won, total: games.length * 3 }
    })
    .filter(f => f.list.length),
)
const current = computed(() => cards.value.find(f => f.id === openId.value) ?? null)

function openFolder(id) {
  buzz(10)
  openId.value = id
  requestAnimationFrame(() => document.querySelector('.folders')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))
}
function close() {
  buzz(8)
  openId.value = null
}
const medalsOf = id => props.medals[id] ?? 0
</script>

<template>
  <section class="folders" :aria-label="t('hub.views.folders')">
    <Transition name="folder" mode="out-in">
      <div v-if="!current" key="grid" class="grid">
        <button
          v-for="(f, i) in cards"
          :key="f.id"
          type="button"
          class="folder"
          :style="{ '--c': f.color, '--i': i }"
          @click="openFolder(f.id)"
        >
          <span class="tab" aria-hidden="true" />
          <span class="body">
            <EmojiArt class="icon" :char="f.emoji" />
            <b class="name">{{ f.name }}</b>
            <small class="develops">{{ f.develops }}</small>
            <span class="meta">
              <span>{{ t('hub.folderGames', { count: f.list.length }) }}</span>
              <span class="bar" :aria-label="t('hub.folderMedals', { done: f.won, total: f.total })">
                <i :style="{ width: `${f.total ? (f.won / f.total) * 100 : 0}%` }" />
              </span>
            </span>
          </span>
        </button>
      </div>

      <div v-else :key="current.id" class="open" :style="{ '--c': current.color }">
        <header class="open-head">
          <button type="button" class="back" @click="close">← {{ t('hub.folderBack') }}</button>
          <div class="title">
            <EmojiArt class="icon" :char="current.emoji" />
            <div>
              <h2>{{ current.name }}</h2>
              <p>{{ current.develops }}</p>
            </div>
          </div>
        </header>

        <div class="games">
          <button
            v-for="(g, i) in current.list"
            :key="g.id"
            type="button"
            class="game"
            :class="{ 'game--today': pathGames.includes(g.id) }"
            :style="{ '--g': g.color, '--i': i }"
            :data-game="g.id"
            @click="emit('play', g.id, $event.currentTarget.querySelector('.tile'))"
          >
            <span class="tile">
              <EmojiArt class="art" :char="g.emoji" />
            </span>
            <b class="gname">{{ g.name }}</b>
            <small class="skill">{{ g.skill }}</small>
            <span class="stars" aria-hidden="true">
              <i v-for="n in 3" :key="n" :class="{ on: n <= medalsOf(g.id) }">★</i>
            </span>
          </button>
        </div>
      </div>
    </Transition>
  </section>
</template>

<style scoped>
.folders {
  margin-top: 6px;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(240px, 100%), 1fr));
  gap: 22px 16px;
}

/* ---- a folder card: a tab on top, the coloured body below ---- */
.folder {
  position: relative;
  display: block;
  padding-top: 18px;
  text-align: left;
  animation: folder-in 0.5s var(--bz-spring) backwards;
  animation-delay: calc(var(--i) * 55ms);
  transition: transform 0.38s var(--bz-spring);
}
.folder:active {
  transform: scale(0.95);
  transition-duration: 0.07s;
}
.tab {
  position: absolute;
  top: 0;
  left: 14px;
  width: 44%;
  height: 26px;
  border-radius: 14px 14px 0 0;
  background: color-mix(in srgb, var(--c) 82%, #000);
}
.body {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-height: 176px;
  padding: 16px 16px 14px;
  border-radius: 6px 26px 26px 26px;
  background: linear-gradient(160deg, var(--c), color-mix(in srgb, var(--c) 78%, #fff));
  color: #3b1f4a;
  box-shadow: 0 6px 0 color-mix(in srgb, var(--c) 70%, #000), var(--bz-shadow-lg);
}
.icon {
  font-size: 46px;
  line-height: 1;
  transition: transform 0.5s var(--bz-spring);
}
.folder:hover .icon,
.folder:focus-visible .icon {
  transform: rotate(-8deg) scale(1.12);
}
.name {
  font-size: 20px;
  line-height: 1.15;
}
.develops {
  font-size: 13.5px;
  line-height: 1.25;
  font-weight: 600;
  opacity: 0.8;
}
.meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-top: auto;
  padding-top: 8px;
  font-size: 13px;
  font-weight: 800;
}
.bar {
  flex: 1;
  max-width: 90px;
  height: 9px;
  border-radius: 9px;
  background: rgba(255, 255, 255, 0.55);
  overflow: hidden;
}
.bar i {
  display: block;
  height: 100%;
  border-radius: 9px;
  background: #3b1f4a;
  transition: width 0.8s var(--bz-spring);
}

/* ---- an open folder ---- */
.open-head {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px 16px 16px;
  border-radius: 26px;
  background: linear-gradient(160deg, var(--c), color-mix(in srgb, var(--c) 78%, #fff));
  color: #3b1f4a;
  box-shadow: var(--bz-shadow-lg);
  animation: head-in 0.45s var(--bz-spring) backwards;
}
.back {
  align-self: flex-start;
  padding: 6px 14px;
  border-radius: var(--bz-radius-pill);
  background: rgba(255, 255, 255, 0.7);
  font-weight: 800;
  font-size: 15px;
}
.title {
  display: flex;
  align-items: center;
  gap: 14px;
}
.title .icon {
  font-size: 52px;
  animation: bob 2.6s ease-in-out infinite;
}
h2 {
  margin: 0;
  font-size: clamp(22px, 5vw, 30px);
  line-height: 1.1;
}
.title p {
  margin: 3px 0 0;
  font-size: 14px;
  font-weight: 600;
  opacity: 0.85;
}
.games {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(150px, 46%), 1fr));
  gap: 16px 12px;
  margin-top: 18px;
}
.game {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 14px 8px 12px;
  border-radius: 26px;
  background: var(--bz-card);
  box-shadow: var(--bz-shadow-lg);
  text-align: center;
  animation: game-in 0.5s var(--bz-spring) backwards;
  animation-delay: calc(0.12s + var(--i) * 70ms);
  transition: transform 0.38s var(--bz-spring);
}
.game:active {
  transform: scale(0.93);
  transition-duration: 0.07s;
}
.game--today::after {
  content: '🚩';
  position: absolute;
  top: -8px;
  right: 6px;
  font-size: 22px;
}
.tile {
  display: grid;
  place-items: center;
  width: 78px;
  aspect-ratio: 1;
  border-radius: 46% 54% 50% 50% / 52% 48% 52% 48%;
  background: var(--g);
  box-shadow: inset 0 -5px 0 rgba(0, 0, 0, 0.1);
}
.art {
  font-size: 44px;
  line-height: 1;
}
.gname {
  margin-top: 4px;
  font-size: 16px;
  line-height: 1.15;
}
.skill {
  font-size: 12.5px;
  line-height: 1.2;
  color: var(--bz-muted);
}
.stars {
  display: flex;
  gap: 2px;
  margin-top: 4px;
  font-size: 18px;
  line-height: 1;
}
.stars i {
  font-style: normal;
  color: rgba(120, 100, 140, 0.25);
}
.stars i.on {
  color: #ffb400;
  animation: star-pop 0.6s var(--bz-spring) backwards;
}

/* ---- transitions ---- */
.folder-enter-active,
.folder-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.25s ease;
}
.folder-enter-from {
  opacity: 0;
  transform: translateY(14px) scale(0.97);
}
.folder-leave-to {
  opacity: 0;
  transform: scale(0.96);
}
@keyframes folder-in {
  from {
    opacity: 0;
    transform: translateY(22px) scale(0.9);
  }
}
@keyframes head-in {
  from {
    opacity: 0;
    transform: scaleY(0.6) translateY(-10px);
    transform-origin: top;
  }
}
@keyframes game-in {
  from {
    opacity: 0;
    transform: translateY(26px) scale(0.8) rotate(-4deg);
  }
}
@keyframes bob {
  50% {
    transform: translateY(-5px) rotate(-5deg);
  }
}
@keyframes star-pop {
  from {
    transform: scale(0) rotate(-90deg);
  }
}
@media (prefers-reduced-motion: reduce) {
  .folder,
  .game,
  .open-head,
  .title .icon,
  .stars i.on {
    animation: none;
  }
}
</style>
