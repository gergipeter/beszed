import { pluck } from '../audio/sfx'

/**
 * "Everything answers a touch": every button, link or picture the child
 * presses plays a soft note, stepping up a pentatonic scale while they keep
 * tapping (random taps still sound like a little tune), and gives a tiny buzz
 * on phones that can. One listener on the module's root (BeszedLayout), so no
 * component has to remember it. Opt out with data-no-feel (drums and pads that
 * play their own sounds).
 */

/** C major pentatonic, two octaves up from C5: no two notes clash. */
const SCALE = [523.25, 587.33, 659.25, 783.99, 880, 1046.5, 1174.66, 1318.5, 1567.98, 1760]
const RUN_GAP_MS = 1400

let step = -1
let lastAt = 0
let lastPoint = null

/** A short vibration where the device has one (Android; iOS ignores it). */
export function buzz(pattern) {
  try {
    navigator.vibrate?.(pattern)
  } catch {
    /* not allowed here */
  }
}

/** Where the child last touched (viewport px), for bursts that start at their finger. */
export const lastTouch = () => lastPoint

function onPointerDown(event) {
  lastPoint = { x: event.clientX, y: event.clientY }
  const target = event.target.closest?.('button, a, [role="button"], [data-feel]')
  if (!target || target.disabled || target.closest('[data-no-feel]')) return
  const now = performance.now()
  step = now - lastAt < RUN_GAP_MS ? (step + 1) % SCALE.length : 0
  lastAt = now
  pluck(SCALE[step])
  buzz(6)
}

/** Installs the touch feel on `root`; returns the uninstall. */
export function installTouchFeel(root) {
  root.addEventListener('pointerdown', onPointerDown, { passive: true })
  return () => root.removeEventListener('pointerdown', onPointerDown)
}
