/**
 * Shared bits of the grid engine (Labirintus, Kis robot). Cells are numbered row by row:
 * index = row × cols + col. A maze cell's walls are a bitmask, a set bit = a wall on that side
 * (LabirintusRounds: N 1, E 2, S 4, W 8).
 */
export const SIDES = Object.freeze({
  up: { id: 'up', bit: 1, dx: 0, dy: -1 },
  right: { id: 'right', bit: 2, dx: 1, dy: 0 },
  down: { id: 'down', bit: 4, dx: 0, dy: 1 },
  left: { id: 'left', bit: 8, dx: -1, dy: 0 },
})

export const xy = (cell, cols) => ({ x: cell % cols, y: Math.floor(cell / cols) })

/** The cell next to `cell` on `side`, or null past the edge. */
export function neighbour(cell, side, cols, rows) {
  const { x, y } = xy(cell, cols)
  const nx = x + side.dx
  const ny = y + side.dy
  return nx < 0 || ny < 0 || nx >= cols || ny >= rows ? null : ny * cols + nx
}

/** Position of a piece one cell big: CSS custom properties read by .piece in the boards. */
export const place = (cell, cols) => {
  const { x, y } = xy(cell, cols)
  return { '--x': x, '--y': y }
}

/** The server's grade band [smooth, ok] → tries 1–3. */
export const gradeOf = (mistakes, [smooth, ok]) => (mistakes <= smooth ? 1 : mistakes <= ok ? 2 : 3)

/** "M x y L …" through the centres of the cells (viewBox: one unit per cell). */
export const centres = (cells, cols) =>
  cells.map((c, i) => `${i ? 'L' : 'M'}${(c % cols) + 0.5} ${Math.floor(c / cols) + 0.5}`).join(' ')
