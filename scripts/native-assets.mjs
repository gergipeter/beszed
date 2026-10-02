// After `vite build --config vite.native.config.js`: puts into dist-native/ the files the app needs that Vite does not
// emit, so that the app works without the website's public/ folder:
//   emoji/    the Twemoji pictures in use (scripts/copy-emoji.mjs, the same set as the website)
//   symbols/  the Mulberry symbols (public/symbols, 1.9 MB), which the website serves from public/
// The relative URLs './emoji/' and './symbols/' are set in resources/js/app.js for the native build.
// Another output directory: `node scripts/native-assets.mjs <dir>`.
import { spawnSync } from 'node:child_process'
import { cpSync, existsSync, readdirSync, statSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const out = resolve(process.argv[2] || join(root, 'dist-native'))

if (!existsSync(join(out, 'index.html'))) {
  console.error(`native-assets: ${out}/index.html not found; run vite build --config vite.native.config.js first.`)
  process.exit(1)
}

const emoji = spawnSync(process.execPath, [join(root, 'scripts', 'copy-emoji.mjs'), join(out, 'emoji')], { stdio: 'inherit' })
if (emoji.status !== 0) process.exit(emoji.status ?? 1)

const symbols = join(root, 'public', 'symbols')
cpSync(symbols, join(out, 'symbols'), { recursive: true })
const size = readdirSync(symbols).reduce((sum, f) => sum + statSync(join(symbols, f)).size, 0)
console.log(`native-assets: ${readdirSync(symbols).length} symbols (${(size / 1024).toFixed(0)} kB) → ${join(out, 'symbols')}`)
