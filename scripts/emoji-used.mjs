// Which emoji the app uses: shared by copy-emoji.mjs (ships only those pictures) and check-emoji.mjs (fails on a
// missing one). Scans everything an emoji can come from: the game content (database/), the code that builds rounds
// (app/), the views, the front end and the config. Emoji that only appear in content edited at runtime have no
// picture unless listed in scripts/emoji-extra.txt; the app shows those as the device's own emoji.
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

export const root = join(dirname(fileURLToPath(import.meta.url)), '..')
export const twemojiDir = join(root, 'node_modules', '@twemoji', 'svg')
export const extraFile = join(root, 'scripts', 'emoji-extra.txt')

const SCANNED = ['app', 'config', 'database', 'resources/js', 'resources/lang', 'resources/views']
const SKIPPED = new Set(['node_modules', 'vendor', 'storage'])
const EXTENSIONS = /\.(json|js|mjs|vue|php|css)$/

const segmenter = new Intl.Segmenter(undefined, { granularity: 'grapheme' })
const isEmoji = /\p{Extended_Pictographic}/u

/** Twemoji file name (without .svg): code points in hex, the emoji-presentation selector dropped unless it is a ZWJ sequence. */
export const assetName = e =>
  [...(e.includes('\u200d') ? e : e.replace(/\ufe0f/g, ''))].map(c => c.codePointAt(0).toString(16)).join('-')

export const hasImage = e => existsSync(join(twemojiDir, `${assetName(e)}.svg`))

function* walk(dir) {
  if (!existsSync(dir)) return
  for (const name of readdirSync(dir)) {
    if (SKIPPED.has(name)) continue
    const path = join(dir, name)
    if (statSync(path).isDirectory()) yield* walk(path)
    else if (EXTENSIONS.test(name)) yield path
  }
}

/** Every emoji in `text`, one per grapheme. */
export const emojiIn = text => [...segmenter.segment(text)].map(s => s.segment).filter(s => isEmoji.test(s))

/** @returns {Map<string, string>} emoji → the first file it was found in (relative to the project) */
export function usedEmoji() {
  // A build context without these (a Docker stage that did not copy them) would quietly ship too few pictures.
  for (const dir of SCANNED) {
    if (dir !== 'resources/lang' && !existsSync(join(root, dir))) throw new Error(`emoji-used: ${dir}/ is missing, so the emoji in use cannot be listed`)
  }
  const used = new Map()
  const files = [...SCANNED.flatMap(dir => [...walk(join(root, dir))])]
  for (const file of files) {
    for (const e of emojiIn(readFileSync(file, 'utf8'))) if (!used.has(e)) used.set(e, file.slice(root.length + 1).replaceAll('\\', '/'))
  }
  return used
}

/** The emoji listed in scripts/emoji-extra.txt ('#' starts a comment; any number of emoji per line). */
export function extraEmoji() {
  if (!existsSync(extraFile)) return []
  const lines = readFileSync(extraFile, 'utf8').split(/\r?\n/).map(line => line.replace(/#.*/, ''))
  return lines.flatMap(emojiIn)
}
