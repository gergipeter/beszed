<script setup>
import SceneBackdrop from '../../components/rewards/SceneBackdrop.vue'
import EmojiArt from '../../components/ui/EmojiArt.vue'

/**
 * The whole puzzle picture: the picture standing in its scene (from level 4),
 * and for a fairy tale the thing that goes with it (Hófehérke's apple). Every
 * piece shows its part of this, the example shows it small, the solved puzzle
 * shows it whole. Sized from its own box (cqmin), so all of them match.
 */
defineProps({
  emoji: { type: String, required: true },
  prop: { type: String, default: null },
  scene: { type: String, default: null },
})
</script>

<template>
  <span class="art" :class="{ 'art--scene': scene }" data-peek-art>
    <SceneBackdrop v-if="scene" :scene="scene" mini />
    <EmojiArt class="main" :char="emoji" />
    <EmojiArt v-if="prop" class="prop" :char="prop" />
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
.prop {
  position: absolute;
  right: 7%;
  bottom: 7%;
  font-size: 26cqmin;
  filter: drop-shadow(0 1cqmin 0.6cqmin rgba(0, 0, 0, 0.25));
}
</style>
