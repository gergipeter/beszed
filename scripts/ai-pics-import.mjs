#!/usr/bin/env node
/**
 * Bulk-imports AI-generated pictures to replace ARASAAC pictograms that have
 * no Mulberry substitute yet (database/lexicon/substitutes.json).
 *
 * Usage:
 *   node scripts/ai-pics-import.mjs <drop-folder>
 *
 * Drop folder: one PNG per word, named after the lexicon word exactly as it
 * appears in database/lexicon/hu.json ("alma.png" for the word "alma"). Run
 * `node scripts/ai-pics-missing.mjs` first for the list of words still needed
 * (and their priority), generate images for them with any AI tool, save them
 * there, then run this script.
 *
 * For each matched file: copies it into public/ai-pics/<slug>.png (slug =
 * the word, ASCII-folded and safe for a URL/filename) and adds or updates
 * database/lexicon/substitutes.json so every game using that word's ARASAAC
 * pictogram shows this picture instead whenever BESZED_PICTOGRAMS=false.
 * Unmatched files and words are reported, not guessed at.
 */
import { readFileSync, writeFileSync, readdirSync, copyFileSync, existsSync, mkdirSync } from 'node:fs'
import { join, extname, basename } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('..', import.meta.url))
const lexiconPath = join(root, 'database/lexicon/hu.json')
const substitutesPath = join(root, 'database/lexicon/substitutes.json')
const aiPicsDir = join(root, 'public/ai-pics')

const dropFolder = process.argv[2]
if (!dropFolder) {
  console.error('Usage: node scripts/ai-pics-import.mjs <drop-folder>')
  process.exit(1)
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
  copyFileSync(join(dropFolder, file), dest)
  const already = substitutes[String(word.p)] !== undefined
  substitutes[String(word.p)] = `ai:${slug}`
  imported++
  if (already) overwritten++
}

// keep the file sorted by key, like it already is, so diffs stay small
const sorted = Object.fromEntries(Object.entries(substitutes).sort((a, b) => Number(a[0]) - Number(b[0])))
writeFileSync(substitutesPath, JSON.stringify(sorted, null, 1) + '\n', 'utf8')

console.log(`Imported ${imported} picture(s) (${overwritten} replaced an existing substitute).`)
if (unmatched.length) {
  console.log(`\n${unmatched.length} file(s) did not match any lexicon word (check spelling / run ai-pics-missing.mjs):`)
  unmatched.forEach(f => console.log(`  ${f}`))
}
