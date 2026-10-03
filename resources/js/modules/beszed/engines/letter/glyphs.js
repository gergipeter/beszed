/**
 * Print letters for finger tracing, as strokes in a unit square (x right, y down), in writing order:
 * each stroke is a list of [x, y] points. Capitals span y 0.12–0.88; small letters sit on the same baseline
 * (x-height from 0.4, tall ones up to 0.12, p down to 0.96).
 */

/** Points of an ellipse arc; angles in degrees, 0 = right, 90 = down (so growing angles run clockwise). */
function arc(cx, cy, rx, ry, from, to, steps = 28) {
  return Array.from({ length: steps + 1 }, (_, i) => {
    const a = ((from + ((to - from) * i) / steps) * Math.PI) / 180
    return [cx + rx * Math.cos(a), cy + ry * Math.sin(a)]
  })
}
const join = (...parts) => parts.flat(1)

/** A bowl that starts at the top and runs counter-clockwise all the way round. */
const ring = (cx, cy, rx, ry) => arc(cx, cy, rx, ry, -90, -450)

export const GLYPHS = {
  // straight capitals
  I: [[[0.5, 0.12], [0.5, 0.88]]],
  L: [[[0.3, 0.12], [0.3, 0.88], [0.7, 0.88]]],
  T: [[[0.2, 0.12], [0.8, 0.12]], [[0.5, 0.12], [0.5, 0.88]]],
  H: [[[0.3, 0.12], [0.3, 0.88]], [[0.7, 0.12], [0.7, 0.88]], [[0.3, 0.5], [0.7, 0.5]]],
  E: [[[0.7, 0.12], [0.3, 0.12], [0.3, 0.88], [0.7, 0.88]], [[0.3, 0.5], [0.62, 0.5]]],
  F: [[[0.7, 0.12], [0.3, 0.12], [0.3, 0.88]], [[0.3, 0.5], [0.62, 0.5]]],
  A: [[[0.5, 0.12], [0.25, 0.88]], [[0.5, 0.12], [0.75, 0.88]], [[0.36, 0.62], [0.64, 0.62]]],
  V: [[[0.25, 0.12], [0.5, 0.88], [0.75, 0.12]]],
  N: [[[0.3, 0.88], [0.3, 0.12], [0.7, 0.88], [0.7, 0.12]]],
  M: [[[0.22, 0.88], [0.22, 0.12], [0.5, 0.6], [0.78, 0.12], [0.78, 0.88]]],
  Z: [[[0.28, 0.12], [0.72, 0.12], [0.28, 0.88], [0.72, 0.88]]],
  K: [[[0.3, 0.12], [0.3, 0.88]], [[0.7, 0.12], [0.3, 0.55]], [[0.42, 0.43], [0.7, 0.88]]],
  // round capitals
  O: [ring(0.5, 0.5, 0.28, 0.38)],
  C: [arc(0.55, 0.5, 0.3, 0.38, -40, -320)],
  D: [[[0.3, 0.12], [0.3, 0.88]], arc(0.3, 0.5, 0.4, 0.38, -90, 90)],
  U: [join([[0.28, 0.12], [0.28, 0.6]], arc(0.5, 0.6, 0.22, 0.28, 180, 0), [[0.72, 0.6], [0.72, 0.12]])],
  P: [[[0.3, 0.88], [0.3, 0.12]], arc(0.3, 0.31, 0.4, 0.19, -90, 90)],
  B: [[[0.3, 0.12], [0.3, 0.88]], arc(0.3, 0.31, 0.38, 0.19, -90, 90), arc(0.3, 0.69, 0.42, 0.19, -90, 90)],
  R: [[[0.3, 0.12], [0.3, 0.88]], arc(0.3, 0.31, 0.4, 0.19, -90, 90), [[0.46, 0.5], [0.72, 0.88]]],
  S: [join(arc(0.5, 0.31, 0.22, 0.19, -30, -270), arc(0.5, 0.69, 0.24, 0.19, -90, 150))],
  G: [join(arc(0.55, 0.5, 0.3, 0.38, -40, -320), [[0.76, 0.52], [0.56, 0.52]])],
  J: [join([[0.62, 0.12], [0.62, 0.62]], arc(0.42, 0.62, 0.2, 0.26, 0, 180))],
  // small letters
  l: [[[0.5, 0.12], [0.5, 0.88]]],
  i: [[[0.5, 0.4], [0.5, 0.88]], [[0.5, 0.22], [0.5, 0.24]]],
  t: [[[0.45, 0.18], [0.45, 0.88]], [[0.3, 0.4], [0.64, 0.4]]],
  o: [ring(0.5, 0.64, 0.22, 0.24)],
  c: [arc(0.54, 0.64, 0.22, 0.24, -40, -320)],
  u: [join([[0.3, 0.4], [0.3, 0.7]], arc(0.5, 0.7, 0.2, 0.18, 180, 0)), [[0.7, 0.4], [0.7, 0.88]]],
  n: [[[0.3, 0.4], [0.3, 0.88]], join(arc(0.5, 0.6, 0.2, 0.2, 180, 360), [[0.7, 0.6], [0.7, 0.88]])],
  m: [
    [[0.22, 0.4], [0.22, 0.88]],
    join(arc(0.38, 0.58, 0.16, 0.18, 180, 360), [[0.54, 0.58], [0.54, 0.88]]),
    join(arc(0.7, 0.58, 0.16, 0.18, 180, 360), [[0.86, 0.58], [0.86, 0.88]]),
  ],
  a: [arc(0.48, 0.64, 0.2, 0.24, -30, -390), [[0.68, 0.4], [0.68, 0.88]]],
  d: [arc(0.46, 0.64, 0.2, 0.24, -30, -390), [[0.66, 0.12], [0.66, 0.88]]],
  b: [[[0.34, 0.12], [0.34, 0.88]], arc(0.54, 0.64, 0.2, 0.24, 180, 540)],
  p: [[[0.34, 0.4], [0.34, 0.96]], arc(0.54, 0.62, 0.2, 0.22, 180, 540)],
  e: [join([[0.3, 0.64], [0.7, 0.64]], arc(0.5, 0.64, 0.2, 0.24, 0, -320))],
  s: [join(arc(0.5, 0.52, 0.17, 0.12, -30, -270), arc(0.5, 0.76, 0.19, 0.12, -90, 150))],
}

