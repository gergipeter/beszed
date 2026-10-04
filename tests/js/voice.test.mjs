// The voice engine's signal logic (Fújóka, Hangrepülő): noise floor, gate, puffs, holds, pitch.
import assert from 'node:assert/strict'
import { test } from 'node:test'
import {
  LEVELS,
  createGate,
  createGentleTimer,
  createHoldTimer,
  createNoiseFloor,
  createPitchTracker,
  createPuffCounter,
  createStepper,
  estimatePitch,
  heightFromLoudness,
  isVoiced,
  rms,
  toDb,
} from '../../resources/js/modules/beszed/engines/voice/signal.js'

const RATE = 48000
const FRAME = 2048
const DT = 16

const sine = (hz, amp = 0.3, rate = RATE, n = FRAME) => Float32Array.from({ length: n }, (_, i) => amp * Math.sin((2 * Math.PI * hz * i) / rate))
/** A voice-like tone: a fundamental and a few harmonics. */
const voice = (hz, amp = 0.2) =>
  Float32Array.from({ length: FRAME }, (_, i) => amp * [1, 0.6, 0.4, 0.25].reduce((s, a, k) => s + a * Math.sin((2 * Math.PI * hz * (k + 1) * i) / RATE), 0))
let seed = 7
const random = () => ((seed = (seed * 16807) % 2147483647) / 2147483647) * 2 - 1
const noise = (amp = 0.3) => Float32Array.from({ length: FRAME }, () => amp * random())

test('rms and dB', () => {
  assert.ok(Math.abs(rms(sine(440, 1)) - Math.SQRT1_2) < 0.01)
  assert.equal(toDb(1), 0)
  assert.ok(Math.abs(toDb(0.1) + 20) < 1e-9)
  assert.equal(toDb(0), -100)
})

test('noise floor: calibrates on the median, follows quiet frames, ignores the child', () => {
  const floor = createNoiseFloor({ calibrateMs: 500 })
  for (let t = 0; t < 500; t += DT) floor.update(t === 160 ? -10 : -62, DT) // one cough in the calibration
  assert.ok(floor.calibrated)
  assert.ok(Math.abs(floor.db + 62) < 0.5, `floor ${floor.db}`)
  // a long loud sound (the child) does not lift it
  for (let t = 0; t < 5000; t += DT) floor.update(-20, DT)
  assert.ok(floor.db < -60, `floor rose to ${floor.db}`)
  // a slightly noisier room is followed, slowly
  for (let t = 0; t < 20000; t += DT) floor.update(-58, DT)
  assert.ok(floor.db > -59, `floor ${floor.db}`)
  // and a quieter room quickly
  for (let t = 0; t < 2000; t += DT) floor.update(-70, DT)
  assert.ok(floor.db < -69, `floor ${floor.db}`)
})

test('gate: hysteresis, release time, soft and strong', () => {
  const gate = createGate(LEVELS.blow)
  const floor = -60
  assert.equal(gate.update(-55, floor, DT), 0) // +5 dB: nothing
  assert.equal(gate.update(-40, floor, DT), 1) // +20 dB: a soft blow
  assert.equal(gate.update(-15, floor, DT), 2) // strong
  // a short dip between the on and off levels keeps it on
  assert.equal(gate.update(-47, floor, DT), 1)
  // under the off level it lets go only after the release time
  assert.equal(gate.update(-58, floor, 60), 1)
  assert.equal(gate.update(-58, floor, 80), 0)
  // a very quiet room: the absolute minimum still applies
  assert.equal(createGate(LEVELS.blow).update(-60, -90, DT), 0)
})

test('puffs: each short blow counts once, a long blow is one puff', () => {
  const puffs = createPuffCounter({ minMs: 70, gapMs: 160 })
  const run = (on, ms) => {
    let counted = 0
    for (let t = 0; t < ms; t += DT) counted += puffs.update(on, DT) ? 1 : 0
    return counted
  }
  assert.equal(run(true, 40), 0) // too short to be a puff
  assert.equal(run(false, 200), 0)
  assert.equal(run(true, 200), 1)
  assert.equal(run(false, 300), 0)
  assert.equal(run(true, 2000), 1) // one long blow = one puff
  assert.equal(run(false, 80), 0) // too short a pause…
  assert.equal(run(true, 200), 0) // …so this is still the same puff
  assert.equal(run(false, 300), 0)
  assert.equal(run(true, 120), 1)
  assert.equal(puffs.count, 3)
})

test('hold in total: pauses are free', () => {
  const hold = createHoldTimer({ goalMs: 2000 })
  let s
  for (let t = 0; t < 1200; t += DT) s = hold.update(true, DT)
  for (let t = 0; t < 3000; t += DT) s = hold.update(false, DT)
  assert.ok(s.progress > 0.55 && s.progress < 0.65, `progress ${s.progress}`)
  for (let t = 0; t < 900; t += DT) s = hold.update(true, DT)
  assert.equal(s.done, true)
  assert.equal(s.falls, 0)
})

