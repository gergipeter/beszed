import { defineAsyncComponent, markRaw } from 'vue'

/**
 * Engine registry: name (Round.engine from the server) → lazy loader.
 * Each engine is its own chunk, so a game only downloads the engines it uses.
 * Adding an engine = a folder here + one line below (or registerEngine()).
 */
const loaders = {
  choice: () => import('./choice/ChoiceEngine.vue'),
  sequence: () => import('./sequence/SequenceEngine.vue'),
  tapcount: () => import('./tapcount/TapCountEngine.vue'),
  trace: () => import('./trace/TraceEngine.vue'),
  judged: () => import('./judged/JudgedEngine.vue'),
  puzzle: () => import('./puzzle/PuzzleEngine.vue'),
  memory: () => import('./memory/MemoryEngine.vue'),
  sort: () => import('./sort/SortEngine.vue'),
  difference: () => import('./difference/DifferenceEngine.vue'),
  vanish: () => import('./vanish/VanishEngine.vue'),
  order: () => import('./order/OrderEngine.vue'),
  simon: () => import('./simon/SimonEngine.vue'),
}

/** Engines already downloaded, as plain components (render with no async gap). */
const ready = {}
const lazy = {}

export function registerEngine(name, loader) {
  loaders[name] = loader
  delete ready[name]
  delete lazy[name]
}

export const hasEngine = name => name in loaders

/** Downloads the engines a session needs, before its first round shows. */
export function preloadEngines(names) {
  const missing = [...new Set(names)].filter(name => hasEngine(name) && !ready[name])
  return Promise.all(
    missing.map(name => loaders[name]().then(mod => (ready[name] = markRaw(mod.default ?? mod)))),
  )
}

/** Component for `name`, or null when no such engine is registered. */
export function resolveEngine(name) {
  if (!hasEngine(name)) return null
  return ready[name] ?? (lazy[name] ??= defineAsyncComponent(loaders[name]))
}
