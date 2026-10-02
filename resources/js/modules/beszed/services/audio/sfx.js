import { config } from '../../config/options'

/**
 * Tiny synthesised sound effects (WebAudio): instant, and no audio files to
 * download or cache. Kept soft so they never cover Csillám's voice.
 */
let context = null

function audio() {
  if (!config.sfx.enabled || typeof window === 'undefined') return null
  const Ctor = window.AudioContext || window.webkitAudioContext
  if (!Ctor) return null
  context ??= new Ctor()
  return context
}

/** iOS keeps WebAudio suspended until a tap; call from inside one (guide.unlock does). */
export function unlockSfx() {
  try {
    const ctx = audio()
    if (ctx?.state === 'suspended') ctx.resume()
  } catch {
    /* audio not available */
  }
}

/** One enveloped note starting `at` seconds from now. */
function note(freq, at, duration, { type = 'sine', gain = 0.25, slideTo } = {}) {
  const ctx = audio()
  if (!ctx) return
  const osc = ctx.createOscillator()
  const amp = ctx.createGain()
  const start = ctx.currentTime + at
  const peak = gain * config.sfx.volume
  osc.type = type
  osc.frequency.setValueAtTime(freq, start)
  if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, start + duration)
  amp.gain.setValueAtTime(0.0001, start)
  amp.gain.exponentialRampToValueAtTime(peak, start + 0.012)
  amp.gain.exponentialRampToValueAtTime(0.0001, start + duration)
  osc.connect(amp).connect(ctx.destination)
  osc.start(start)
  osc.stop(start + duration + 0.02)
}

function play(fn) {
  try {
    fn()
  } catch {
    /* audio not available */
  }
}

/** Short "drum" blip (taps, placing things). */
export function beep(freq = 160, duration = 0.14) {
  play(() => note(freq, 0, duration, { type: 'triangle', gain: 0.5, slideTo: freq * 0.5 }))
}

/** One clear note, held for `duration` s (Állatkórus: each animal sings its own). */
export function tone(freq, duration = 0.4) {
  play(() => note(freq, 0, duration, { type: 'triangle', gain: 0.4 }))
}

/** A soft plucked note (every touch in the app plays one; see services/touch/feel.js). */
export function pluck(freq) {
  play(() => note(freq, 0, 0.18, { gain: 0.1 }))
}

/**
 * Right answer: two bright notes. `combo` (answers right at the first try in a
 * row) lifts them a step each time, up to a fifth higher, so a streak sounds like it.
 */
export function chime(combo = 0) {
  const lift = 2 ** (Math.min(combo, 5) * 1.4 / 12)
  play(() => {
    note(1318.5 * lift, 0, 0.16, { gain: 0.18 }) // E6
    note(1760 * lift, 0.08, 0.28, { gain: 0.16 }) // A6
    if (combo >= 3) note(2637 * lift, 0.18, 0.3, { gain: 0.08 }) // a sparkle on top of a streak
  })
}

/** Game finished: a little rising arpeggio. */
export function fanfare() {
  play(() => {
    ;[523.25, 659.25, 783.99, 1046.5].forEach((f, i) => note(f, i * 0.11, 0.3, { type: 'triangle', gain: 0.22 }))
    note(1046.5, 0.44, 0.55, { gain: 0.14 })
  })
}

/** New sticker / accessory: a quick shimmer. */
export function sparkle() {
  play(() => {
    ;[1568, 2093, 2637, 3136].forEach((f, i) => note(f, i * 0.06, 0.18, { gain: 0.08 }))
  })
}

/** Level up: a longer, brighter run. */
export function levelUp() {
  play(() => {
    ;[392, 523.25, 659.25, 783.99, 1046.5, 1318.5].forEach((f, i) =>
      note(f, i * 0.09, 0.32, { type: 'triangle', gain: 0.2 }),
    )
    note(1568, 0.58, 0.7, { gain: 0.12 })
  })
}

/** Loud applause: multiple claps with crowd noise. */
export function applause() {
  play(() => {
    const ctx = audio()
    if (!ctx) return
    const start = ctx.currentTime
    const baseGain = 0.3 * config.sfx.volume

    // Create 4-5 claps with varying timing
    ;[0, 0.15, 0.28, 0.42, 0.54].forEach((offset, idx) => {
      // Create each clap from noise bursts (higher frequencies for impact)
      ;[1200, 3400, 5600].forEach((freq, fi) => {
        const osc = ctx.createOscillator()
        const amp = ctx.createGain()
        const clap = ctx.createBiquadFilter()
        clap.type = 'highpass'
        clap.frequency.value = 800 + idx * 100
        const duration = 0.06 + fi * 0.01

        osc.type = 'square'
        osc.frequency.value = freq + fi * 200
        amp.gain.setValueAtTime(baseGain * 0.3, start + offset)
        amp.gain.exponentialRampToValueAtTime(0.0001, start + offset + duration)

        osc.connect(clap).connect(amp).connect(ctx.destination)
        osc.start(start + offset)
        osc.stop(start + offset + duration)
      })
    })

    // Add low rumble for depth (crowd sound)
    note(60, 0.3, 0.5, { type: 'sine', gain: 0.08, slideTo: 80 })
    note(120, 0.4, 0.4, { type: 'sine', gain: 0.06, slideTo: 100 })
  })
}

/** A flower opens: a little upward pop and a twinkle. */
export function bloom() {
  play(() => {
    note(880, 0, 0.12, { gain: 0.14, slideTo: 1320 })
    note(1568, 0.09, 0.22, { gain: 0.1 })
  })
}

/** Csillám giggles when she is tapped: a quick, bubbly run of notes. */
export function giggle() {
  play(() =>
    [0, 0.07, 0.14, 0.21, 0.28].forEach((at, i) =>
      note(820 + (i % 2 ? 300 : 0) + i * 45, at, 0.09, { gain: 0.12, slideTo: 1500 }),
    ),
  )
}

/** The daily gift opens: a bright rising arpeggio. */
export function gift() {
  play(() => [523.25, 659.25, 783.99, 1046.5, 1318.5].forEach((f, i) => note(f, i * 0.09, 0.3, { gain: 0.16 })))
}
