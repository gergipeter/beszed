/**
 * The pure signal logic of the voice engine (Fújóka, Hangrepülő): no Vue, no WebAudio, so it can be
 * tested with node (tests/js/voice.test.mjs). Every function takes plain numbers or a Float32Array of
 * microphone samples; the engine feeds them one animation frame at a time (dt = ms since the last frame).
 * Nothing here records or keeps sound: a frame is measured and forgotten.
 */

export const DB_MIN = -100

/** Loudness of one frame: root mean square of the samples (−1…1). */
export function rms(samples) {
  let sum = 0
  for (let i = 0; i < samples.length; i++) sum += samples[i] * samples[i]
  return samples.length ? Math.sqrt(sum / samples.length) : 0
}

/** RMS → dBFS (0 = the loudest the mic can give), never below DB_MIN. */
export const toDb = r => (r > 0 ? Math.max(DB_MIN, 20 * Math.log10(r)) : DB_MIN)

const clamp = (x, lo, hi) => Math.min(hi, Math.max(lo, x))

export function median(values) {
  if (!values.length) return 0
  const sorted = [...values].sort((a, b) => a - b)
  const mid = sorted.length >> 1
  return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2
}

/**
 * The room's own noise, in dB. The first `calibrateMs` are measured (median, so a cough doesn't count);
 * after that only quiet frames (near or below the floor) move it: down quickly, up slowly, so a noisier
 * room is followed but the child's own sound never lifts the floor.
 */
export function createNoiseFloor({ calibrateMs = 500, minDb = -80, maxDb = -32, nearDb = 6, downMs = 300, upMs = 4000 } = {}) {
  let elapsed = 0
  let samples = []
  let floor = -60
  let calibrated = false
  return {
    get db() {
      return floor
    },
    get calibrated() {
      return calibrated
    },
    update(db, dt) {
      if (!calibrated) {
        samples.push(db)
        elapsed += dt
        if (elapsed >= calibrateMs) {
          floor = clamp(median(samples), minDb, maxDb)
          calibrated = true
          samples = []
        }
        return floor
      }
      if (db < floor + nearDb) {
        const tau = db < floor ? downMs : upMs
        floor = clamp(floor + (db - floor) * (1 - Math.exp(-dt / tau)), minDb, maxDb)
      }
      return floor
    },
  }
}

/**
 * How far above the floor a sound must be. Blowing into the mic is loud, so `blow` asks more than the
 * voice does; `strong` splits a gentle blow from a hard one. `min…` keep a very quiet room from making
 * every rustle count. All in dB.
 */
export const LEVELS = {
  blow: { on: 15, off: 10, strong: 30, minOn: -52, minStrong: -26 },
  voice: { on: 10, off: 6, strong: 30, minOn: -58, minStrong: -26 },
}

/**
 * On/off with hysteresis (on above `on`, off only after `releaseMs` below `off`), plus the strength:
 * 0 = silent, 1 = on (soft), 2 = strong.
 */
export function createGate(levels = LEVELS.voice, { releaseMs = 120 } = {}) {
  let on = false
  let quiet = 0
  return {
    get on() {
      return on
    },
    update(db, floorDb, dt) {
      const onAt = Math.max(floorDb + levels.on, levels.minOn)
      const offAt = Math.max(floorDb + levels.off, levels.minOn - (levels.on - levels.off))
      if (db >= onAt) {
        on = true
        quiet = 0
      } else if (on && db < offAt) {
        quiet += dt
        if (quiet >= releaseMs) on = false
      } else {
        quiet = 0
      }
      if (!on) return 0
      return db >= Math.max(floorDb + levels.strong, levels.minStrong) ? 2 : 1
    },
  }
}

/** 0–1 for the live meter: from the floor to 40 dB above it. */
export const meterLevel = (db, floorDb) => clamp((db - floorDb) / 40, 0, 1)

/**
 * Short puffs (Fújóka, level 1): a puff counts once it has been on for `minMs`; the next one only after
 * a pause of `gapMs`. A long blow is one puff: the child learns to blow short and separately.
 * update() returns true on the frame a puff counts.
 */
