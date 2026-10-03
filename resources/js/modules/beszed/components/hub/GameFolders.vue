<script setup>
import { computed, ref } from 'vue'
import { t } from '../../i18n'
import { buzz } from '../../services/touch/feel'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * The hub's folders: the games grouped by what they develop (config/beszed_folders.php).
 * Made for children who cannot read yet (4–5 years): big round "sticker" bubbles, a huge picture,
 * a short name and a star count; no descriptions on screen (they are there for screen readers and
 * parents). Tapping a bubble opens it; its games pop in one after another.
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
          :aria-label="`${f.name}. ${f.develops}. ${t('hub.folderMedals', { done: f.won, total: f.total })}`"
          @click="openFolder(f.id)"
        >
          <span class="blob" aria-hidden="true" />
          <span v-if="f.won" class="badge" aria-hidden="true">⭐ {{ f.won }}</span>
          <span class="face" aria-hidden="true">
            <EmojiArt class="icon" :char="f.emoji" />
          </span>
          <b class="name" aria-hidden="true">{{ f.name }}</b>
        </button>
      </div>

      <div v-else :key="current.id" class="open" :style="{ '--c': current.color }">
        <header class="open-head">
          <button type="button" class="back" :aria-label="t('hub.folderBack')" @click="close">
            <span aria-hidden="true">⬅</span>
          </button>
          <EmojiArt class="head-icon" :char="current.emoji" />
          <h2>{{ current.name }}</h2>
          <p class="sr-only">{{ current.develops }}</p>
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
            :aria-label="`${g.name}. ${g.skill}`"
            @click="emit('play', g.id, $event.currentTarget.querySelector('.tile'))"
          >
            <span class="tile" aria-hidden="true">
              <EmojiArt class="art" :char="g.emoji" />
            </span>
            <b class="gname" aria-hidden="true">{{ g.name }}</b>
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
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
  border: 0;
}