test('hold without stopping: a pause makes it fall back and counts a fall', () => {
  const hold = createHoldTimer({ goalMs: 4000, continuous: true, graceMs: 250, fallMs: 700 })
  let s
  for (let t = 0; t < 2000; t += DT) s = hold.update(true, DT)
  assert.ok(Math.abs(s.progress - 0.5) < 0.02)
  for (let t = 0; t < 200; t += DT) s = hold.update(false, DT) // a breath inside the grace time
  assert.ok(Math.abs(s.progress - 0.5) < 0.02)
  assert.equal(s.falls, 0)
  for (let t = 0; t < 1000; t += DT) s = hold.update(false, DT)
  assert.equal(s.progress, 0)
  assert.equal(s.falls, 1)
  for (let t = 0; t < 4100; t += DT) s = hold.update(true, DT)
  assert.equal(s.done, true)
  assert.equal(s.falls, 1)
})

test('gentle blow: soft fills, strong pops it, silence sinks slowly', () => {
  const gentle = createGentleTimer({ goalMs: 3000 })
  let s
  for (let t = 0; t < 1500; t += DT) s = gentle.update(1, DT)
  assert.ok(s.progress > 0.45)
  for (let t = 0; t < 100; t += DT) s = gentle.update(2, DT) // a short spike is forgiven
  assert.equal(s.oops, 0)
  for (let t = 0; t < 400; t += DT) s = gentle.update(2, DT)
  assert.equal(s.oops, 1)
  assert.equal(s.progress, 0)
  for (let t = 0; t < 1500; t += DT) s = gentle.update(1, DT)
  const before = s.progress
  for (let t = 0; t < 1000; t += DT) s = gentle.update(0, DT)
  assert.ok(s.progress < before && s.progress > before - 0.2, `${before} → ${s.progress}`)
  for (let t = 0; t < 2500; t += DT) s = gentle.update(1, DT)
  assert.equal(s.done, true)
})

test('soft and strong in turn', () => {
  const steps = createStepper(['soft', 'strong', 'soft'], { stepMs: 900 })
  let s
  for (let t = 0; t < 1000; t += DT) s = steps.update(2, DT) // strong when soft is asked: nothing
  assert.equal(s.index, 0)
  for (let t = 0; t < 950; t += DT) s = steps.update(1, DT)
  assert.equal(s.index, 1)
  for (let t = 0; t < 950; t += DT) s = steps.update(2, DT)
  assert.equal(s.index, 2)
  for (let t = 0; t < 950; t += DT) s = steps.update(1, DT)
  assert.equal(s.done, true)
})

test('pitch: finds the fundamental of a tone and of a voice, not of noise', () => {
  for (const hz of [120, 220, 300, 450, 700]) {
    const found = estimatePitch(sine(hz), RATE)
    assert.ok(Math.abs(found.hz - hz) / hz < 0.02, `${hz} → ${found.hz}`)
  }
  for (const hz of [150, 260, 380]) {
    const found = estimatePitch(voice(hz), RATE)
    assert.ok(Math.abs(found.hz - hz) / hz < 0.03, `voice ${hz} → ${found.hz}`)
    assert.ok(isVoiced(found))
  }
  // 44.1 kHz too (no halving below 32 kHz is needed there either)
  const at441 = estimatePitch(sine(300, 0.3, 44100), 44100)
  assert.ok(Math.abs(at441.hz - 300) < 6)
  // a blow is noise: no pitch, not voiced
  const blow = estimatePitch(noise(), RATE)
  assert.equal(isVoiced(blow), false)
})

test('pitch tracker: relative to the child\'s own first second', () => {
  const tracker = createPitchTracker({ baselineMs: 1000, rangeSt: 6 })
  for (let t = 0; t < 1000; t += DT) assert.equal(tracker.update(250, DT), 0.5)
  assert.ok(Math.abs(tracker.baseline - 250) < 1)
  let y
  for (let i = 0; i < 6; i++) y = tracker.update(250 * 2 ** (4 / 12), DT) // 4 semitones up
  assert.ok(y > 0.8, `high ${y}`)
  for (let i = 0; i < 6; i++) y = tracker.update(250 * 2 ** (-4 / 12), DT)
  assert.ok(y < 0.2, `low ${y}`)
  // one octave slip among steady frames is filtered out
  for (let i = 0; i < 6; i++) tracker.update(250, DT)
  y = tracker.update(500, DT)
  assert.ok(Math.abs(y - 0.5) < 0.05, `slip ${y}`)
  assert.equal(tracker.update(0, DT), null)
  // a nudge upwards makes the same voice count higher
  tracker.nudge(1, 1)
  assert.ok(tracker.baseline < 250)
})

test('loudness stands in for pitch: louder is higher', () => {
  const low = heightFromLoudness(-48, -60)
  const high = heightFromLoudness(-30, -60)
  assert.ok(low < 0.3 && high > 0.8, `${low} ${high}`)
})