export function createPuffCounter({ minMs = 70, gapMs = 160 } = {}) {
  let onFor = 0
  let offFor = Infinity
  let armed = true
  let count = 0
  return {
    get count() {
      return count
    },
    update(on, dt) {
      if (on) {
        onFor += dt
        offFor = 0
        if (armed && onFor >= minMs) {
          armed = false
          count++
          return true
        }
        return false
      }
      onFor = 0
      offFor += dt
      if (offFor >= gapMs) armed = true
      return false
    },
  }
}

/**
 * Holding a blow or a sound for `goalMs`.
 * total (continuous: false): every moment on counts, pauses are free (Hangrepülő level 1).
 * continuous: after a pause longer than `graceMs` the progress slides back (the rocket falls, the boat
 * drifts) at goalMs per `fallMs`, and a stop after at least `minRunMs` on counts as one fall.
 */
export function createHoldTimer({ goalMs, continuous = false, graceMs = 250, fallMs = 700, minRunMs = 300 } = {}) {
  let held = 0
  let run = 0
  let quiet = 0
  let falls = 0
  let done = false
  return {
    update(on, dt) {
      if (!done) {
        if (on) {
          held += dt
          run += dt
          quiet = 0
        } else {
          quiet += dt
          if (continuous && quiet > graceMs) {
            if (run >= minRunMs) falls++
            run = 0
            held = Math.max(0, held - (dt * goalMs) / fallMs)
          }
        }
        if (held >= goalMs) done = true
      }
      return {
        progress: clamp(held / goalMs, 0, 1),
        done,
        falls,
        falling: !done && continuous && quiet > graceMs && held > 0,
      }
    },
  }
}

/**
 * A gentle blow for `goalMs` (the feather floats, the bubble grows). Soft (strength 1) fills it; a pause
 * longer than `graceMs` lets it sink slowly; a strong blow held for `strongMs` is an "oops" (the feather
 * flies off, the bubble pops) and empties it.
 */
export function createGentleTimer({ goalMs, graceMs = 300, sinkMs = 6000, strongMs = 150 } = {}) {
  let held = 0
  let quiet = 0
  let strongFor = 0
  let oops = 0
  let done = false
  return {
    update(strength, dt) {
      let popped = false
      if (!done) {
        if (strength === 2) {
          strongFor += dt
          quiet = 0
          // one "oops" per strong blow, on the frame it has lasted long enough
          if (strongFor >= strongMs && strongFor - dt < strongMs) {
            held = 0
            oops++
            popped = true
          }
        } else {
          strongFor = 0
          if (strength === 1) {
            held += dt
            quiet = 0
          } else {
            quiet += dt
            if (quiet > graceMs) held = Math.max(0, held - (dt * goalMs) / sinkMs)
          }
        }
        if (held >= goalMs) done = true
      }
      return { progress: clamp(held / goalMs, 0, 1), done, oops, popped, tooStrong: strongFor >= strongMs }
    },
  }
}

/**
 * Soft and strong in turn (Fújóka, level 3): each step must be held at its strength for `stepMs`.
 * Silence keeps what was done of the step for a while; the wrong strength takes it back.
 */
export function createStepper(steps, { stepMs = 900 } = {}) {
  let index = 0
  let held = 0
  return {
    get index() {
      return index
    },
    update(strength, dt) {
      let stepped = false
      if (index < steps.length) {
        const want = steps[index] === 'strong' ? 2 : 1
        if (strength === want) held += dt
        else if (strength === 0) held = Math.max(0, held - dt * 0.25)
        else held = Math.max(0, held - dt)
        if (held >= stepMs) {
          index++
          held = 0
          stepped = true
        }
      }
      return { index, stepProgress: clamp(held / stepMs, 0, 1), stepped, done: index >= steps.length }
    },
  }
}

/**
 * Fundamental frequency of one frame by normalised autocorrelation (McLeod's NSDF), or hz 0.
 * `clarity` (0–1) says how periodic the frame is: a voice or a whistle is high, a blow (noise) is low.
 * Above 32 kHz the frame is halved first (pairs averaged), which keeps it cheap on phones.
 */
