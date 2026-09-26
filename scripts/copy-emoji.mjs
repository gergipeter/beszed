// Copies the Twemoji SVG set into the Vite output (public/build/emoji/), after `vite build`.
// Graphics: Twemoji by Twitter, Inc. and contributors, CC-BY 4.0 (attribution on /adatvedelem).
import { cpSync, existsSync, mkdirSync, readdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const from = join(root, 'node_modules', '@twemoji', 'svg')
const to = join(root, 'public', 'build', 'emoji')

if (!existsSync(from)) {
  console.warn('copy-emoji: @twemoji/svg is not installed; the app will use native emoji.')
  process.exit(0)
}

mkdirSync(to, { recursive: true })
const files = readdirSync(from).filter(f => f.endsWith('.svg'))
for (const f of files) cpSync(join(from, f), join(to, f))
console.log(`copy-emoji: ${files.length} SVGs → public/build/emoji/`)