// accents, drawn last: acute (á é í ó ú), umlaut (ö ü) and double acute (ő ű)
const acute = (x, y) => [[x - 0.04, y + 0.05], [x + 0.06, y - 0.05]]
const umlaut = (x, y) => [[[x - 0.08, y], [x - 0.08, y + 0.02]], [[x + 0.08, y], [x + 0.08, y + 0.02]]]
const doubleAcute = (x, y) => [acute(x - 0.07, y), acute(x + 0.07, y)]
const accented = (base, ...marks) => [...GLYPHS[base], ...marks]

Object.assign(GLYPHS, {
  á: accented('a', acute(0.56, 0.26)),
  é: accented('e', acute(0.5, 0.26)),
  í: [GLYPHS.i[0], acute(0.52, 0.24)],
  ó: accented('o', acute(0.5, 0.26)),
  ö: accented('o', ...umlaut(0.5, 0.28)),
  ő: accented('o', ...doubleAcute(0.5, 0.26)),
  ú: accented('u', acute(0.5, 0.26)),
  ü: accented('u', ...umlaut(0.5, 0.28)),
  ű: accented('u', ...doubleAcute(0.5, 0.26)),
  Á: accented('A', acute(0.5, 0.06)),
  É: accented('E', acute(0.5, 0.06)),
  Í: accented('I', acute(0.5, 0.06)),
  Ó: accented('O', acute(0.5, 0.06)),
  Ö: accented('O', ...umlaut(0.5, 0.07)),
  Ő: accented('O', ...doubleAcute(0.5, 0.06)),
  Ú: accented('U', acute(0.5, 0.06)),
  Ü: accented('U', ...umlaut(0.5, 0.07)),
  Ű: accented('U', ...doubleAcute(0.5, 0.06)),
})

/** Points every ~1.5% of the square along a stroke, so a quick finger still covers it. */
export function densify(stroke, gap = 0.015) {
  const out = [stroke[0]]
  for (let i = 1; i < stroke.length; i++) {
    const [x0, y0] = stroke[i - 1]
    const [x1, y1] = stroke[i]
    const n = Math.max(1, Math.round(Math.hypot(x1 - x0, y1 - y0) / gap))
    for (let k = 1; k <= n; k++) out.push([x0 + ((x1 - x0) * k) / n, y0 + ((y1 - y0) * k) / n])
  }
  return out
}

export const glyphStrokes = glyph => (GLYPHS[glyph] ?? GLYPHS.I).map(stroke => densify(stroke))
