import { buzz } from './feel'

/**
 * Press and hold a picture to see it big: after HOLD_MS without moving, the
 * picture opens large in the middle of the screen until the finger lifts.
 * Small phones make pictograms tiny; this is the "look closer" gesture. The
 * tap it would have been is swallowed, so looking never counts as an answer.
 * Pictures opt in with data-peek (OptionTile, PictureCard, the game pieces);
 * never a shadow (Árnyékkereső) or a hidden card, which would give it away.
 * One listener on the module's root (BeszedLayout), like the touch feel.
 */
const HOLD_MS = 420
const SLOP = 10

let timer = 0
let start = null
let layer = null

function show(el) {
  // a composed picture (the puzzle's scene + figure) whole, else the picture itself
  const art = (el.querySelector('[data-peek-art]') ?? el.querySelector('.emoji'))?.cloneNode(true)
  if (!art) return
  layer = document.createElement('div')
  layer.className = 'bz-peek'
  layer.setAttribute('aria-hidden', 'true')
  const card = document.createElement('div')
  card.className = 'bz-peek-card'
  card.appendChild(art)
  layer.appendChild(card)
  document.body.appendChild(layer)
  buzz(12)
}

function hide() {
  layer?.remove()
  layer = null
}

/** The click that follows the lifted finger must not answer. */
function swallowClick() {
  const stop = e => {
    e.stopPropagation()
    e.preventDefault()
  }
  window.addEventListener('click', stop, { capture: true, once: true })
  setTimeout(() => window.removeEventListener('click', stop, { capture: true }), 0)
}

function down(event) {
  const el = event.target.closest?.('[data-peek]')
  if (!el || el.disabled || event.button > 0) return
  start = { x: event.clientX, y: event.clientY }
  clearTimeout(timer)
  timer = setTimeout(() => show(el), HOLD_MS)
}
function move(event) {
  if (!start || layer) return
  if (Math.hypot(event.clientX - start.x, event.clientY - start.y) > SLOP) {
    clearTimeout(timer) // a drag or a scroll, not a hold
    start = null
  }
}
function up() {
  clearTimeout(timer)
  start = null
  if (layer) {
    hide()
    swallowClick()
  }
}
/** Android's long-press menu and iOS's image menu would cover the peek. */
function noMenu(event) {
  if (event.target.closest?.('[data-peek], button, img')) event.preventDefault()
}

/** Installs press-and-hold on `root`; returns the uninstall. */
export function installPeek(root) {
  root.addEventListener('pointerdown', down, { passive: true })
  window.addEventListener('pointermove', move, { passive: true })
  window.addEventListener('pointerup', up, { passive: true })
  window.addEventListener('pointercancel', up, { passive: true })
  root.addEventListener('contextmenu', noMenu)
  return () => {
    root.removeEventListener('pointerdown', down)
    window.removeEventListener('pointermove', move)
    window.removeEventListener('pointerup', up)
    window.removeEventListener('pointercancel', up)
    root.removeEventListener('contextmenu', noMenu)
    hide()
  }
}
