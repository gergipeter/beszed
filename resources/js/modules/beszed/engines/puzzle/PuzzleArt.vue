<script setup>
import { computed } from 'vue'
import SceneBackdrop from '../../components/rewards/SceneBackdrop.vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'
import { aiPic } from '../../utils/emoji'

/**
 * The whole puzzle picture: the picture standing in its scene (from level 4),
 * and for a fairy tale the thing that goes with it (Hófehérke's apple). Every
 * piece shows its part of this, the example shows it small, the solved puzzle
 * shows it whole. Sized from its own box (cqmin), so all of them match.
 *
 * An AI picture ("ai:koala") is already a whole illustrated scene with its own
 * background, not a small glyph to place inside one: it fills the frame edge
 * to edge instead, with no SceneBackdrop or prop drawn on top of it.
 */
const props = defineProps({
  emoji: { type: String, required: true },
  prop: { type: String, default: null },
  scene: { type: String, default: null },
})
const full = computed(() => Boolean(aiPic(props.emoji)))
</script>

<template>
  <span class="art" :class="{ 'art--scene': scene && !full, 'art--full': full }" data-peek-art>
    <SceneBackdrop v-if="scene && !full" :scene="scene" mini />
    <EmojiArt class="main" :char="emoji" />
    <EmojiArt v-if="prop && !full" class="prop" :char="prop" />
  </span>
</template>

<style scoped>
.art {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  container-type: size;
  line-height: 1;
}
.main {
  position: relative;
  font-size: 80cqmin;
}
/* in a scene: a little smaller and higher, standing on the ground */
.art--scene .main {
  font-size: 58cqmin;
  translate: 0 -6cqmin;
  filter: drop-shadow(0 2cqmin 1cqmin rgba(0, 0, 0, 0.25));
}
/* an AI picture is its own whole scene: fill the frame, no glyph-sized box or drop shadow */
.art--full .main {
  font-size: 100cqmin;
  width: 100%;
  height: 100%;
}
.art--full .main :deep(.emoji--img) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.prop {
  position: absolute;
  right: 7%;
  bottom: 7%;
  font-size: 26cqmin;
  filter: drop-shadow(0 1cqmin 0.6cqmin rgba(0, 0, 0, 0.25));
}
</style>
