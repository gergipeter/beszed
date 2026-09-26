const ZWJ = '‍'
const VS16 = /️/g

/**
 * File name of an emoji in Twemoji-style image sets: code points in hex joined
 * by "-", with the emoji-presentation selector dropped unless it is a ZWJ sequence.
 * "🐝" → "1f41d", "🗑️" → "1f5d1", "👩‍🍳" → "1f469-200d-1f373".
 */
export function emojiAssetName(emoji) {
  const text = emoji.includes(ZWJ) ? emoji : emoji.replace(VS16, '')
  return [...text].map(char => char.codePointAt(0).toString(16)).join('-')
}

/**
 * An ARASAAC pictogram reference: "arasaac:2462", or "arasaac:2462~🍎" with the
 * emoji to show if the picture can't load. Null for anything else.
 * @returns {{ id: string, fallback: string } | null}
 */
export function pictogram(text) {
  const m = /^arasaac:(\d+)(?:~(.+))?$/.exec(text ?? '')
  return m ? { id: m[1], fallback: m[2] ?? '' } : null
}

/**
 * An uploaded image reference: "upload:123". Null for anything else.
 * @returns {{ id: string } | null}
 */
export function upload(text) {
  const m = /^upload:(\d+)$/.exec(text ?? '')
  return m ? { id: m[1] } : null
}

const segmenter = typeof Intl !== 'undefined' && Intl.Segmenter ? new Intl.Segmenter(undefined, { granularity: 'grapheme' }) : null

/** "🐱📦" → ["🐱", "📦"] (whole emoji, including ZWJ sequences and skin tones); spaces dropped. */
export function splitEmoji(text) {
  if (!segmenter) return [text]
  return [...segmenter.segment(text)].map(s => s.segment).filter(s => s.trim())
}
