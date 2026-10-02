// Ties the service worker's caches to the build, after `vite build`: writes public/build/sw-version.js,
//   self.BESZED_BUILD = '<hash of manifest.json>'
// which public/sw.js imports. Browsers byte-compare imported scripts when they look for a new worker, so every
// build that changes the manifest (any new fingerprinted file) installs a fresh worker and precaches the new build.
// The build directory can be given as an argument or in BUILD_DIR (default public/build).
import { createHash } from 'node:crypto'
import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const dir = resolve(process.argv[2] || process.env.BUILD_DIR || join(root, 'public', 'build'))
const manifest = join(dir, 'manifest.json')

if (!existsSync(manifest)) {
  console.error(`stamp-sw: ${manifest} not found; run vite build first.`)
  process.exit(1)
}

const build = createHash('sha256').update(readFileSync(manifest)).digest('hex').slice(0, 12)
writeFileSync(join(dir, 'sw-version.js'), `// Written by scripts/stamp-sw.mjs; public/sw.js names its caches after this.\nself.BESZED_BUILD = '${build}'\n`)
console.log(`stamp-sw: build ${build} → ${join(dir, 'sw-version.js')}`)
