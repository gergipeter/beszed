/**
 * Adds ARASAAC pictograms to the word bank (database/lexicon/hu.json).
 *
 *   node scripts/arasaac-import.mjs [--dry-run]
 *
 * 1. Every hand-written word gets the pictogram ("p") whose Hungarian keyword is
 *    the word and whose tags fit the word's category (so "levél" gets a leaf,
 *    not a letter). No good match: the word keeps only its emoji.
 * 2. The words in database/lexicon/arasaac-words.json (a hand-picked list for
 *    small children; frequency lists and tags can't judge that) are added with
 *    their pictogram as "src": "arasaac" entries, replaced on every run.
 * A few automatic choices were wrong when checked by eye; PICK fixes them.
 *
 * Pictograms: Sergio Palao, ARASAAC (https://arasaac.org), Government of Aragón,
 * CC BY-NC-SA 4.0. The word-frequency list (hermitdave/FrequencyWords) only
 * sets how familiar a word is; it isn't shipped.
 */
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const LEXICON = new URL('../database/lexicon/hu.json', import.meta.url)
const EXTRA = new URL('../database/lexicon/arasaac-words.json', import.meta.url)
const CACHE = join(tmpdir(), 'beszed-arasaac')
const DRY = process.argv.includes('--dry-run')

const SOURCES = {
  catalog: 'https://api.arasaac.org/v1/pictograms/all/hu',
  english: 'https://api.arasaac.org/v1/pictograms/all/en',
  frequency: 'https://raw.githubusercontent.com/hermitdave/FrequencyWords/master/content/2018/hu/hu_50k.txt',
}

/** Tags that show a pictogram fits a word bank category. */
const FITS = {
  animal: ['animal'],
  fruit: ['fruit', 'dried fruit'],
  vegetable: ['vegetable', 'plant-based food'],
  food: ['food', 'feeding', 'gastronomy', 'dessert', 'sweets', 'dairy product', 'plant-based food', 'beverage'],
  vehicle: ['mode of transport', 'land transport', 'air transport', 'water transport', 'vehicle', 'work machine', 'space'],
  clothing: ['clothes', 'footwear', 'accessories', 'fashion'],
  toy: ['toy', 'game', 'traditional game', 'sport material', 'leisure', 'outdoor activity', 'play'],
  instrument: ['musical instrument'],
  tool: ['tool', 'utensil', 'material', 'outdoor activity'],
  kitchen: ['utensil', 'container', 'household', 'appliance'],
  house: ['furniture', 'home', 'household', 'room', 'appliance', 'building facility', 'building', 'object', 'computing', 'mass media'],
  body: ['human body'],
  building: ['building', 'place', 'facility', 'educational space', 'leisure', 'infrastructure'],
  weather: ['meteorology', 'atmospheric phenomena'],
  nature: ['plant', 'tree', 'astronomy', 'geography', 'physical geography', 'sea', 'place', 'landscape', 'meteorology', 'beach', 'material', 'outdoor activity', 'agriculture', 'dried fruit'],
  flower: ['flower'],
  school: ['educational material', 'education', 'literature', 'language', 'document', 'geography'],
  thing: ['object', 'household', 'home', 'appliance', 'accessories', 'music device', 'container', 'sport material', 'material', 'money', 'communication'],
  person: ['person', 'professional', 'character', 'book character', 'family', 'kinship', 'people'],
}

/**
 * Checked by eye: the automatic choice was wrong (a polar bear for "medve", a
 * party hat for "sisak"). A number picks that pictogram, null keeps the emoji only.
 */
const PICK = {
  medve: null, jegesmedve: 2868, kukorica: null, bab: 3294, sisak: 2691, tészta: 8652, tintahal: 5418,
  uzsonna: null, sár: null, edény: null, gyufa: null,
}

async function cached(name, url) {
  mkdirSync(CACHE, { recursive: true })
  const file = join(CACHE, name)
  if (!existsSync(file)) {
    const res = await fetch(url)
    if (!res.ok) throw new Error(`${url}: HTTP ${res.status}`)
    writeFileSync(file, Buffer.from(await res.arrayBuffer()))
  }
  return readFileSync(file, 'utf8')
}