export function estimatePitch(samples, sampleRate, { minHz = 80, maxHz = 1000, threshold = 0.6, size = 1024 } = {}) {
  let x = samples
  let rate = sampleRate
  if (rate > 32000) {
    const half = new Float32Array(samples.length >> 1)
    for (let i = 0; i < half.length; i++) half[i] = (samples[2 * i] + samples[2 * i + 1]) / 2
    x = half
    rate = sampleRate / 2
  }
  const n = Math.min(x.length, size)
  const minLag = Math.max(2, Math.floor(rate / maxHz))
  const maxLag = Math.min(Math.floor(rate / minHz), n - 2)
  if (maxLag <= minLag) return { hz: 0, clarity: 0 }

  const nsdf = new Float32Array(maxLag + 2)
  for (let lag = 0; lag <= maxLag + 1; lag++) {
    let acf = 0
    let energy = 0
    for (let i = 0; i + lag < n; i++) {
      acf += x[i] * x[i + lag]
      energy += x[i] * x[i] + x[i + lag] * x[i + lag]
    }
    nsdf[lag] = energy > 0 ? (2 * acf) / energy : 0
  }

  // key maxima: the highest point of each positive lobe after the first zero crossing
  const peaks = []
  let lag = 1
  while (lag < maxLag && nsdf[lag] > 0) lag++
  while (lag <= maxLag) {
    while (lag <= maxLag && nsdf[lag] <= 0) lag++
    let best = -1
    while (lag <= maxLag && nsdf[lag] > 0) {
      if (lag >= minLag && (best < 0 || nsdf[lag] > nsdf[best])) best = lag
      lag++
    }
    if (best > 0) peaks.push(best)
  }
  if (!peaks.length) return { hz: 0, clarity: 0 }
  const top = Math.max(...peaks.map(p => nsdf[p]))
  const chosen = peaks.find(p => nsdf[p] >= 0.9 * top)
  const clarity = nsdf[chosen]
  if (clarity < threshold) return { hz: 0, clarity: Math.max(0, clarity) }

  // parabolic interpolation around the peak for a finer lag
  const a = nsdf[chosen - 1]
  const b = nsdf[chosen]
  const c = nsdf[chosen + 1]
  const denom = a - 2 * b + c
  const shift = denom ? clamp((0.5 * (a - c)) / denom, -0.5, 0.5) : 0
  return { hz: rate / (chosen + shift), clarity }
}

/**
 * Height (0 = low, 1 = high) of the child's voice relative to their own first `baselineMs` of voice.
 * ±`rangeSt` semitones span the whole height; recent pitches are median-filtered against octave slips.
 * nudge(dir) moves the baseline a little (a child who can't reach a star gets it a bit closer).
 */
export function createPitchTracker({ baselineMs = 1000, rangeSt = 6, window = 5 } = {}) {
  let voicedMs = 0
  let first = []
  let base = 0
  const recent = []
  return {
    get baseline() {
      return base
    },
    update(hz, dt) {
      if (!(hz > 0)) return null
      recent.push(hz)
      if (recent.length > window) recent.shift()
      if (!base) {
        first.push(hz)
        voicedMs += dt
        if (voicedMs >= baselineMs) {
          base = median(first)
          first = []
        }
        return 0.5
      }
      const st = 12 * Math.log2(median(recent) / base)
      return clamp(0.5 + st / (2 * rangeSt), 0, 1)
    },
    nudge(dir, st = 0.5) {
      if (base) base *= 2 ** ((-dir * st) / 12)
    },
  }
}

/** Without a pitch (a whisper, a noisy mic): loud = high. 0 dB above the on-level → 0.2, +20 dB → 0.9. */
export const heightFromLoudness = (db, floorDb, levels = LEVELS.voice) =>
  clamp(0.2 + ((db - Math.max(floorDb + levels.on, levels.minOn)) / 20) * 0.7, 0, 1)

/** Is the frame clearly voiced (a periodic sound in the range of a voice)? Fújóka wants a blow, not a "fúúú". */
export const isVoiced = ({ hz, clarity }, minClarity = 0.8) => hz >= 70 && hz <= 1200 && clarity >= minClarity
