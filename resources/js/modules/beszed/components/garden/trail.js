/**
 * Geometry of a garden zone: the game stops along a winding trail (rows go
 * left → right, then right → left, like a board game), the smooth trail
 * through them, and free spots between the stops where the child's plants grow.
 * Plain functions of the zone's width, so the layout needs no measuring
 * beyond that.
 */

/** Space above the first row and below the last. */
const TOP = 36
const BOTTOM = 40
/** Up and down, so the trail looks walked rather than ruled. */
const WOBBLE = [0, -14, 10, -6]

export function columnsFor(width) {
  return width < 520 ? 2 : width < 760 ? 3 : 4
}

/** @returns {{ points: {x: number, y: number}[], height: number, rowHeight: number, cols: number }} */
/** `flip`: the first row runs right → left (the zone before it ended on the right, so the road carries on from there). */
export function trailLayout(count, width, flip = false) {
  const cols = columnsFor(width)
  const rowHeight = width < 520 ? 150 : 168
  const rows = Math.max(1, Math.ceil(count / cols))
  const points = Array.from({ length: count }, (_, i) => {
    const row = Math.floor(i / cols)
    const col = (row + Number(flip)) % 2 ? cols - 1 - (i % cols) : i % cols
    return { x: ((col + 0.5) / cols) * width, y: TOP + row * rowHeight + rowHeight / 2 + WOBBLE[i % WOBBLE.length] }
  })
  return { points, height: TOP + rows * rowHeight + BOTTOM, rowHeight, cols }
}

/** SVG path through the points (Catmull-Rom as cubic Béziers), dropping in straight above the first stop and leaving straight below the last. */
export function trailPath(points, height) {
  if (!points.length) return ''
  const last = points[points.length - 1]
  const all = [{ x: points[0].x, y: 0 }, ...points, { x: last.x, y: height }]
  let d = `M ${all[0].x.toFixed(1)} ${all[0].y.toFixed(1)}`
  for (let i = 0; i < all.length - 1; i++) {
    const [p0, p1, p2, p3] = [all[i - 1] ?? all[i], all[i], all[i + 1], all[i + 2] ?? all[i + 1]]
    const c1 = [p1.x + (p2.x - p0.x) / 6, p1.y + (p2.y - p0.y) / 6]
    const c2 = [p2.x - (p3.x - p1.x) / 6, p2.y - (p3.y - p1.y) / 6]
    d += ` C ${c1.map(n => n.toFixed(1)).join(' ')}, ${c2.map(n => n.toFixed(1)).join(' ')}, ${p2.x.toFixed(1)} ${p2.y.toFixed(1)}`
  }
  return d
}

/**
 * Where plants can grow: along both edges and between the stops of each row,
 * never on top of a stop or its sign. Always the same spots for the same layout,
 * so a plant stays where it grew.
 */
export function plantSpots({ points, rowHeight, cols }, width) {
  const rows = Math.ceil(points.length / cols)
  const spots = []
  for (let row = 0; row < rows; row++) {
    const mid = TOP + row * rowHeight + rowHeight / 2
    spots.push({ x: width * 0.05, y: mid - 38 }, { x: width * 0.95, y: mid + 30 })
    for (let c = 1; c < cols; c++) spots.push({ x: (c / cols) * width, y: mid + (c % 2 ? 44 : -46) })
    spots.push({ x: width * 0.06, y: mid + 44 }, { x: width * 0.94, y: mid - 42 })
  }
  // keep clear of the stones (≈ 90px) and their signs below them
  return spots.filter(s => points.every(p => Math.abs(s.x - p.x) > 62 || s.y < p.y - 62 || s.y > p.y + 92))
}
