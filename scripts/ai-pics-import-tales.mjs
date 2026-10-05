#!/usr/bin/env node
/**
 * Applies generated tale scenes (scripts/ai-pics-generate-tales.mjs) to
 * database/seeders/data/beszed/kirako.json: each tale's "emoji" field (its puzzle picture) becomes
 * "ai:<slug>" pointing at public/ai-pics/<slug>.png, replacing the shared generic character emoji
 * every tale used before. The tale's own "prop" field is then dropped — it's already drawn into the
 * scene, so PuzzleArt.vue would otherwise overlay a second, now-redundant icon on top.
 *
 * Usage: node scripts/ai-pics-import-tales.mjs <drop-folder>
 *
 * After running: re-seed so it reaches the database —
 *   docker cp database/seeders/data/beszed/kirako.json beszed-app-1:/app/database/seeders/data/beszed/kirako.json
 *   docker exec beszed-app-1 php artisan db:seed --class=BeszedContentSeeder --force
 */
import { readFileSync, writeFileSync, readdirSync, copyFileSync, existsSync, mkdirSync } from 'node:fs'
import { join, extname, basename } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('..', import.meta.url))
const kirakoPath = join(root, 'database/seeders/data/beszed/kirako.json')
const aiPicsDir = join(root, 'public/ai-pics')

const dropFolder = process.argv[2]
if (!dropFolder) {
  console.error('Usage: node scripts/ai-pics-import-tales.mjs <drop-folder>')
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

const kirako = JSON.parse(readFileSync(kirakoPath, 'utf8'))
const tales = kirako.filter(it => it.payload.kind === 'tale')
const bySlug = new Map(tales.map(t => [slugify(t.payload.name), t]))

// Slug collisions among tale names (two tales whose names fold to the same filename) would silently
// misassign a picture, same risk as ai-pics-import.mjs — check before touching anything.
const counts = new Map()
for (const t of tales) {
  const s = slugify(t.payload.name)
  counts.set(s, (counts.get(s) ?? 0) + 1)
}
const collisions = [...counts.entries()].filter(([, n]) => n > 1).map(([s]) => s)
if (collisions.length) {
  console.log('These tale names share a filename once accents are stripped — resolve by hand:')
  collisions.forEach(s => console.log(`  ${s}.png`))
}

if (!existsSync(aiPicsDir)) mkdirSync(aiPicsDir, { recursive: true })

const files = readdirSync(dropFolder).filter(f => ['.png', '.jpg', '.jpeg', '.webp'].includes(extname(f).toLowerCase()))
if (!files.length) {
  console.log(`No image files found in ${dropFolder}`)
  process.exit(0)
}

let imported = 0
const unmatched = []

for (const file of files) {
  const slug = slugify(basename(file, extname(file)))
  if (collisions.includes(slug)) continue
  const tale = bySlug.get(slug)
  if (!tale) {
    unmatched.push(file)
    continue
  }
  copyFileSync(join(dropFolder, file), join(aiPicsDir, `${slug}.png`))
  tale.payload.emoji = `ai:${slug}`
  delete tale.payload.prop // already drawn into the scene; keeping it would overlay a second icon
  imported++
}

// one compact line per entry, matching the file's existing style
const body = kirako.map(row => JSON.stringify(row)).join(',\n  ')
writeFileSync(kirakoPath, `[\n  ${body}\n]\n`, 'utf8')

console.log(`Imported ${imported} tale picture(s).`)
if (unmatched.length) {
  console.log(`\n${unmatched.length} file(s) did not match any tale name:`)
  unmatched.forEach(f => console.log(`  ${f}`))
}
console.log('\nNow re-seed the database:')
console.log('  docker cp database/seeders/data/beszed/kirako.json beszed-app-1:/app/database/seeders/data/beszed/kirako.json')
console.log('  docker exec beszed-app-1 php artisan db:seed --class=BeszedContentSeeder --force')
