/**
 * Szájtorna's automatic counting: the pure logic (no Vue, no camera; tested in tests/js/poses.test.mjs).
 * The face tracker (faceTracker.js, MediaPipe Face Landmarker, on the device) gives 0–1 scores of the
 * face's movements every frame ("blendshapes": jawOpen, mouthPucker, cheekPuff…). Each exercise move the
 * server could name a `pose` for (SzajtornaRounds::POSES) becomes a gate here; going through every move's
 * pose once is one repetition. Moves without a pose ("pihenj", "szívd be az arcod": no score shows them)
 * are simply not waited for. The order is not checked: in the mirror, left and right swap anyway.
 */

const avg = (a, b) => ((a ?? 0) + (b ?? 0)) / 2

/**
 * pose → its score from the blendshapes, the level that counts as done (`on`, at least `lift` above the
 * child's own resting face) and `off` below which it is over. Cheeks and lips rolled in score weakly
 * in MediaPipe, so they ask less.
 */
export const POSES = {
  puff: { score: b => b.cheekPuff, on: 0.25, off: 0.12, lift: 0.12 },
  pucker: { score: b => Math.max(b.mouthPucker ?? 0, b.mouthFunnel ?? 0), on: 0.55, off: 0.35, lift: 0.25 },
  smile: { score: b => avg(b.mouthSmileLeft, b.mouthSmileRight), on: 0.5, off: 0.3, lift: 0.25 },
  open: { score: b => b.jawOpen, on: 0.4, off: 0.22, lift: 0.25 },
  frown: { score: b => avg(b.mouthFrownLeft, b.mouthFrownRight), on: 0.3, off: 0.15, lift: 0.15 },
  roll: { score: b => avg(b.mouthRollLower, b.mouthRollUpper), on: 0.35, off: 0.2, lift: 0.15 },
  rollLower: { score: b => b.mouthRollLower, on: 0.4, off: 0.22, lift: 0.15 },
  rollUpper: { score: b => b.mouthRollUpper, on: 0.4, off: 0.22, lift: 0.15 },
  left: { score: b => b.mouthLeft, on: 0.3, off: 0.15, lift: 0.15 },
  right: { score: b => b.mouthRight, on: 0.3, off: 0.15, lift: 0.15 },
}

const clamp01 = x => Math.min(1, Math.max(0, x))

/** MediaPipe's result (faceBlendshapes[0].categories) → { name: score }; null when no face was found. */
export function toScores(result) {
  const cats = result?.faceBlendshapes?.[0]?.categories
  if (!cats?.length) return null
  const out = {}
  for (const c of cats) out[c.categoryName] = c.score
  return out
}

/**
 * The child's resting face: the median score of each pose over the first `ms` with a face in view. A
 * child whose mouth is a little open at rest then has to open it more than that to count.
 */
export function createBaseline(poses, { ms = 900 } = {}) {
  let elapsed = 0
  const seen = Object.fromEntries(poses.map(p => [p, []]))
  let base = null
  return {
    get ready() {
      return base !== null
    },
    get values() {
      return base
    },
    update(scores, dt) {
      if (base || !scores) return base
      for (const p of poses) seen[p].push(POSES[p].score(scores) ?? 0)
      elapsed += dt
      if (elapsed >= ms) {
        base = {}
        for (const p of poses) {
          const v = [...seen[p]].sort((a, b) => a - b)
          base[p] = v[v.length >> 1] ?? 0
        }
      }
      return base
    },
  }
}

/** The on/off levels of a pose for this child (never above 0.85, so it stays reachable). */
export function levelsFor(pose, base = 0) {
  const def = POSES[pose]
  const on = Math.min(0.85, Math.max(def.on, base + def.lift))
  return { on, off: Math.max(0, on - (def.on - def.off)) }
}

/**
 * Counts repetitions of one exercise. `moves`: [{ pose }] (pose null = not watched). A pose counts once it
 * has been held `holdMs`; it can count again only after dropping below its `off` level. When every
 * watched move has counted (a pose twice if two moves ask for it), that is one repetition.
 * update() returns { rep, active, strength } each frame: rep = a repetition was finished on this frame,
 * active = the move index now being held (for the picture), strength = 0–1 of the next pose still needed.
 */
export function createRepCounter(moves, { holdMs = 250, base = {} } = {}) {
  const watched = moves.map((m, i) => ({ pose: m.pose, i })).filter(m => m.pose && POSES[m.pose])
  const poses = [...new Set(watched.map(m => m.pose))]
  const need = Object.fromEntries(poses.map(p => [p, watched.filter(m => m.pose === p).length]))
  const levels = Object.fromEntries(poses.map(p => [p, levelsFor(p, base[p] ?? 0)]))
  const state = Object.fromEntries(poses.map(p => [p, { on: false, held: 0, counted: false }]))
  let got = Object.fromEntries(poses.map(p => [p, 0]))
  let reps = 0

  /** The move a pose's next count stands for: its first move not yet counted in this repetition. */
  const moveOf = pose => watched.filter(m => m.pose === pose)[Math.min(got[pose], need[pose] - 1)]?.i ?? null

  return {
    get watched() {
      return watched.length > 0
    },
    get reps() {
      return reps
    },
    get progress() {
      const total = watched.length
      return total ? poses.reduce((s, p) => s + Math.min(got[p], need[p]), 0) / total : 0
    },
    update(scores, dt) {
      let rep = false
      let active = null
      let strength = 0
      if (!scores) return { rep, active, strength }
      for (const p of poses) {
        const v = POSES[p].score(scores) ?? 0
        const st = state[p]
        const { on, off } = levels[p]
        if (got[p] < need[p]) strength = Math.max(strength, clamp01(v / on))
        if (v >= on) {
          st.held += dt
          if (!st.on && st.held >= holdMs) {
            st.on = true
            if (got[p] < need[p]) {
              active = moveOf(p)
              got[p]++
              st.counted = true
            }
          } else if (st.on && st.counted) {
            active = watched.filter(m => m.pose === p)[got[p] - 1]?.i ?? null
          }
        } else if (v < off) {
          st.held = 0
          st.on = false
          st.counted = false
        } else {
          st.held = Math.max(0, st.held - dt)
        }
      }
      if (poses.length && poses.every(p => got[p] >= need[p])) {
        reps++
        rep = true
        got = Object.fromEntries(poses.map(p => [p, 0]))
      }
      return { rep, active, strength }
    },
  }
}
