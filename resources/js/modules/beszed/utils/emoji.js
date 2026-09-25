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
