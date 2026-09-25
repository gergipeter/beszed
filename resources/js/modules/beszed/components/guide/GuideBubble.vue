<script setup>
import CsillamAvatar from './CsillamAvatar.vue'

/** Csillám (tappable) with a speech bubble; the slot is the bubble's content. */
defineProps({
  name: { type: String, default: '' },
  /** Accessible name of the avatar button, i.e. what tapping her does. */
  avatarLabel: { type: String, required: true },
  size: { type: String, default: 'md', validator: v => ['md', 'lg'].includes(v) },
  tag: { type: String, default: 'div' },
})

const emit = defineEmits(['press'])
</script>

<template>
  <component :is="tag" class="guide" :class="`guide--${size}`">
    <button type="button" class="avatar" :aria-label="avatarLabel" @click="emit('press')">
      <CsillamAvatar :name="name" />
    </button>
    <div class="bubble"><slot /></div>
  </component>
</template>

<style scoped>
.guide {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 8px 0 16px;
}
.avatar {
  flex: none;
  width: clamp(100px, 25vw, 140px);
  padding: 0;
}
.guide--lg .avatar {
  width: clamp(130px, 34vw, 180px);
}
.bubble {
  position: relative;
  flex: 1;
  min-width: 0;
  padding: 12px 18px;
  border-radius: 26px;
  background: var(--bz-card);
  box-shadow: 0 6px 0 rgba(59, 31, 74, 0.12);
}
.bubble::before {
  content: '';
  position: absolute;
  left: -13px;
  top: 50%;
  transform: translateY(-50%);
  border: 13px solid transparent;
  border-left: 0;
  border-right-color: var(--bz-card);
}
</style>
