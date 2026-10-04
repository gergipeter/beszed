/**
 * Telling "sz" from "s" by ear, on the phone: the pure logic of Hangrepülő's sound check (no Vue, no
 * WebAudio; tested in tests/js/hiss.test.mjs). Both are hisses, but the tongue makes them in different
 * places, and that moves the noise's pitch: "sz" (/s/) is a high, thin hiss with most of its energy
 * around 6–9 kHz, "s" (/ʃ/) a lower, darker one around 3–5 kHz (higher in a child's small mouth, in both).
 * The spectral centroid (the energy's centre of gravity) between 1.5 and 11 kHz splits them.
 *
 * It is lenient on purpose: a frame counts as the wrong sound only when it is clearly the other one, so
 * an unclear hiss, a child's in-between sound or a poor microphone never stops the game, it only goes
 * unjudged. Like signal.js, each frame is measured and forgotten: nothing is recorded or sent.
 */

/** The sounds the check knows, and where their centroids fall (Hz, 1.5–11 kHz band). */
export const HISSES = {
  // "sz": clearly right above `right`, clearly the other sound ("s") below `wrong`
  sz: { right: 6000, wrong: 5000, high: true },
  // "s": clearly right below `right`, clearly the other sound ("sz") above `wrong`
  s: { right: 5600, wrong: 6800, high: false },
}

export const BAND_LO = 1500
export const BAND_HI = 11000
/** Below this the microphone can't hear an "sz" (its energy is above 6 kHz): the check switches off. */
export const MIN_RATE = 22050
/** A real microphone always hears a little above this, even in an "s"; a band-limited one hears nothing. */
export const TOP_HZ = 9000

/** In-place radix-2 FFT (re, im: Float64Array of the same power-of-two length). */
export function fft(re, im) {
  const n = re.length
  for (let i = 1, j = 0; i < n; i++) {
    let bit = n >> 1
    for (; j & bit; bit >>= 1) j ^= bit
    j ^= bit
    if (i < j) {
      ;[re[i], re[j]] = [re[j], re[i]]
      ;[im[i], im[j]] = [im[j], im[i]]
    }
  }
  for (let len = 2; len <= n; len <<= 1) {
    const ang = (-2 * Math.PI) / len
    const wr = Math.cos(ang)
    const wi = Math.sin(ang)
    const half = len >> 1
    for (let i = 0; i < n; i += len) {
      let cr = 1
      let ci = 0
      for (let k = 0; k < half; k++) {
        const a = i + k
        const b = a + half
        const tr = re[b] * cr - im[b] * ci
        const ti = re[b] * ci + im[b] * cr
        re[b] = re[a] - tr
        im[b] = im[a] - ti
        re[a] += tr
        im[a] += ti
        const next = cr * wr - ci * wi
        ci = cr * wi + ci * wr
        cr = next
      }
    }
  }
}

/**
 * One frame's hiss features: `centroid` (Hz, in the 1.5–11 kHz band), `hiss` (0–1, the share of the
 * energy above 1.5 kHz: a hiss is mostly high, a vowel or a hum mostly low) and `top` (the share of the
 * band's energy above TOP_HZ, for telling a band-limited microphone: see createMicCheck). Null when the
 * sample rate is too low to hear an "sz". Uses the first power-of-two (≤ 2048) samples, Hann-windowed.
 */
export function hissFeatures(samples, sampleRate) {
  if (sampleRate < MIN_RATE) return null
  let n = 1
  while (n * 2 <= Math.min(samples.length, 2048)) n *= 2
  if (n < 256) return null
  const re = new Float64Array(n)
  const im = new Float64Array(n)
  for (let i = 0; i < n; i++) re[i] = samples[i] * (0.5 - 0.5 * Math.cos((2 * Math.PI * i) / (n - 1)))
  fft(re, im)

  const hzPerBin = sampleRate / n
  const lowBin = Math.max(1, Math.round(100 / hzPerBin))
  const loBin = Math.round(BAND_LO / hzPerBin)
  const hiBin = Math.min(n / 2 - 1, Math.round(BAND_HI / hzPerBin))
  let all = 0
  let band = 0
  let moment = 0
  let top = 0
  const topBin = Math.round(TOP_HZ / hzPerBin)
  for (let k = lowBin; k < n / 2; k++) {
    const p = re[k] * re[k] + im[k] * im[k]
    all += p
    if (k >= loBin && k <= hiBin) {
      band += p
      moment += p * k * hzPerBin
      if (k >= topBin) top += p
    }
  }
  return { centroid: band > 0 ? moment / band : 0, hiss: all > 0 ? band / all : 0, top: band > 0 ? top / band : 0 }
}

/**
 * One frame against the sound to make: 'right', 'wrong' (clearly the other hiss), 'unsure' (in between),
 * or null when the frame is no hiss at all (a vowel, a hum: `hiss` below `minHiss`).
 */
export function judgeHiss(features, target, { minHiss = 0.6 } = {}) {
  const t = HISSES[target]
  if (!t || !features || features.hiss < minHiss) return null
  const c = features.centroid
  if (t.high) return c >= t.right ? 'right' : c <= t.wrong ? 'wrong' : 'unsure'
  return c <= t.right ? 'right' : c >= t.wrong ? 'wrong' : 'unsure'
}

/**
 * The last `window` judged frames → the verdict the game acts on: 'wrong' once at least `minFrames` are
 * judged and `wrongShare` of them are wrong, 'right' when `rightShare` are right, else 'unsure'.
 * Silence (null) is not judged and changes nothing; reset() forgets, e.g. when the child stops.
 */
export function createSoundCheck({ window = 12, minFrames = 6, wrongShare = 0.6, rightShare = 0.5 } = {}) {
  let recent = []
  let verdict = 'unsure'
  return {
    get verdict() {
      return verdict
    },
    update(judgement) {
      if (!judgement) return verdict
      recent.push(judgement)
      if (recent.length > window) recent.shift()
      if (recent.length < minFrames) return (verdict = 'unsure')
      const share = kind => recent.filter(j => j === kind).length / recent.length
      verdict = share('wrong') >= wrongShare ? 'wrong' : share('right') >= rightShare ? 'right' : 'unsure'
      return verdict
    },
    reset() {
      recent = []
      verdict = 'unsure'
    },
  }
}

/**
 * Can this microphone hear an "sz" at all? Some phones' echo cancellation (iOS voice processing among them)
 * cuts everything above about 8 kHz: a right "sz" would then sound like an "s", and the child would be told
 * off for a good sound. Over the first `frames` hissing frames, the loudest share of energy above TOP_HZ
 * tells: a real mic gives at least ~1 % even in an "s", a band-limited one next to nothing. Until it is
 * decided, and when it can't hear, `ok` is false and the game doesn't judge.
 */
export function createMicCheck({ frames = 10, minTop = 0.002 } = {}) {
  let seen = 0
  let best = 0
  let decided = null
  return {
    get ok() {
      return decided === true
    },
    get decided() {
      return decided !== null
    },
    update(features, { minHiss = 0.6 } = {}) {
      if (decided !== null || !features || features.hiss < minHiss) return decided === true
      seen++
      best = Math.max(best, features.top)
      if (seen >= frames) decided = best >= minTop
      return decided === true
    },
  }
}
