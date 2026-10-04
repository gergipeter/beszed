// Hangrepülő's sound check: telling "sz" (/s/) from "s" (/ʃ/) by the hiss's spectral centroid.
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createMicCheck, createSoundCheck, fft, hissFeatures, judgeHiss } from '../../resources/js/modules/beszed/engines/voice/hiss.js'

const RATE = 48000

/** A seeded random generator, so every run hears the same noise. */
function random(seed) {
  return () => {
    seed = (seed * 1664525 + 1013904223) % 4294967296
    return seed / 4294967296
  }
}

/** Noise between `lo` and `hi` Hz: many sines of random frequency and phase. */
function bandNoise(lo, hi, { n = 2048, rate = RATE, seed = 7, amp = 0.2 } = {}) {
  const rnd = random(seed)
  const out = new Float32Array(n)
  for (let s = 0; s < 120; s++) {
    const f = lo + rnd() * (hi - lo)
    const ph = rnd() * 2 * Math.PI
    for (let i = 0; i < n; i++) out[i] += (amp / 12) * Math.sin((2 * Math.PI * f * i) / rate + ph)
  }
  return out
}

/** A vowel: a 220 Hz voice with falling harmonics up to about 3 kHz. */
function vowel(n = 2048) {
  const out = new Float32Array(n)
  for (let h = 1; h * 220 < 3000; h++) for (let i = 0; i < n; i++) out[i] += (0.3 / h) * Math.sin((2 * Math.PI * 220 * h * i) / RATE)
  return out
}

test('fft finds a sine at its bin', () => {
  const n = 64
  const re = Float64Array.from({ length: n }, (_, i) => Math.cos((2 * Math.PI * 5 * i) / n))
  const im = new Float64Array(n)
  fft(re, im)
  const mags = [...re].map((r, k) => Math.hypot(r, im[k]))
  assert.equal(mags.indexOf(Math.max(...mags.slice(0, n / 2))), 5)
})

test('a high hiss ("sz") has a high centroid, a low one ("s") a low centroid', () => {
  const sz = hissFeatures(bandNoise(6000, 10000), RATE)
  const s = hissFeatures(bandNoise(2500, 4800), RATE)
  assert.ok(sz.centroid > 7000, `sz centroid ${sz.centroid}`)
  assert.ok(s.centroid < 4500, `s centroid ${s.centroid}`)
  assert.ok(sz.hiss > 0.9 && s.hiss > 0.9)
})

test('a vowel is no hiss and is not judged', () => {
  const f = hissFeatures(vowel(), RATE)
  assert.ok(f.hiss < 0.6, `vowel hiss share ${f.hiss}`)
  assert.equal(judgeHiss(f, 'sz'), null)
})

test('judging: right, wrong (clearly the other sound) and unsure in between', () => {
  const sz = hissFeatures(bandNoise(6000, 10000), RATE)
  const s = hissFeatures(bandNoise(2500, 4800), RATE)
  const between = hissFeatures(bandNoise(4500, 7000), RATE)
  assert.equal(judgeHiss(sz, 'sz'), 'right')
  assert.equal(judgeHiss(s, 'sz'), 'wrong')
  assert.equal(judgeHiss(s, 's'), 'right')
  assert.equal(judgeHiss(sz, 's'), 'wrong')
  assert.equal(judgeHiss(between, 'sz'), 'unsure')
  assert.equal(judgeHiss(sz, 'zs'), null)
})

test('too low a sample rate switches the check off', () => {
  assert.equal(hissFeatures(bandNoise(2500, 4800, { rate: 16000 }), 16000), null)
  assert.equal(judgeHiss(null, 'sz'), null)
})

test('the sound check needs most recent frames wrong before it says wrong', () => {
  const check = createSoundCheck({ window: 10, minFrames: 5 })
  for (let i = 0; i < 4; i++) check.update('wrong')
  assert.equal(check.verdict, 'unsure', 'too few frames to judge')
  check.update('wrong')
  assert.equal(check.verdict, 'wrong')
  check.update(null) // silence changes nothing
  assert.equal(check.verdict, 'wrong')
  for (let i = 0; i < 6; i++) check.update('right')
  assert.equal(check.verdict, 'right')
  check.reset()
  assert.equal(check.verdict, 'unsure')
})

test('a microphone that cuts the top off (echo cancellation) switches the check off', () => {
  const fullS = createMicCheck({ frames: 5 })
  for (let i = 0; i < 5; i++) fullS.update(hissFeatures(bandNoise(2500, 11000, { seed: i + 1 }), RATE))
  assert.equal(fullS.ok, true, 'a full-band mic hears above 9 kHz')

  const cut = createMicCheck({ frames: 5 })
  for (let i = 0; i < 4; i++) cut.update(hissFeatures(bandNoise(2500, 7800, { seed: i + 1 }), RATE))
  assert.equal(cut.decided, false)
  cut.update(hissFeatures(bandNoise(2500, 7800, { seed: 9 }), RATE))
  assert.equal(cut.decided, true)
  assert.equal(cut.ok, false, 'nothing above 8 kHz: the mic is band-limited')

  const vowels = createMicCheck({ frames: 2 })
  vowels.update(hissFeatures(vowel(), RATE))
  vowels.update(hissFeatures(vowel(), RATE))
  assert.equal(vowels.decided, false, 'only hisses decide')
})
