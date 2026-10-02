// Copies the Twemoji SVGs the app uses (scripts/emoji-used.mjs, plus scripts/emoji-extra.txt) into the Vite output
// (public/build/emoji/), after `vite build`: a few hundred files instead of the whole 3,720-file, 16 MB set.
// Another output directory: `node scripts/copy-emoji.mjs <dir>` or EMOJI_OUT=<dir>.
// Graphics: Twemoji by Twitter, Inc. and contributors, CC-BY 4.0 (attribution on /adatvedelem).
import { cpSync, existsSync, mkdirSync, readdirSync, rmSync, statSync } from 'node:fs'
import { join, resolve } from 'node:path'
import { assetName, extraEmoji, hasImage, root, twemojiDir, usedEmoji } from './emoji-used.mjs'

const to = resolve(process.argv[2] || process.env.EMOJI_OUT || join(root, 'public', 'build', 'emoji'))

if (!existsSync(twemojiDir)) {
  console.warn('copy-emoji: @twemoji/svg is not installed; the app will use native emoji.')
  process.exit(0)
}

const wanted = new Set()
const unknown = []
for (const e of [...usedEmoji().keys(), ...extraEmoji()]) (hasImage(e) ? wanted.add(`${assetName(e)}.svg`) : unknown.push(e))

mkdirSync(to, { recursive: true })
// An earlier run (or the full set) may be there: keep only what is wanted.
for (const f of readdirSync(to)) if (f.endsWith('.svg') && !wanted.has(f)) rmSync(join(to, f))
let bytes = 0
for (const f of wanted) {
  cpSync(join(twemojiDir, f), join(to, f))
  bytes += statSync(join(to, f)).size
}
console.log(`copy-emoji: ${wanted.size} SVGs (${(bytes / 1024).toFixed(0)} kB) → ${to}`)
if (unknown.length) console.log(`copy-emoji: no Twemoji image for ${unknown.join(' ')} (shown as the device's own emoji)`)
