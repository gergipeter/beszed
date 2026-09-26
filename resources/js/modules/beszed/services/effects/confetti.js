/**
 * A burst of confetti over the whole screen. Each piece is one element animated
 * with the Web Animations API using concrete transform/opacity values, which the
 * browser runs on the compositor: smooth on phones, no JavaScript per frame.
 * (CSS keyframes with custom properties would fall back to the main thread.)
 * Styles: styles/base.css, .bz-confetti. Cleans itself up.
 */
const COLORS = ['#FFD84D', '#FF6F61', '#9EE6C9', '#B9A6FF', '#FF9EC7', '#7CC7FF', '#FFB8A8', '#2F9E5B']

const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

const between = (min, max) => min + Math.random() * (max - min)

/** @param {{ pieces?: number }} [options] */
export function confetti({ pieces = 60 } = {}) {
  if (typeof document === 'undefined' || reducedMotion() || !Element.prototype.animate) return

  const layer = document.createElement('div')
  layer.className = 'bz-confetti'
  layer.setAttribute('aria-hidden', 'true')
  document.body.appendChild(layer)

  const running = []
  for (let i = 0; i < pieces; i++) {
    const piece = document.createElement('i')
    const size = between(7, 13)
    const round = Math.random() < 0.3
    piece.style.left = `${between(0, 100).toFixed(1)}%`
    piece.style.width = `${size.toFixed(1)}px`
    piece.style.height = `${(round ? size : size * 0.45).toFixed(1)}px`
    piece.style.borderRadius = round ? '50%' : '2px'
    piece.style.background = COLORS[i % COLORS.length]
    layer.appendChild(piece)

    const dx = between(-18, 18).toFixed(1)
    const spin = between(-720, 720).toFixed(0)
    running.push(
      piece.animate(
        [
          { transform: 'translate3d(0, 0, 0) rotate(0deg)', opacity: 1 },
          { opacity: 1, offset: 0.85 },
          { transform: `translate3d(${dx}vw, 108vh, 0) rotate(${spin}deg)`, opacity: 0 },
        ],
        {
          duration: between(1800, 3200),
          delay: between(0, 450),
          easing: 'cubic-bezier(0.25, 0.55, 0.45, 1)',
          fill: 'both',
        },
      ).finished,
    )
  }

  Promise.allSettled(running).then(() => layer.remove())
}
