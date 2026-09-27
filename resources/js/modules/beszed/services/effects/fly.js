/**
 * Csillám flies from her place to a game's stone before the game opens: a copy
 * of her drawing arcs across the screen and shrinks onto the stone (Web
 * Animations, transform/opacity only). Resolves when she has landed; right
 * away when motion is reduced or there's nothing to fly.
 */
const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

export function flyTo(from, to, { duration = 720 } = {}) {
  if (!from || !to || reducedMotion() || !Element.prototype.animate) return Promise.resolve()

  const a = from.getBoundingClientRect()
  const b = to.getBoundingClientRect()
  const dx = b.left + b.width / 2 - (a.left + a.width / 2)
  const dy = b.top + b.height / 2 - (a.top + a.height / 2)

  const ghost = from.cloneNode(true)
  ghost.setAttribute('aria-hidden', 'true')
  Object.assign(ghost.style, {
    position: 'fixed',
    left: `${a.left}px`,
    top: `${a.top}px`,
    width: `${a.width}px`,
    height: `${a.height}px`,
    margin: '0',
    zIndex: '1000',
    pointerEvents: 'none',
  })
  document.body.appendChild(ghost)

  const flight = ghost.animate(
    [
      { transform: 'translate(0, 0) scale(1) rotate(0deg)' },
      { transform: `translate(${dx * 0.5}px, ${dy * 0.5 - 110}px) scale(0.75) rotate(-10deg)`, offset: 0.55 },
      { transform: `translate(${dx}px, ${dy}px) scale(0.35) rotate(6deg)`, opacity: 0.2 },
    ],
    { duration, easing: 'cubic-bezier(0.45, 0.05, 0.35, 1)' },
  )
  return flight.finished.catch(() => {}).then(() => ghost.remove())
}
