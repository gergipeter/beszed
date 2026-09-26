// Lists every emoji used by the content and the code, and fails if one has no Twemoji image
// (it would still show, but as the device's own emoji). Run: npm run check:emoji
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const set = join(root, 'node_modules', '@twemoji', 'svg')

const files = []
const walk = dir => {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) walk(path)
    else if (/\.(json|js|vue|php)$/.test(name)) files.push(path)
  }
}
walk(join(root, 'database', 'seeders', 'data', 'beszed'))
walk(join(root, 'resources', 'js'))
files.push(join(root, 'config', 'beszed.php'))

const segmenter = new Intl.Segmenter(undefined, { granularity: 'grapheme' })
const isEmoji = /\p{Extended_Pictographic}/u
const assetName = e =>
  [...(e.includes('‍') ? e : e.replace(/️/g, ''))].map(c => c.codePointAt(0).toString(16)).join('-')

const used = new Map()
for (const file of files) {
  for (const { segment } of segmenter.segment(readFileSync(file, 'utf8'))) {
    if (isEmoji.test(segment) && !used.has(segment)) used.set(segment, file.slice(root.length + 1))
  }
}

const missing = [...used].filter(([e]) => !existsSync(join(set, `${assetName(e)}.svg`)))
console.log(`${used.size} different emoji in use; ${missing.length} without a Twemoji image.`)
for (const [e, file] of missing) console.log(`  ${e}  ${assetName(e)}.svg  (${file})`)
process.exit(missing.length ? 1 : 0)
