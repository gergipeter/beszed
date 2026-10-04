// Szájtorna's automatic counting: face scores → poses → repetitions.
import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createBaseline, createRepCounter, levelsFor, toScores } from '../../resources/js/modules/beszed/engines/mimic/poses.js'

const DT = 33
/** Feeds `ms` of the same face to the counter; returns how many repetitions finished. */
function hold(counter, scores, ms) {
  let reps = 0
  for (let t = 0; t < ms; t += DT) if (counter.update(scores, DT).rep) reps++
  return reps
}
const REST = { mouthPucker: 0.05, jawOpen: 0.05, mouthSmileLeft: 0.1, mouthSmileRight: 0.1, cheekPuff: 0.02 }
const PUCKER = { ...REST, mouthPucker: 0.9 }
const SMILE = { ...REST, mouthSmileLeft: 0.8, mouthSmileRight: 0.75 }
const OPEN = { ...REST, jawOpen: 0.7 }

test('toScores reads MediaPipe blendshapes, null without a face', () => {
  const scores = toScores({ faceBlendshapes: [{ categories: [{ categoryName: 'jawOpen', score: 0.4 }] }] })
  assert.equal(scores.jawOpen, 0.4)
  assert.equal(toScores({ faceBlendshapes: [] }), null)
  assert.equal(toScores(null), null)
})

test('pucker then smile is one repetition, in either order', () => {
  const counter = createRepCounter([{ pose: 'pucker' }, { pose: 'smile' }])
  assert.equal(hold(counter, PUCKER, 400), 0)
  assert.equal(hold(counter, REST, 200), 0)
  assert.equal(hold(counter, SMILE, 400), 1)
  hold(counter, REST, 200)
  hold(counter, SMILE, 400)
  hold(counter, REST, 200)
  assert.equal(hold(counter, PUCKER, 400), 1)
  assert.equal(counter.reps, 2)
})

test('a flicker shorter than the hold time does not count', () => {
  const counter = createRepCounter([{ pose: 'open' }])
  assert.equal(hold(counter, OPEN, 150), 0)
  hold(counter, REST, 100)
  assert.equal(hold(counter, OPEN, 400), 1)
})

test('holding a pose counts it once; it must be let go to count again', () => {
  const counter = createRepCounter([{ pose: 'pucker' }, { pose: 'pucker' }, { pose: 'open' }])
  hold(counter, PUCKER, 2000)
  assert.equal(counter.progress, 1 / 3)
  hold(counter, REST, 200)
  hold(counter, PUCKER, 400)
  assert.equal(counter.progress, 2 / 3)
  assert.equal(hold(counter, OPEN, 400), 1)
})

test('moves without a pose are not waited for; no watched move means no counting', () => {
  const counter = createRepCounter([{ pose: 'puff' }, { pose: null }])
  assert.equal(hold(counter, { ...REST, cheekPuff: 0.5 }, 400), 1)
  const none = createRepCounter([{ pose: null }, { pose: 'unknown' }])
  assert.equal(none.watched, false)
  assert.equal(hold(none, PUCKER, 400), 0)
})

test('the child\'s resting face raises the bar', () => {
  const baseline = createBaseline(['open'])
  hold({ update: (s, dt) => (baseline.update(s, dt), {}) }, { ...REST, jawOpen: 0.3 }, 1000)
  assert.ok(baseline.ready)
  assert.equal(baseline.values.open, 0.3)
  assert.ok(Math.abs(levelsFor('open', 0.3).on - 0.55) < 1e-9)
  assert.equal(levelsFor('open', 0.8).on, 0.85, 'never out of reach')

  const counter = createRepCounter([{ pose: 'open' }], { base: baseline.values })
  assert.equal(hold(counter, { ...REST, jawOpen: 0.45 }, 400), 0)
  assert.equal(hold(counter, { ...REST, jawOpen: 0.7 }, 400), 1)
})

test('no face: nothing happens', () => {
  const counter = createRepCounter([{ pose: 'smile' }])
  assert.deepEqual(counter.update(null, DT), { rep: false, active: null, strength: 0 })
})
