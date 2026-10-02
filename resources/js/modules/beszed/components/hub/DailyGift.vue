<script setup>
import { ref } from 'vue'
import { ICONS } from '../../config/icons'
import { t } from '../../i18n'
import EmojiArt from '../ui/EmojiArt.vue'

/**
 * The daily gift: a present to open once a day. It is always a flower for the garden
 * (never a surprise box), and a day without it costs nothing. Quiet once opened.
 */
defineProps({
  available: { type: Boolean, required: true },
})
const emit = defineEmits(['open'])

const justOpened = ref(false)
function open() {
  justOpened.value = true
  emit('open')
}
</script>

<template>
  <button v-if="available" type="button" class="gift" @click="open">
    <EmojiArt class="box" :char="ICONS.gift" />
    <span class="text">
      <b>{{ t('hub.gift.ready') }}</b>
      <small>{{ t('hub.gift.open') }}</small>
    </span>
  </button>
  <p v-else-if="justOpened" class="gift gift--opened" role="status">
    <EmojiArt class="box box--flower" :char="ICONS.flower" />
    <span class="text">
      <b>{{ t('hub.gift.opened') }}</b>
    </span>
  </p>
</template>

<style scoped>
.gift {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  margin: 0 0 16px;
  padding: 12px 16px;
  border-radius: var(--bz-radius-lg);
  background: linear-gradient(135deg, #ffd86b, #ff9fb8);
  color: var(--bz-on-bright);
  text-align: left;
  box-shadow: var(--bz-shadow);
  transition: transform 0.3s var(--bz-spring);
}
button.gift:active {
  transform: scale(0.97);
}
.box {
  flex: none;
  font-size: 46px;
  line-height: 1;
  animation: wiggle 1.8s ease-in-out infinite;
}
.gift--opened {
  background: color-mix(in srgb, var(--bz-leaf) 22%, var(--bz-card));
  color: var(--bz-ink);
  animation: pop 0.6s var(--bz-spring);
}
.box--flower {
  animation: pop 0.7s var(--bz-spring) backwards;
}
.text {
  display: grid;
  gap: 2px;
  font-size: var(--bz-text-md);
  line-height: 1.15;
}
.text small {
  font-size: var(--bz-text-sm);
  font-weight: 700;
  opacity: 0.85;
}
@keyframes wiggle {
  0%,
  60%,
  100% {
    transform: rotate(0);
  }
  70% {
    transform: rotate(-10deg) scale(1.08);
  }
  80% {
    transform: rotate(10deg) scale(1.08);
  }
  90% {
    transform: rotate(-6deg);
  }
}
@keyframes pop {
  from {
    transform: scale(0.4);
    opacity: 0;
  }
}
</style>