/* ---- the folders: big round stickers, two to a row ---- */
.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 22px 18px;
  padding: 8px 6px 14px;
}
@media (min-width: 640px) {
  .grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 28px 24px;
  }
}
.folder {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 190px;
  padding: 14px 8px 16px;
  text-align: center;
  color: #3b1f4a;
  transform: rotate(var(--tilt, 0deg));
  animation: folder-in 0.55s var(--bz-spring) backwards;
  animation-delay: calc(var(--i) * 60ms);
  transition: transform 0.38s var(--bz-spring);
}
/* every sticker sits a little crooked, and not the same way */
.folder:nth-child(4n + 1) { --tilt: -2.5deg; --shape: var(--bz-radius-lg); }
.folder:nth-child(4n + 2) { --tilt: 2deg; --shape: var(--bz-radius-lg); }
.folder:nth-child(4n + 3) { --tilt: 1.5deg; --shape: var(--bz-radius-lg); }
.folder:nth-child(4n) { --tilt: -2deg; --shape: var(--bz-radius-lg); }
/* a plain zoom (a transition): swapping the animation would replay the entrance, and the sticker would vanish */
.folder:hover,
.folder:focus-visible {
  transform: scale(1.07) rotate(var(--tilt, 0deg));
}
.folder:active {
  transform: scale(0.92) rotate(var(--tilt, 0deg));
  transition-duration: 0.07s;
}
/* the coloured shape, a thick white edge like a sticker; a touch more vivid than the palette */
.blob {
  position: absolute;
  inset: 0;
  border-radius: var(--shape, var(--bz-radius-lg));
  background: linear-gradient(160deg, var(--c), color-mix(in srgb, var(--c) 70%, #fff));
  filter: saturate(1.7);
  box-shadow:
    0 0 0 6px #fff,
    0 12px 0 6px rgba(0, 0, 0, 0.18),
    0 18px 28px rgba(0, 0, 0, 0.25);
}
.face {
  position: relative;
  display: grid;
  place-items: center;
  width: 96px;
  aspect-ratio: 1;
  border-radius: 50%;
  background: #fff;
  box-shadow: inset 0 -6px 0 rgba(0, 0, 0, 0.08);
}
.icon {
  font-size: 62px;
  line-height: 1;
  animation: bob 2.8s ease-in-out infinite;
  animation-delay: calc(var(--i) * -0.4s);
}
.name {
  position: relative;
  max-width: 96%;
  font-size: clamp(20px, 5.4vw, 24px);
  line-height: 1.1;
  text-shadow: 0 2px 0 rgba(255, 255, 255, 0.55);
  overflow-wrap: anywhere;
}
.badge {
  position: absolute;
  top: -6px;
  right: -4px;
  z-index: 1;
  padding: 4px 12px 5px;
  border-radius: var(--bz-radius-pill);
  background: #ffd23f;
  box-shadow:
    0 0 0 4px #fff,
    0 4px 0 4px rgba(0, 0, 0, 0.15);
  font-size: 18px;
  font-weight: 800;
  line-height: 1.1;
  transform: rotate(8deg);
}

/* ---- an open folder ---- */
.open-head {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: var(--bz-radius-lg);
  background: linear-gradient(160deg, var(--c), color-mix(in srgb, var(--c) 70%, #fff));
  color: #3b1f4a;
  box-shadow:
    0 0 0 5px #fff,
    0 10px 0 5px rgba(0, 0, 0, 0.16);
  filter: saturate(1.5);
  animation: head-in 0.45s var(--bz-spring) backwards;
}
.open-head > * {
  filter: saturate(0.67); /* keep the text and pictures themselves at their normal colour */
}
.back {
  flex: none;
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 5px 0 rgba(0, 0, 0, 0.18);
  font-size: 32px;
  line-height: 1;
  transition: transform 0.3s var(--bz-spring);
}
.back:active {
  transform: scale(0.88);
}
.head-icon {
  flex: none;
  font-size: 56px;
  line-height: 1;
  animation: bob 2.6s ease-in-out infinite;
}
h2 {
  margin: 0;
  font-size: clamp(24px, 6vw, 32px);
  line-height: 1.05;
}
.games {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 22px 16px;
  margin-top: 26px;
  padding: 0 4px 10px;
}
@media (min-width: 640px) {
  .games {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
.game {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 16px 8px 14px;
  border-radius: var(--bz-radius-lg);
  background: var(--bz-card);
  box-shadow:
    0 0 0 4px var(--g),
    0 10px 0 4px color-mix(in srgb, var(--g) 65%, #000),
    0 16px 24px rgba(0, 0, 0, 0.2);
  text-align: center;
  animation: game-in 0.55s var(--bz-spring) backwards;
  animation-delay: calc(0.12s + var(--i) * 80ms);
  transition: transform 0.38s var(--bz-spring);
}
.game:hover,
.game:focus-visible {
  transform: scale(1.06);
}
.game:active {
  transform: scale(0.9);
  transition-duration: 0.07s;
}
/* today's adventure: a bouncing star on the corner (the flag was too small to see) */
.game--today::after {
  content: '⭐';
  position: absolute;
  top: -16px;
  right: -8px;
  font-size: 34px;
  line-height: 1;
  filter: drop-shadow(0 3px 0 rgba(0, 0, 0, 0.2));
  animation: hop 1.1s ease-in-out infinite;
}
.tile {
  display: grid;
  place-items: center;
  width: 108px;
  aspect-ratio: 1;
  border-radius: 46% 54% 50% 50% / 52% 48% 52% 48%;
  background: var(--g);
  box-shadow: inset 0 -7px 0 rgba(0, 0, 0, 0.12);
  animation: bob 3s ease-in-out infinite;
  animation-delay: calc(var(--i) * -0.5s);
}
.art {
  font-size: 64px;
  line-height: 1;
}
.gname {
  font-size: clamp(18px, 4.8vw, 21px);
  line-height: 1.1;
  overflow-wrap: anywhere;
}
.stars {
  display: flex;
  gap: 4px;
  font-size: 28px;
  line-height: 1;
}
.stars i {
  font-style: normal;
  color: rgba(120, 100, 140, 0.22);
}
.stars i.on {
  color: #ffb400;
  text-shadow: 0 2px 0 rgba(0, 0, 0, 0.18);
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
    transform: translateY(26px) scale(0.7) rotate(-8deg);
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
    transform: translateY(30px) scale(0.7) rotate(-6deg);
  }
}
@keyframes bob {
  50% {
    transform: translateY(-6px) rotate(-4deg);
  }
}
@keyframes hop {
  50% {
    transform: translateY(-8px) rotate(10deg) scale(1.1);
  }
}
@keyframes wiggle {
  20% {
    transform: rotate(calc(var(--tilt, 0deg) - 3deg)) scale(1.04);
  }
  50% {
    transform: rotate(calc(var(--tilt, 0deg) + 3deg)) scale(1.04);
  }
  80% {
    transform: rotate(calc(var(--tilt, 0deg) - 1.5deg)) scale(1.02);
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
  .icon,
  .head-icon,
  .tile,
  .game--today::after,
  .stars i.on {
    animation: none;
  }
}
</style>
