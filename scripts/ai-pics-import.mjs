#!/usr/bin/env node
/**
 * Bulk-imports AI-generated pictures to replace ARASAAC pictograms that have
 * no Mulberry substitute yet (database/lexicon/substitutes.json).
 *
 * Usage:
 *   node scripts/ai-pics-import.mjs <drop-folder> [--no-cutout]
 *
 * Drop folder: one PNG/JPG per word, named after the lexicon word exactly as
 * it appears in database/lexicon/hu.json ("alma.png" for the word "alma").
 * Run `node scripts/ai-pics-missing.mjs` first for the list of words still
 * needed (and their priority), generate images for them with any AI tool
 * (plain white background — see the *-prompts.txt files), save them there,
 * then run this script.
 *
 * By default every image is passed through a background cutout (sharp):
 * near-white pixels reachable from the four corners become transparent, so
 * the generator only has to produce a plain white background reliably — not
 * real PNG alpha, which most image models don't actually give you even when
 * asked. Pass --no-cutout to copy files through unchanged (e.g. scenes that
 * are meant to keep a background, like the fairy tale illustrations).
 *
 * For each matched file: copies the (optionally cut out) image into
 * public/ai-pics/<slug>.png (slug = the word, ASCII-folded and safe for a
 * URL/filename) and adds or updates database/lexicon/substitutes.json so
 * every game using that word's ARASAAC pictogram shows this picture instead
 * whenever BESZED_PICTOGRAMS=false. Unmatched files and words are reported,
 * not guessed at.
 */
import { readFileSync, writeFileSync, readdirSync, existsSync, mkdirSync } from 'node:fs'
import { join, extname, basename } from 'node:path'
import { fileURLToPath } from 'node:url'
import sharp from 'sharp'

const root = fileURLToPath(new URL('..', import.meta.url))
const lexiconPath = join(root, 'database/lexicon/hu.json')
const substitutesPath = join(root, 'database/lexicon/substitutes.json')
const aiPicsDir = join(root, 'public/ai-pics')

const args = process.argv.slice(2)
const dropFolder = args.find(a => !a.startsWith('--'))
const doCutout = !args.includes('--no-cutout')
if (!dropFolder) {
  console.error('Usage: node scripts/ai-pics-import.mjs <drop-folder> [--no-cutout]')
  process.exit(1)
}

/**
 * Flood-fills transparency in from the four corners wherever the pixel is close enough to white,
 * stopping at the subject's outline. A corner that isn't background (the subject touches an edge)
 * just leaves that corner opaque — rare for a centered sticker-style subject, and harmless either way.
 */
async function cutoutWhiteBackground(filePath) {
  const img = sharp(filePath).ensureAlpha()
  const { data, info } = await img.raw().toBuffer({ resolveWithObject: true })
  const { width, height, channels } = info
  const THRESHOLD = 18 // how close to pure white still counts as background
  const isBg = i => {
    const r = data[i], g = data[i + 1], b = data[i + 2]
    return 255 - r < THRESHOLD && 255 - g < THRESHOLD && 255 - b < THRESHOLD
  }
  const visited = new Uint8Array(width * height)
  const stack = []
  const seed = (x, y) => {
    if (x < 0 || y < 0 || x >= width || y >= height) return
    const p = y * width + x
    if (visited[p]) return
    visited[p] = 1
    const i = p * channels
    if (isBg(i)) stack.push(p)
  }
  for (let x = 0; x < width; x++) {
    seed(x, 0)
    seed(x, height - 1)
  }
  for (let y = 0; y < height; y++) {
    seed(0, y)
    seed(width - 1, y)
  }
  while (stack.length) {
    const p = stack.pop()
    const x = p % width
    const y = (p / width) | 0
    data[p * channels + 3] = 0 // transparent
    const neighbors = [
      [x - 1, y],
      [x + 1, y],
      [x, y - 1],
      [x, y + 1],
    ]
    for (const [nx, ny] of neighbors) {
      if (nx < 0 || ny < 0 || nx >= width || ny >= height) continue
      const np = ny * width + nx
      if (visited[np]) continue
      visited[np] = 1
      const ni = np * channels
      if (isBg(ni)) stack.push(np)
    }
  }
  return sharp(data, { raw: { width, height, channels } }).png().toBuffer()
}

const slugify = word =>
  word
    .toLowerCase()
    .replace(/[áà]/g, 'a')
    .replace(/[éè]/g, 'e')
    .replace(/[íì]/g, 'i')
    .replace(/[óòöő]/g, 'o')
    .replace(/[úùüű]/g, 'u')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')

const lexicon = JSON.parse(readFileSync(lexiconPath, 'utf8'))
const substitutes = JSON.parse(readFileSync(substitutesPath, 'utf8'))

// Slug collisions (two words that fold to the same filename, e.g. "ágy"/"agy") would otherwise let
// the second one silently steal the first one's file. Caught here instead of trusting a Map overwrite.
const withPic = lexicon.filter(w => w.p)
const bySlugAll = new Map()
for (const w of withPic) {
  const s = slugify(w.w)
  if (!bySlugAll.has(s)) bySlugAll.set(s, [])
  bySlugAll.get(s).push(w)
}
const collisions = [...bySlugAll.entries()].filter(([, words]) => words.length > 1)
if (collisions.length) {
  console.log('These words share a filename once accents are stripped — resolve by hand, nothing imported for them:')
  for (const [slug, words] of collisions) console.log(`  ${slug}.png -> ${words.map(w => w.w).join(' / ')}`)
}
const bySlug = new Map([...bySlugAll.entries()].filter(([, words]) => words.length === 1).map(([slug, words]) => [slug, words[0]]))

if (!existsSync(aiPicsDir)) mkdirSync(aiPicsDir, { recursive: true })

const files = readdirSync(dropFolder).filter(f => ['.png', '.jpg', '.jpeg', '.webp'].includes(extname(f).toLowerCase()))
if (!files.length) {
  console.log(`No image files found in ${dropFolder}`)
  process.exit(0)
}

let imported = 0
let overwritten = 0
const unmatched = []

for (const file of files) {
  const stem = basename(file, extname(file))
  const slug = slugify(stem)
  const word = bySlug.get(slug)
  if (!word) {
    unmatched.push(file)
    continue
  }
  const dest = join(aiPicsDir, `${slug}.png`)
  const src = join(dropFolder, file)
  if (doCutout) {
    const png = await cutoutWhiteBackground(src)
    writeFileSync(dest, png)
  } else {
    writeFileSync(dest, readFileSync(src))
  }
  const already = substitutes[String(word.p)] !== undefined
  substitutes[String(word.p)] = `ai:${slug}`
  imported++
  if (already) overwritten++
}

// keep the file sorted by key, like it already is, so diffs stay small
const sorted = Object.fromEntries(Object.entries(substitutes).sort((a, b) => Number(a[0]) - Number(b[0])))
writeFileSync(substitutesPath, JSON.stringify(sorted, null, 1) + '\n', 'utf8')

console.log(`Imported ${imported} picture(s) (${overwritten} replaced an existing substitute)${doCutout ? ', background cut out' : ''}.`)
if (unmatched.length) {
  console.log(`\n${unmatched.length} file(s) did not match any lexicon word (check spelling / run ai-pics-missing.mjs):`)
  unmatched.forEach(f => console.log(`  ${f}`))
}
