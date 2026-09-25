/**
 * Tracing paths in normalised canvas coordinates: t runs 0 → 1 along the path,
 * and each returns [x, y] with both in 0–1 (scaled to the canvas when drawn).
 */
export const PATHS = {
  wave: t => [0.08 + 0.84 * t, 0.5 + 0.22 * Math.sin(t * Math.PI * 4)],
  loops: t => {
    const a = t * Math.PI * 8
    return [0.1 + 0.8 * t - 0.05 * Math.sin(a), 0.5 - 0.2 * Math.cos(a)]
  },
  zigzag: t => {
    const s = t * 5
    const i = Math.min(4, Math.floor(s))
    const f = s - i
    return [0.08 + 0.84 * t, i % 2 === 0 ? 0.74 - 0.46 * f : 0.28 + 0.46 * f]
  },
  arches: t => [0.08 + 0.84 * t, 0.74 - 0.46 * Math.abs(Math.sin(t * Math.PI * 4))],
}

/** `samples` evenly spaced points along path `name` (wave if unknown). */
export function samplePath(name, samples = 240) {
  const path = PATHS[name] ?? PATHS.wave
  return Array.from({ length: samples }, (_, i) => path(i / (samples - 1)))
}
