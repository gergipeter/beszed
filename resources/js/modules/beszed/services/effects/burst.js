/**
 * A little burst of stars from a point (the child's finger on a right answer).
 * Like confetti: plain elements animated with the Web Animations API on
 * transform/opacity, so the compositor runs it. Bigger for a streak.
 */
const COLORS = ['#FFD84D', '#FF9EC7', '#9EE6C9', '#B9A6FF', '#7CC7FF', '#FFB8A8']

const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

/** @param {{ x: number, y: number } | null} point  viewport px */
export function burst(point, { pieces = 10, reach = 70 } = {}) {
  if (!point || reducedMotion() || !Element.prototype.animate) return
  const layer = document.createElement('div')
  layer.className = 'bz-burst'
  layer.setAttribute('aria-hidden', 'true')
  layer.style.left = `${point.x}px`
  layer.style.top = `${point.y}px`
  document.body.appendChild(layer)

  const flights = []
  for (let i = 0; i < pieces; i++) {
    const star = document.createElement('i')
    star.textContent = i % 3 ? '✦' : '★'
    star.style.color = COLORS[i % COLORS.length]
    layer.appendChild(star)
    const angle = (i / pieces) * Math.PI * 2 + Math.random() * 0.5
    const distance = reach * (0.6 + Math.random() * 0.6)
    const x = Math.cos(angle) * distance
    const y = Math.sin(angle) * distance
    flights.push(
      star.animate(
        [
          { transform: 'translate(-50%, -50%) scale(0.3) rotate(0deg)', opacity: 1 },
          { transform: `translate(calc(-50% + ${x.toFixed(0)}px), calc(-50% + ${y.toFixed(0)}px)) scale(1) rotate(${(Math.random() * 180).toFixed(0)}deg)`, opacity: 1, offset: 0.7 },
          { transform: `translate(calc(-50% + ${(x * 1.15).toFixed(0)}px), calc(-50% + ${(y * 1.15 + 18).toFixed(0)}px)) scale(0.6)`, opacity: 0 },
        ],
        { duration: 650 + Math.random() * 250, easing: 'cubic-bezier(0.2, 0.8, 0.3, 1)' },
      ),
    )
  }
  Promise.all(flights.map(f => f.finished.catch(() => {}))).then(() => layer.remove())
}