const catalog = JSON.parse(await cached('catalog-hu.json', SOURCES.catalog))
// Some everyday words have no Hungarian keyword in ARASAAC yet; the word list can
// name the English one instead ("óvoda=kindergarten").
const english = JSON.parse(await cached('catalog-en.json', SOURCES.english))
const rank = new Map(
  (await cached('hu_50k.txt', SOURCES.frequency))
    .split('\n')
    .map((line, i) => [line.split(' ')[0], i + 1]),
)

/** Fine as a picture for a child: not a diagram, nothing adult or violent. */
const usable = p => !p.schematic && !p.sex && !p.violence
const core = p => (p.tags ?? []).includes('core vocabulary')
const fit = (p, category) => (FITS[category] ?? []).filter(t => (p.tags ?? []).includes(t)).length

/** keyword → pictograms carrying it, in one language's catalog */
function index(pictograms) {
  const map = new Map()
  for (const p of pictograms.filter(usable)) {
    for (const k of p.keywords ?? []) {
      const word = k.keyword.trim().toLowerCase()
      if (!map.has(word)) map.set(word, [])
      map.get(word).push(p)
    }
  }
  return map
}
const byWord = index(catalog)
const byEnglish = index(english)

/**
 * The best free pictogram for a word in a category: fits the category, core
 * vocabulary, oldest (most canonical). A pictogram without any tags is taken
 * only when it is the word's one and only pictogram.
 */
function best(word, category, used, from = byWord) {
  const all = (from.get(word.toLowerCase()) ?? []).filter(p => !used.has(p._id))
  const candidates = all.filter(p => fit(p, category) > 0)
  candidates.sort((a, b) => fit(b, category) - fit(a, category) || core(b) - core(a) || a._id - b._id)
  return candidates[0] ?? (all.length === 1 && !(all[0].tags ?? []).length ? all[0] : null)
}

// 1. Hand-written words: add the pictogram.
const lexicon = JSON.parse(readFileSync(LEXICON, 'utf8')).filter(w => w.src !== 'arasaac')
const used = new Set(Object.values(PICK).filter(Boolean))
let matched = 0
for (const w of lexicon) {
  delete w.p
  const p = w.w in PICK ? (PICK[w.w] ? { _id: PICK[w.w] } : null) : best(w.w, w.c, used)
  if (p) {
    w.p = p._id
    used.add(p._id)
    matched++
  }
}

// 2. The hand-picked extra words.
const extra = JSON.parse(readFileSync(EXTRA, 'utf8'))
const known = new Set(lexicon.map(w => w.w.toLowerCase()))
const added = []
const missing = []
for (const [category, words] of Object.entries(extra)) {
  if (category === '_') continue
  for (const item of words) {
    const [word, en] = item.split('=')
    if (known.has(word)) continue
    known.add(word)
    const p = word in PICK ? (PICK[word] ? { _id: PICK[word] } : null) : (best(word, category, used) ?? (en ? best(en, category, used, byEnglish) : null))
    if (!p) {
      missing.push(word)
      continue
    }
    used.add(p._id)
    const f = (rank.get(word) ?? Infinity) <= 6000 || core(p) ? 1 : 2
    added.push({ w: word, p: p._id, c: category, f, src: 'arasaac' })
  }
}

const order = ['w', 'e', 'p', 'c', 'sub', 'f', 'acc', 'src']
const line = w => '  ' + JSON.stringify(Object.fromEntries(order.filter(k => k in w).map(k => [k, w[k]]))).replace(/","/g, '", "').replace(/":/g, '": ').replace(/,"/g, ', "')
const text = '[\n' + [...lexicon, ...added].map(line).join(',\n') + '\n]\n'

console.log(`pictograms for hand-written words: ${matched} / ${lexicon.length}`)
console.log(`extra words added: ${added.length}; no fitting pictogram: ${missing.length} (${missing.join(', ')})`)
if (!DRY) {
  writeFileSync(LEXICON, text)
  console.log('written', LEXICON.pathname)
}
