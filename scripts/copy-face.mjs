// Copies the files Szájtorna's face tracker loads at run time into the Vite output (public/build/face/), after
// `vite build`: MediaPipe's WebAssembly (the SIMD build and the one for phones without SIMD, ~22 MB) from
// @mediapipe/tasks-vision, and the Face Landmarker model (resources/models/face_landmarker.task, 3.7 MB,
// from storage.googleapis.com/mediapipe-models). They download only when a child turns the mirror on.
// MediaPipe and the model: Google, Apache-2.0 (credited on /adatvedelem).
// Another output directory: `node scripts/copy-face.mjs <dir>` or FACE_OUT=<dir>.
import { cpSync, existsSync, mkdirSync, statSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const to = resolve(process.argv[2] || process.env.FACE_OUT || join(root, 'public', 'build', 'face'))
const wasm = join(root, 'node_modules', '@mediapipe', 'tasks-vision', 'wasm')
const model = join(root, 'resources', 'models', 'face_landmarker.task')

if (!existsSync(wasm) || !existsSync(model)) {
  console.warn('copy-face: MediaPipe or the face model is missing; Szájtorna\'s mirror will count by the parent\'s taps only.')
  process.exit(0)
}

mkdirSync(to, { recursive: true })
const files = [
  ...['vision_wasm_internal', 'vision_wasm_nosimd_internal'].flatMap(f => [`${f}.js`, `${f}.wasm`].map(n => join(wasm, n))),
  model,
]
let bytes = 0
for (const f of files) {
  const dest = join(to, f.split(/[\\/]/).pop())
  cpSync(f, dest)
  bytes += statSync(dest).size
}
console.log(`copy-face: ${files.length} files (${(bytes / 1048576).toFixed(1)} MB) → ${to}`)
