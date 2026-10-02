<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { config } from '../../config/options'
import { emojiAssetName, pictogram, symbol, upload, splitEmoji } from '../../utils/emoji'

/**
 * The one place emojis are drawn. Sized by font-size like text, so callers style
 * it the same either way. With `config.emoji.baseUrl` set it shows that image set
 * (same look on every device); a sequence like "🐱📦" becomes one image per emoji,
 * and any missing file falls back to the native emoji. An ARASAAC pictogram
 * ("arasaac:2462~🍎") is drawn as its picture, with the emoji as the fallback; so is a Mulberry symbol
 * ("mulberry:badger").
 */
const props = defineProps({
  char: { type: String, required: true },
  /** Accessible name. Without it the emoji is treated as decoration. */
  label: { type: String, default: '' },
})

const failed = reactive(new Set())
const picto = computed(() => pictogram(props.char))
const uploaded = computed(() => upload(props.char))
const mulberry = computed(() => symbol(props.char))
const symbolFailed = ref(false)
const pictoFailed = ref(false)
const uploadFailed = ref(false)
const shown = computed(() => {
  if (picto.value) return pictoFailed.value ? picto.value.fallback : ''
  if (mulberry.value) return symbolFailed.value ? mulberry.value.fallback || ' ' : ''
  if (uploaded.value) return uploadFailed.value ? ' ' : ''
  return props.char
})
watch(
  () => props.char,
  () => {
    failed.clear()
    pictoFailed.value = false
    symbolFailed.value = false
    uploadFailed.value = false
  },
)

const base = computed(() => (config.emoji.baseUrl ? config.emoji.baseUrl.replace(/\/?$/, '/') : null))
const parts = computed(() =>
  splitEmoji(shown.value).map(ch => ({
    ch,
    src: base.value && !failed.has(ch) ? `${base.value}${emojiAssetName(ch)}${config.emoji.ext}` : null,
  })),
)
const single = computed(() => (parts.value.length === 1 ? parts.value[0] : null))
const a11y = computed(() => (props.label ? { role: 'img', 'aria-label': props.label } : { 'aria-hidden': 'true' }))
</script>

<template>
  <img
    v-if="picto && !pictoFailed"
    class="emoji emoji--img emoji--picto"
    :src="`${config.pictograms.baseUrl.replace(/\/?$/, '/')}${picto.id}.png`"
    :alt="label"
    :aria-hidden="label ? undefined : 'true'"
    draggable="false"
    decoding="async"
    loading="eager"
    @error="pictoFailed = true"
  />
  <img
    v-else-if="mulberry && !symbolFailed"
    class="emoji emoji--img emoji--picto"
    :src="`${config.symbols.baseUrl.replace(/\/?$/, '/')}${mulberry.name}.svg`"
    :alt="label"
    :aria-hidden="label ? undefined : 'true'"
    draggable="false"
    decoding="async"
    loading="eager"
    @error="symbolFailed = true"
  />
  <img
    v-else-if="uploaded && !uploadFailed"
    class="emoji emoji--img"
    :src="`${config.contentImages.baseUrl.replace(/\/?$/, '/')}${uploaded.id}`"
    :alt="label"
    :aria-hidden="label ? undefined : 'true'"
    draggable="false"
    decoding="async"
    loading="eager"
    @error="uploadFailed = true"
  />
  <img
    v-else-if="single?.src"
    class="emoji emoji--img"
    :src="single.src"
    :alt="label"
    :aria-hidden="label ? undefined : 'true'"
    draggable="false"
    decoding="async"
    loading="eager"
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
        loading="eager"
        @error="failed.add(p.ch)"
      />
      <span v-else>{{ p.ch }}</span>
    </template>
  </span>
  <span v-else class="emoji" v-bind="a11y">{{ shown }}</span>
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
/* pictograms carry a thin margin of their own; a touch larger matches an emoji's weight */
.emoji--picto {
  width: 1.15em;
  height: 1.15em;
  vertical-align: -0.2em;
}
.emoji--group {
  display: inline-flex;
  align-items: center;
  gap: 0.04em;
}
</style>
