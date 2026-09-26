<script setup>
import { computed, reactive, watch } from 'vue'
import { config } from '../../config/options'
import { emojiAssetName, splitEmoji } from '../../utils/emoji'

/**
 * The one place emojis are drawn. Sized by font-size like text, so callers style
 * it the same either way. With `config.emoji.baseUrl` set it shows that image set
 * (same look on every device); a sequence like "🐱📦" becomes one image per emoji,
 * and any missing file falls back to the native emoji.
 */
const props = defineProps({
  char: { type: String, required: true },
  /** Accessible name. Without it the emoji is treated as decoration. */
  label: { type: String, default: '' },
})

const failed = reactive(new Set())
watch(
  () => props.char,
  () => failed.clear(),
)

const base = computed(() => (config.emoji.baseUrl ? config.emoji.baseUrl.replace(/\/?$/, '/') : null))
const parts = computed(() =>
  splitEmoji(props.char).map(ch => ({
    ch,
    src: base.value && !failed.has(ch) ? `${base.value}${emojiAssetName(ch)}${config.emoji.ext}` : null,
  })),
)
const single = computed(() => (parts.value.length === 1 ? parts.value[0] : null))
const a11y = computed(() => (props.label ? { role: 'img', 'aria-label': props.label } : { 'aria-hidden': 'true' }))
</script>

<template>
  <img
    v-if="single?.src"
    class="emoji emoji--img"
    :src="single.src"
    :alt="label"
    :aria-hidden="label ? undefined : 'true'"
    draggable="false"
    decoding="async"
    @error="failed.add(single.ch)"
  />
  <span v-else-if="base && parts.length > 1" class="emoji emoji--group" v-bind="a11y">
    <template v-for="(p, i) in parts" :key="i">
      <img
        v-if="p.src"
        class="emoji--img"
        :src="p.src"
        alt=""
        draggable="false"
        decoding="async"
        @error="failed.add(p.ch)"
      />
      <span v-else>{{ p.ch }}</span>
    </template>
  </span>
  <span v-else class="emoji" v-bind="a11y">{{ char }}</span>
</template>

<style scoped>
.emoji {
  line-height: 1;
}
.emoji--img {
  display: inline-block;
  width: 1em;
  height: 1em;
  vertical-align: -0.1em;
  object-fit: contain;
  user-select: none;
  -webkit-user-drag: none;
}
.emoji--group {
  display: inline-flex;
  align-items: center;
  gap: 0.04em;
}
</style>
