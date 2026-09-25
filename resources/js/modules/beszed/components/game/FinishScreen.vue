<script setup>
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import CsillamAvatar from '../guide/CsillamAvatar.vue'
import BzButton from '../ui/BzButton.vue'
import EmojiArt from '../ui/EmojiArt.vue'

defineProps({
  stars: { type: Number, required: true },
  guideName: { type: String, default: '' },
})

const emit = defineEmits(['again', 'exit'])
</script>

<template>
  <div class="finish">
    <div class="avatar"><CsillamAvatar :name="guideName" /></div>
    <h2 class="title">{{ t('game.finishTitle') }}</h2>
    <p class="earned" role="img" :aria-label="t('game.starsLabel', { count: stars })">
      <EmojiArt v-for="n in stars" :key="n" :char="ICONS.star" />
    </p>
    <div class="bz-row">
      <BzButton variant="primary" @click="emit('again')">{{ t('game.again') }}</BzButton>
      <BzButton @click="emit('exit')">{{ t('game.otherGame') }}</BzButton>
    </div>
  </div>
</template>

<style scoped>
.finish {
  padding-top: 30px;
  text-align: center;
}
.avatar {
  width: min(240px, 62vw);
  margin: 0 auto;
}
.title {
  margin: 10px 0 0;
  font-size: 38px;
}
.earned {
  margin: 6px 0 26px;
  font-size: 34px;
  letter-spacing: 4px;
}
</style>
