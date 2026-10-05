#!/usr/bin/env node
/**
 * Lists lexicon words that still show an ARASAAC pictogram with no commercially-free substitute
 * (database/lexicon/substitutes.json has nothing for that word's "p" id) — the worklist for
 * scripts/ai-pics-import.mjs. Printed most-common words first (lexicon "f": 1 = most frequent),
 * since those are what children actually hit first in the games.
 *
 * Usage: node scripts/ai-pics-missing.mjs [--csv]
 *   (no flag) a readable table to the terminal
 *   --csv     the same list as CSV, for a spreadsheet
 */
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('..', import.meta.url))
const lexicon = JSON.parse(readFileSync(join(root, 'database/lexicon/hu.json'), 'utf8'))
const substitutes = JSON.parse(readFileSync(join(root, 'database/lexicon/substitutes.json'), 'utf8'))

const missing = lexicon
  .filter(w => w.p && substitutes[String(w.p)] === undefined)
  .sort((a, b) => (a.f ?? 9) - (b.f ?? 9) || a.w.localeCompare(b.w, 'hu'))

const asCsv = process.argv.includes('--csv')

if (asCsv) {
  console.log('freq,word,emoji,arasaac_id,category,accusative')
  for (const w of missing) console.log([w.f ?? '', w.w, w.e ?? '', w.p, w.c ?? '', w.acc ?? ''].join(','))
} else {
  console.log(`${missing.length} words still need a picture (of ${lexicon.filter(w => w.p).length} ARASAAC-backed words).\n`)
  const byFreq = {}
  for (const w of missing) byFreq[w.f ?? 9] = (byFreq[w.f ?? 9] ?? 0) + 1
  console.log('By priority (lexicon frequency rank, 1 = most common):')
  for (const [f, n] of Object.entries(byFreq).sort()) console.log(`  f=${f}: ${n} words`);
  console.log('\nFirst 30, most common first (word → save your image as ai-pics-drop/<word>.png):')
  for (const w of missing.slice(0, 30)) console.log(`  ${w.w}  (${w.c ?? '?'})`)
  console.log('\nRun with --csv > worklist.csv for the full list.')
}
