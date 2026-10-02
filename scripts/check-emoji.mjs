// Lists every emoji used by the content and the code, and fails if one has no Twemoji image
// (it would still show, but as the device's own emoji). Run: npm run check:emoji
import { assetName, extraEmoji, hasImage, usedEmoji } from './emoji-used.mjs'

const used = usedEmoji()
for (const e of extraEmoji()) if (!used.has(e)) used.set(e, 'scripts/emoji-extra.txt')

const missing = [...used].filter(([e]) => !hasImage(e))
console.log(`${used.size} different emoji in use; ${missing.length} without a Twemoji image.`)
for (const [e, file] of missing) console.log(`  ${e}  ${assetName(e)}.svg  (${file})`)
process.exit(missing.length ? 1 : 0)
