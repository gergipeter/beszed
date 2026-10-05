#!/usr/bin/env node
/**
 * Generates one AI-illustrated scene per Kirakó fairy tale (database/seeders/data/beszed/kirako.json,
 * kind: "tale"), replacing the single shared 👸/🤴/… emoji every tale currently uses as its puzzle
 * picture — so each tale's puzzle actually looks like that tale, not an identical generic figure.
 *
 * Unlike scripts/ai-pics-generate.mjs (one object per lexicon word), this draws the character with
 * their defining prop in their scene, using the same style terms so the whole app still reads as one
 * picture set. Descriptions come from database/lexicon/tale-gloss.json (hand-written once, like
 * en-gloss.json), so this never calls the API just to ask what a tale is about — only for the image.
 *
 * Usage:
 *   node scripts/ai-pics-generate-tales.mjs [--limit N] [--start N] [--out DIR]
 *
 * After it finishes: look over the images, then run
 *   node scripts/ai-pics-import-tales.mjs ai-pics-tales-drop
 * (not ai-pics-import.mjs — tales patch kirako.json's own "emoji" field directly, not substitutes.json).
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('..', import.meta.url))

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
const outDir = join(root, flag('--out', 'ai-pics-tales-drop'))

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

const kirakoPath = join(root, 'database/seeders/data/beszed/kirako.json')
const kirako = JSON.parse(readFileSync(kirakoPath, 'utf8'))
const glossPath = join(root, 'database/lexicon/tale-gloss.json')
const glossTable = JSON.parse(readFileSync(glossPath, 'utf8'))

const tales = kirako.filter(it => it.payload.kind === 'tale')
// Only ones whose emoji is still the plain shared character glyph, not already "ai:<slug>".
const pending = tales.filter(it => !it.payload.emoji.startsWith('ai:'))

const batch = pending.slice(start, start + limit)
if (!batch.length) {
  console.log('Nothing to generate (every tale already has its own picture, or --start is past the end).')
  process.exit(0)
}
if (!existsSync(outDir)) mkdirSync(outDir, { recursive: true })

// Same style terms as scripts/ai-pics-generate.mjs, but "illustration ... with" instead of "icon of"
// since this draws a small scene (character + prop), not one centered object.
const promptFor = description =>
  `A simple flat vector illustration of ${description}, centered, thick clean outline, soft pastel colors, ` +
  'plain white background, no text, no shadow, child-friendly illustration style, like a speech therapy flashcard.'

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
const missingGloss = []

for (const [i, tale] of batch.entries()) {
  const name = tale.payload.name
  const slug = slugify(name)
  const description = glossTable[name]
  process.stdout.write(`[${i + 1}/${batch.length}] ${name}... `)
  if (!description) {
    console.log('SKIPPED (no entry in tale-gloss.json)')
    missingGloss.push(name)
    continue
  }
  const dest = join(outDir, `${slug}.png`)
  try {
    const png = await generateImage(promptFor(description))
    writeFileSync(dest, png)
    console.log(`ok -> ${slug}.png`)
    ok++
  } catch (err) {
    console.log(`FAILED: ${err.message}`)
    failed.push(name)
  }
  await sleep(800)
}

console.log(`\nDone: ${ok}/${batch.length} saved to ${outDir}`)
if (failed.length) console.log(`Failed (${failed.length}): ${failed.join(', ')}`)
if (missingGloss.length) console.log(`No gloss entry (${missingGloss.length}): ${missingGloss.join(', ')}`)
console.log(`\nLook over the images, then run: node scripts/ai-pics-import-tales.mjs ${flag('--out', 'ai-pics-tales-drop')}`)
