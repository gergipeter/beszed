#!/usr/bin/env node
/**
 * Generates pictures for lexicon words that still show an ARASAAC pictogram with no commercially-free
 * substitute (database/lexicon/substitutes.json), via the Gemini image generation API
 * (gemini-3.1-flash-image:generateContent), and saves them ready for scripts/ai-pics-import.mjs.
 *
 * Needs GEMINI_API_KEY in .env (or the environment). One request per word, with a short pause between
 * calls to stay well under rate limits; a failed word is reported and skipped, not retried forever.
 *
 * Usage:
 *   node scripts/ai-pics-generate.mjs [--limit N] [--out DIR] [--start N]
 *   --limit N   how many words to generate this run (default 20 — keep batches small to review style)
 *   --out DIR   where to save the PNGs (default ai-pics-drop/, matching ai-pics-import.mjs's own default)
 *   --start N   skip the first N words of the (frequency-sorted) worklist, to resume a later batch
 *
 * After it finishes, look over the images in the output folder, then run:
 *   node scripts/ai-pics-import.mjs ai-pics-drop
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('..', import.meta.url))

// Minimal .env reader: no extra dependency for one value.
function loadEnvKey(name) {
  if (process.env[name]) return process.env[name]
  const envPath = join(root, '.env')
  if (!existsSync(envPath)) return null
  const line = readFileSync(envPath, 'utf8')
    .split('\n')
    .find(l => l.startsWith(`${name}=`))
  return line ? line.slice(name.length + 1).trim() : null
}

const apiKey = loadEnvKey('GEMINI_API_KEY')
if (!apiKey) {
  console.error('GEMINI_API_KEY not found in .env or environment.')
  process.exit(1)
}

const args = process.argv.slice(2)
const flag = (name, def) => {
  const i = args.indexOf(name)
  return i === -1 ? def : args[i + 1]
}
const limit = Number(flag('--limit', 20))
const start = Number(flag('--start', 0))
const outDir = join(root, flag('--out', 'ai-pics-drop'))

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

const lexicon = JSON.parse(readFileSync(join(root, 'database/lexicon/hu.json'), 'utf8'))
const substitutes = JSON.parse(readFileSync(join(root, 'database/lexicon/substitutes.json'), 'utf8'))

// Kirakó's theme picker (config/beszed.php games.kirako.categories) filters the same lexicon by these
// lexicon "c" categories — not a separate word list, just a different slice of the one worklist. So
// --category lets a run target "finish every animal word" instead of going strictly by frequency.
const KIRAKO_THEMES = {
  animals: ['animal'],
  vehicles: ['vehicle'],
  food: ['fruit', 'vegetable', 'food'],
  toys: ['toy', 'instrument'],
  nature: ['nature', 'weather', 'flower'],
  home: ['house', 'kitchen', 'thing', 'tool', 'clothing', 'school', 'building'],
}
const category = flag('--category', null)
const categoryFilter = category && (KIRAKO_THEMES[category] ?? [category])
if (category && !categoryFilter) {
  console.error(`Unknown --category "${category}". Known: ${Object.keys(KIRAKO_THEMES).join(', ')} (or any lexicon "c" value).`)
  process.exit(1)
}

const missing = lexicon
  .filter(w => w.p && substitutes[String(w.p)] === undefined)
  .filter(w => !categoryFilter || categoryFilter.includes(w.c))
  .sort((a, b) => (a.f ?? 9) - (b.f ?? 9) || a.w.localeCompare(b.w, 'hu'))

const batch = missing.slice(start, start + limit)
if (!batch.length) {
  console.log('Nothing to generate (worklist empty or --start is past the end).')
  process.exit(0)
}

if (!existsSync(outDir)) mkdirSync(outDir, { recursive: true })

// One fixed style for every picture, so the whole set reads as one system in the games.
const promptFor = word =>
  `A simple flat vector icon of ${word}, centered, thick clean outline, soft pastel colors, ` +
  'plain white background, no text, no shadow, child-friendly illustration style, like a speech therapy flashcard.'

// Hungarian word → English gloss, hand-translated once (database/lexicon/en-gloss.json) instead of
// asking Gemini for it on every run: halves the API calls (one per word instead of two), since the
// image call is what actually costs money. Falls back to asking Gemini only for a word missing here.
const glossPath = join(root, 'database/lexicon/en-gloss.json')
const glossTable = existsSync(glossPath) ? JSON.parse(readFileSync(glossPath, 'utf8')) : {}

async function glossEnglish(word, category) {
  if (glossTable[word]) return glossTable[word]
  const res = await fetch(`https://generativelanguage.googleapis.com/v1/models/gemini-3.5-flash:generateContent`, {
    method: 'POST',
    headers: { 'x-goog-api-key': apiKey, 'Content-Type': 'application/json' },
    body: JSON.stringify({
      contents: [{ parts: [{ text: `One or two English words naming this Hungarian noun for a children's flashcard, category "${category}": "${word}". Reply with only the English word(s), nothing else.` }] }],
    }),
  })
  if (!res.ok) throw new Error(`gloss failed (${res.status}): ${await res.text()}`)
  const data = await res.json()
  const text = data.candidates?.[0]?.content?.parts?.find(p => p.text)?.text?.trim()
  if (!text) throw new Error('gloss: no text in response')
  return text.replace(/[."]/g, '')
}

async function generateImage(promptText) {
  const res = await fetch(`https://generativelanguage.googleapis.com/v1/models/gemini-3.1-flash-image:generateContent`, {
    method: 'POST',
    headers: { 'x-goog-api-key': apiKey, 'Content-Type': 'application/json' },
    body: JSON.stringify({
      contents: [{ parts: [{ text: promptText }] }],
      generationConfig: { responseModalities: ['TEXT', 'IMAGE'] },
    }),
  })
  if (!res.ok) throw new Error(`image failed (${res.status}): ${await res.text()}`)
  const data = await res.json()
  const part = data.candidates?.[0]?.content?.parts?.find(p => p.inlineData)
  if (!part) throw new Error('image: no inlineData in response')
  return Buffer.from(part.inlineData.data, 'base64')
}

const sleep = ms => new Promise(r => setTimeout(r, ms))

let ok = 0
const failed = []

for (const [i, w] of batch.entries()) {
  const slug = slugify(w.w)
  const dest = join(outDir, `${slug}.png`)
  process.stdout.write(`[${i + 1}/${batch.length}] ${w.w} (${w.c ?? '?'})... `)
  try {
    const english = await glossEnglish(w.w, w.c ?? '')
    await sleep(300)
    const png = await generateImage(promptFor(english))
    writeFileSync(dest, png)
    console.log(`ok (${english}) -> ${slug}.png`)
    ok++
  } catch (err) {
    console.log(`FAILED: ${err.message}`)
    failed.push(w.w)
  }
  await sleep(800) // stay comfortably under per-minute rate limits
}

console.log(`\nDone: ${ok}/${batch.length} saved to ${outDir}`)
if (failed.length) console.log(`Failed (${failed.length}): ${failed.join(', ')}`)
console.log(`\nLook over the images, then run: node scripts/ai-pics-import.mjs ${flag('--out', 'ai-pics-drop')}`)
