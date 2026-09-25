<script setup>
import { computed, ref, watch } from 'vue'
import { config } from '../../config/options'
import { emojiAssetName } from '../../utils/emoji'

/**
 * The one place emojis are drawn. Sized by font-size like text, so callers style
 * it the same either way. With `config.emoji.baseUrl` set it shows that image set
 * (same look on every device) and falls back to the native emoji if a file is missing.
 */
const props = defineProps({
  char: { type: String, required: true },
  /** Accessible name. Without it the emoji is treated as decoration. */
  label: { type: String, default: '' },
})

const failed = ref(false)
watch(() => props.char, () => (failed.value = false))

const src = computed(() => {
  const { baseUrl, ext } = config.emoji
  if (!baseUrl || failed.value) return null
  return `${baseUrl.replace(/\/?$/, '/')}${emojiAssetName(props.char)}${ext}`
})
</script>

<template>
  <img
    v-if="src"
    class="emoji emoji--img"
    :src="src"
    :alt="label"
    :aria-hidden="label ? undefined : 'true'"
    draggable="false"
    decoding="async"
    @error="failed = true"
  />
  <span v-else class="emoji" :role="label ? 'img' : undefined" :aria-label="label || undefined" :aria-hidden="label ? undefined : 'true'">{{ char }}</span>
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
}
</style>
