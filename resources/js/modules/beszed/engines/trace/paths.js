/**
 * Tracing paths in normalised canvas coordinates: t runs 0 → 1 along the path,
 * and each returns [x, y] with both in 0–1 (scaled to the canvas when drawn).
 *
 * Each shape is a factory `(difficulty) => (t) => [x, y]`: difficulty is 1–100
 * (CeruzaRounds' $level, unrelated to the content item's own 1–3 tier, which
 * only picks the shape family) and scales segment count / amplitude / sharpness
 * smoothly, so the same named shape keeps getting harder well past the point
 * where a different shape would've been picked next.
 */
const lerp = (difficulty, from, to) => from + ((to - from) * (Math.max(1, Math.min(100, difficulty)) - 1)) / 99

export const PATHS = {
  wave: difficulty => {
    const waves = lerp(difficulty, 1.5, 5)
    const amp = lerp(difficulty, 0.26, 0.16)
    return t => [0.08 + 0.84 * t, 0.5 + amp * Math.sin(t * Math.PI * 2 * waves)]
  },
  loops: difficulty => {
    const loops = lerp(difficulty, 2, 5)
    const amp = lerp(difficulty, 0.14, 0.24)
    return t => {
      const a = t * Math.PI * 2 * loops
      return [0.1 + 0.8 * t - 0.05 * Math.sin(a), 0.5 - amp * Math.cos(a)]
    }
  },
  zigzag: difficulty => {
    const n = Math.round(lerp(difficulty, 2, 7))
    return t => {
      const s = t * n
      const i = Math.min(n - 1, Math.floor(s))
      const f = s - i
      return [0.08 + 0.84 * t, i % 2 === 0 ? 0.74 - 0.46 * f : 0.28 + 0.46 * f]
    }
  },
  arches: difficulty => {
    const arches = lerp(difficulty, 1.5, 5)
    return t => [0.08 + 0.84 * t, 0.74 - 0.46 * Math.abs(Math.sin(t * Math.PI * arches))]
  },
  /** A staircase going up: flat treads, short steep risers. */
  steps: difficulty => {
    const n = Math.round(lerp(difficulty, 3, 7))
    const steep = lerp(difficulty, 0.7, 0.4)
    return t => {
      const s = t * n
      const i = Math.min(n - 1, Math.floor(s))
      const f = s - i
      const rise = f < steep ? 0 : (f - steep) / (1 - steep)
      return [0.08 + 0.84 * t, 0.78 - (0.88 / n) * (i + rise)]
    }
  },
  /** Gentle hills, more of them and a bit taller as it gets harder. */
  hills: difficulty => {
    const hills = lerp(difficulty, 1, 3.5)
    const amp = lerp(difficulty, 0.34, 0.44)
    return t => [0.08 + 0.84 * t, 0.72 - amp * Math.abs(Math.sin(t * Math.PI * hills))]
  },
}

/** `samples` evenly spaced points along path `name` (wave if unknown) at `difficulty` (1–100). */
export function samplePath(name, difficulty = 1, samples = 240) {
  const path = (PATHS[name] ?? PATHS.wave)(difficulty)
  return Array.from({ length: samples }, (_, i) => path(i / (samples - 1)))
}
