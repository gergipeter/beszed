/**
 * Színező: line pictures to colour, drawn in a 300 × 300 square (x right, y down). Each picture is a list of
 * named regions in painting order (a later region covers an earlier one); a region is one tap target and takes
 * one colour, even when it is made of several shapes (all the dots of a ladybird). Per region:
 *   d      one path, or a list of paths drawn one over the other (each with its own outline)
 *   lines  details drawn over it in the outline colour (window panes, a smile); never tappable
 *   ink    small shapes filled with the outline colour (eyes)
 * A picture may add `lines` / `ink` drawn over everything.
 *
 * The region ids must match SzinezoRounds::PICTURES on the server (tests/Feature/Games/SzinezoTest.php
 * compares them). Ids ending in _bal / _jobb are the left / right one of a pair (spoken from level 3).
 */

const n1 = n => Math.round(n * 10) / 10
const pt = ([x, y]) => `${n1(x)} ${n1(y)}`
const polar = (cx, cy, r, deg) => [cx + r * Math.cos((deg * Math.PI) / 180), cy + r * Math.sin((deg * Math.PI) / 180)]

const circle = (cx, cy, r) => `M${n1(cx - r)} ${n1(cy)}a${r} ${r} 0 1 0 ${n1(2 * r)} 0a${r} ${r} 0 1 0 ${n1(-2 * r)} 0Z`
const ellipse = (cx, cy, rx, ry) => `M${n1(cx - rx)} ${n1(cy)}a${rx} ${ry} 0 1 0 ${n1(2 * rx)} 0a${rx} ${ry} 0 1 0 ${n1(-2 * rx)} 0Z`
const poly = pts => `M${pts.map(pt).join('L')}Z`

function rect(x, y, w, h, r = 0) {
  if (!r) return `M${x} ${y}h${w}v${h}h${-w}Z`
  const c = (dx, dy) => `a${r} ${r} 0 0 1 ${dx} ${dy}`
  return `M${x + r} ${y}h${w - 2 * r}${c(r, r)}v${h - 2 * r}${c(-r, r)}h${2 * r - w}${c(-r, -r)}v${2 * r - h}${c(r, -r)}Z`
}

/** A star with `n` points; R outer, r inner radius. */
const star = (cx, cy, R, r, n = 5) =>
  poly(Array.from({ length: 2 * n }, (_, i) => polar(cx, cy, i % 2 ? r : R, -90 + (i * 180) / n)))

/** Sun rays: n tapered spokes between radius r1 and r2. */
function rays(cx, cy, r1, r2, n = 8, w = 7) {
  return Array.from({ length: n }, (_, i) => {
    const a = (i * 360) / n
    const d1 = ((w / 2 / r1) * 180) / Math.PI
    const d2 = ((w * 0.3) / r2) * (180 / Math.PI)
    return poly([polar(cx, cy, r1, a - d1), polar(cx, cy, r2, a - d2), polar(cx, cy, r2, a + d2), polar(cx, cy, r1, a + d1)])
  }).join('')
}

/** A round outline of n bumps (a tree's crown, a flower's petals); bulge 0.5 = half-circle bumps. */
function scallop(cx, cy, r, n, bulge = 0.62, rot = -90) {
  const p = Array.from({ length: n }, (_, i) => polar(cx, cy, r, rot + (i * 360) / n))
  return `M${pt(p[0])}${p
    .map((a, i) => {
      const b = p[(i + 1) % n]
      const R = n1(Math.hypot(b[0] - a[0], b[1] - a[1]) * bulge)
      return `A${R} ${R} 0 0 1 ${pt(b)}`
    })
    .join('')}Z`
}

/** A cloud sitting on a flat bottom from (x, y), w wide, h tall. */
function cloud(x, y, w, h) {
  const p = [[x, y], [x + 0.08 * w, y - 0.55 * h], [x + 0.4 * w, y - h], [x + 0.78 * w, y - 0.7 * h], [x + w, y]]
  return `M${pt(p[0])}${p
    .slice(1)
    .map((b, i) => {
      const a = p[i]
      const R = n1(Math.hypot(b[0] - a[0], b[1] - a[1]) * 0.6)
      return `A${R} ${R} 0 0 1 ${pt(b)}`
    })
    .join('')}Q${n1(x + w / 2)} ${n1(y + 6)} ${pt(p[0])}Z`
}

/** A smiling face for a sun (eyes as ink, smile as a line). */
const faceInk = (cx, cy, s) => circle(cx - 0.32 * s, cy - 0.14 * s, 0.12 * s) + circle(cx + 0.32 * s, cy - 0.14 * s, 0.12 * s)
const smile = (cx, cy, s) => `M${n1(cx - 0.36 * s)} ${n1(cy + 0.16 * s)}Q${n1(cx)} ${n1(cy + 0.52 * s)} ${n1(cx + 0.36 * s)} ${n1(cy + 0.16 * s)}`

/** A smiling sun with rays: [d, lines, ink] for R('nap', ...sun(…)). */
const sun = (cx, cy, r) => [circle(cx, cy, r) + rays(cx, cy, r + 6, r + 17, 8, 7), smile(cx, cy, r), faceInk(cx, cy, r)]

function R(id, d, lines = '', ink = '') {
  return { id, paths: Array.isArray(d) ? d : [d], lines, ink }
}

export const PICTURES = {
  haz: {
    regions: [
      R('nap', ...sun(54, 56, 22)),
      R('felho', cloud(214, 66, 70, 32)),
      R('fu', 'M14 254Q80 238 150 248Q220 258 286 244V272Q286 286 272 286H28Q14 286 14 272Z', 'M38 272l5 -11l5 11M244 270l5 -11l5 11M120 276l4 -9l4 9'),
      R('kemeny', [rect(182, 72, 22, 52), rect(177, 64, 32, 12, 3)]),
      R('teto', poly([[52, 148], [146, 64], [240, 148]]), circle(146, 118, 11) + 'M146 107V129M135 118H157'),
      R('fal', rect(70, 146, 152, 108)),
      R('ajto', 'M128 254V210a18 18 0 0 1 36 0V254Z', '', circle(156, 233, 3.2)),
      R('ablak_bal', rect(82, 168, 36, 36, 4), 'M100 168V204M82 186H118'),
      R('ablak_jobb', rect(176, 168, 36, 36, 4), 'M194 168V204M176 186H212'),
    ],
  },

  hal: {
    regions: [
      R('hinar_bal', 'M40 268C26 232 56 214 40 182C30 160 44 140 52 126C64 146 54 166 62 184C74 212 54 236 64 268Z', 'M52 140Q58 200 52 262'),
      R('hinar_jobb', 'M260 268C274 232 244 214 260 182C270 160 256 140 248 126C236 146 246 166 238 184C226 212 246 236 236 268Z', 'M248 140Q242 200 248 262'),
      R('kovek', ellipse(98, 270, 26, 13) + ellipse(150, 274, 18, 10) + ellipse(204, 270, 28, 14)),
      R('farok', 'M196 132L252 90Q236 132 252 174Z', 'M214 120L240 104M214 144L240 160'),
      R('uszony', 'M102 110Q122 46 178 108Z' + 'M116 160Q124 202 158 164Z'),
      R('hal', 'M66 132C92 84 176 80 204 132C176 182 92 180 66 132Z', 'M100 106Q116 132 100 158M72 142Q80 147 88 142' + circle(86, 120, 8), circle(87, 121, 4)),
      R('pottyok', circle(130, 126, 9) + circle(154, 108, 7) + circle(160, 146, 10) + circle(184, 126, 7)),
      R('buborekok', circle(50, 98, 9) + circle(36, 70, 7) + circle(52, 42, 11)),
    ],
    lines: 'M110 36q8 -7 16 0t16 0M196 50q8 -7 16 0t16 0',
  },

  auto: {
    regions: [
      R('nap', ...sun(52, 52, 20)),
      R('felho', cloud(198, 74, 80, 34)),
      R('ut', rect(14, 240, 272, 34, 12), 'M36 257h22M84 257h22M132 257h22M180 257h22M228 257h22'),
      R(
        'auto',
        'M46 226Q36 226 36 214V186Q36 164 60 162L92 160L116 116Q120 108 130 108H194Q204 108 208 116L234 160L248 162Q264 164 264 184V214Q264 226 252 226Z',
        'M151 162V222M164 178h14M36 196h10' + ellipse(255, 184, 5, 8),
      ),
      R('ablakok', 'M100 158L122 120H146V158Z' + 'M156 158V120H196L220 158Z'),
      R('kerek_bal', circle(92, 228, 24), circle(92, 228, 9)),
      R('kerek_jobb', circle(208, 228, 24), circle(208, 228, 9)),
    ],
  },

  fa: {
    regions: [
      R('nap', ...sun(260, 44, 18)),
      R('fu', 'M14 254Q80 238 150 248Q220 258 286 244V272Q286 286 272 286H28Q14 286 14 272Z', 'M34 272l5 -11l5 11M150 276l4 -9l4 9'),
      R('torzs', 'M124 254Q140 236 138 196L138 150H162L162 196Q160 236 178 254Z', 'M146 206q5 8 0 16M156 176q-4 6 0 12'),
      R('lomb', scallop(150, 108, 74, 11)),
      R('alma_bal', circle(112, 90, 17), 'M112 73q1 -8 7 -11'),
      R('alma_jobb', circle(186, 128, 17), 'M186 111q1 -8 7 -11'),
      R('kosar', 'M196 236H264L255 270Q230 278 205 270Z', 'M206 236Q230 204 254 236M203 248H257M207 260H253'),
    ],
  },

  hajo: {
    regions: [
      R('nap', ...sun(52, 52, 20)),
      R('felho', cloud(204, 84, 78, 32)),
      R('viz', 'M14 214q17 -12 34 0t34 0t34 0t34 0t34 0t34 0t34 0t34 0V272Q286 286 272 286H28Q14 286 14 272Z', 'M40 252q10 -7 20 0M200 262q10 -7 20 0M120 270q10 -7 20 0'),
      R('vitorla_bal', 'M144 60Q112 120 74 190H144Z'),
      R('vitorla_jobb', 'M156 74Q204 130 226 190H156Z'),
      R('zaszlo', 'M153 32L194 44L153 56Z'),
      R('hajo', 'M56 194H244L222 238Q150 248 78 238Z', circle(110, 214, 7) + circle(150, 216, 7) + circle(190, 214, 7)),
    ],
    // the mast
    ink: rect(147, 32, 6, 164, 2),
  },

  sarkany: {
    regions: [
      R('nap', ...sun(256, 46, 18)),
      R('felho_bal', cloud(16, 152, 76, 32)),
      R('felho_jobb', cloud(206, 130, 76, 32)),
      R('domb', 'M14 268Q150 190 286 268V272Q286 286 272 286H28Q14 286 14 272Z', 'M60 262l5 -11l5 11M224 262l5 -11l5 11'),
      R('sarkany', poly([[150, 30], [202, 104], [150, 190], [98, 104]]), 'M150 30V190M98 104H202M150 190C130 210 176 222 160 240C146 256 186 262 196 276'),
      R(
        'masnik',
        [[153, 216], [160, 240], [175, 262]].map(([x, y]) => `M${x} ${y}L${x - 15} ${y - 10}V${y + 10}Z` + `M${x} ${y}L${x + 15} ${y - 10}V${y + 10}Z`).join(''),
        '',
        circle(153, 216, 3.5) + circle(160, 240, 3.5) + circle(175, 262, 3.5),
      ),
    ],
    // the string down to the hill
    lines: 'M150 112Q92 170 62 254',
  },

  raketa: {
    regions: [
      R('hold', 'M236 30A32 32 0 0 0 236 94A38 38 0 0 1 236 30Z'),
      R('csillag_bal', star(62, 72, 27, 12)),
      R('csillag_jobb', star(250, 172, 24, 10)),
      R('lang', 'M126 212Q128 252 150 284Q172 252 174 212Z', 'M140 218Q150 252 160 218'),
      R('szarnyak', 'M120 166L82 218V238L120 214Z' + 'M180 166L218 218V238L180 214Z'),
      R('raketa', 'M118 100H182V206Q182 218 170 218H130Q118 218 118 206Z', 'M118 190H182'),
      R('orr', 'M118 102Q120 62 150 30Q180 62 182 102Z'),
      R('ablak', circle(150, 146, 19), circle(150, 146, 12)),
    ],
    ink: circle(36, 184, 3) + circle(270, 252, 3) + circle(42, 248, 2.5) + circle(108, 40, 2.5) + circle(200, 30, 2.5) + circle(254, 120, 2),
  },

  pillango: {
    regions: [
      R('fu', 'M14 258Q150 240 286 258V272Q286 286 272 286H28Q14 286 14 272Z', 'M52 252V232M248 252V232M52 246q-12 -4 -16 -14q12 0 16 10M248 246q12 -4 16 -14q-12 0 -16 10'),
      R('virag_bal', scallop(52, 210, 22, 6, 0.5)),
      R('virag_jobb', scallop(248, 210, 22, 6, 0.5)),
      R('kozepek', circle(52, 210, 10) + circle(248, 210, 10)),
      R('szarnyak', [
        'M146 134C116 150 80 170 92 196C104 218 136 190 146 150Z',
        'M154 134C184 150 220 170 208 196C196 218 164 190 154 150Z',
        'M146 120C110 60 50 50 54 100C56 140 110 146 146 130Z',
        'M154 120C190 60 250 50 246 100C244 140 190 146 154 130Z',
      ]),
      R('pottyok', circle(92, 94, 12) + circle(208, 94, 12) + circle(122, 120, 6) + circle(178, 120, 6) + circle(110, 180, 8) + circle(190, 180, 8)),
      R(
        'test',
        [ellipse(150, 142, 10, 48), circle(150, 86, 13)],
        'M145 75Q138 56 126 50M155 75Q162 56 174 50M145 91q5 5 10 0',
        circle(126, 50, 4) + circle(174, 50, 4) + circle(145, 84, 2) + circle(155, 84, 2),
      ),
    ],
  },

  katica: {
    regions: [
      R('virag_bal', scallop(54, 58, 24, 6, 0.5)),
      R('virag_jobb', scallop(246, 58, 24, 6, 0.5)),
      R('kozepek', circle(54, 58, 10) + circle(246, 58, 10)),
      R(
        'level',
        'M22 236C60 166 240 166 278 236C240 292 60 292 22 236Z',
        'M30 236H270M80 236l-16 -18M220 236l16 -18M80 236l-14 18M220 236l14 18M104 150l-22 -8M100 176h-24M106 202l-20 12M196 150l22 -8M200 176h24M194 202l20 12',
      ),
      R(
        'fej',
        circle(150, 96, 30),
        'M138 72Q130 52 118 46M162 72Q170 52 182 46' + circle(140, 88, 6) + circle(160, 88, 6),
        circle(118, 46, 4) + circle(182, 46, 4) + circle(141, 89, 3) + circle(161, 89, 3),
      ),
      R('hat', circle(150, 168, 62), 'M150 106V230'),
      R('pottyok', circle(122, 140, 10) + circle(178, 140, 10) + circle(112, 178, 11) + circle(188, 178, 11) + circle(128, 210, 9) + circle(172, 210, 9)),
    ],
  },

  vonat: {
    regions: [
      R('fust', circle(66, 108, 12) + circle(88, 84, 16) + circle(118, 56, 20)),
      R('sin', rect(14, 248, 272, 14, 6), 'M40 262v12M80 262v12M120 262v12M160 262v12M200 262v12M240 262v12'),
      R('kemeny', 'M52 172L46 132H76L70 172Z'),
      R('kocsi', rect(160, 150, 116, 82, 10), 'M160 206H276'),
      R('mozdony', 'M44 232Q34 232 34 222V182Q34 170 46 170H100V128H152V232Z', 'M70 170V232M152 214H160'),
      R('teto', rect(94, 116, 64, 14, 5)),
      R('ablakok', rect(110, 140, 30, 30, 4) + rect(174, 164, 38, 30, 4) + rect(224, 164, 38, 30, 4)),
      R('kerekek', circle(62, 234, 18) + circle(124, 234, 18) + circle(190, 236, 16) + circle(246, 236, 16), 'M62 234H124', circle(62, 234, 4) + circle(124, 234, 4) + circle(190, 236, 4) + circle(246, 236, 4)),
    ],
  },

  gomba: {
    regions: [
      R('nap', ...sun(264, 42, 16)),
      R('fu', 'M14 256Q90 242 150 252Q220 262 286 248V272Q286 286 272 286H28Q14 286 14 272Z', 'M30 274l5 -11l5 11M180 278l4 -9l4 9'),
      R('tonk', 'M96 256Q102 214 100 172H152Q150 214 156 256Z', 'M118 222q8 7 16 0', circle(114, 206, 3.5) + circle(138, 206, 3.5)),
      R('kalap', 'M28 176Q28 72 126 66Q224 72 224 176Q126 194 28 176Z'),
      R('pottyok', ellipse(70, 144, 13, 10) + ellipse(106, 106, 12, 9) + ellipse(152, 98, 14, 10) + ellipse(186, 142, 12, 9) + ellipse(128, 150, 13, 9)),
      R('csiga', 'M200 254Q200 240 214 240H262Q264 226 274 220Q290 218 288 236Q286 254 266 256H206Q200 256 200 254Z', 'M274 222L268 202M282 221L288 203', circle(268, 200, 3.5) + circle(288, 201, 3.5) + circle(280, 232, 2.5)),
      R('csigahaz', circle(238, 224, 25), 'M238 224m0 -5a5 5 0 1 1 -5 5a10 10 0 1 1 10 10a15 15 0 1 1 -15 -15'),
    ],
  },

  hoember: {
    regions: [
      R('ho', 'M14 238Q80 226 150 234Q220 242 286 230V272Q286 286 272 286H28Q14 286 14 272Z'),
      R('fenyo_bal', [rect(42, 226, 12, 18), poly([[48, 152], [8, 230], [88, 230]]), poly([[48, 118], [14, 186], [82, 186]]), poly([[48, 90], [20, 146], [76, 146]])]),
      R('fenyo_jobb', [rect(246, 226, 12, 18), poly([[252, 152], [212, 230], [292, 230]]), poly([[252, 118], [218, 186], [286, 186]]), poly([[252, 90], [224, 146], [280, 146]])]),
      R(
        'hoember',
        [circle(150, 204, 48), circle(150, 132, 34), circle(150, 78, 28)],
        'M118 124L82 100M92 107L86 92M182 124L218 100M208 107L214 92',
        circle(140, 70, 3.5) + circle(160, 70, 3.5) + circle(136, 98, 2) + circle(143, 101, 2) + circle(150, 102, 2) + circle(157, 101, 2) + circle(164, 98, 2),
      ),
      R('orr', 'M152 79L198 87L152 94Z'),
      R('sal', ['M118 100Q150 116 182 100L186 112Q150 132 114 112Z', 'M164 118L170 152L186 148L178 114Z']),
      R('gombok', circle(150, 146, 7) + circle(150, 186, 7.5) + circle(150, 216, 7.5)),
      R('kalap', [rect(128, 14, 44, 38, 4), rect(114, 48, 72, 11, 4)], 'M128 40H172'),
    ],
    ink: circle(104, 26, 3) + circle(204, 36, 3) + circle(234, 18, 2.5) + circle(88, 46, 2.5) + circle(268, 62, 3) + circle(30, 58, 2.5),
  },

  bohoc: {
    regions: [
      R('haj', scallop(70, 140, 32, 7, 0.55) + scallop(230, 140, 32, 7, 0.55)),
      R(
        'arc',
        circle(150, 152, 72),
        'M110 116q14 -12 28 0M162 116q14 -12 28 0' + circle(124, 134, 10) + circle(176, 134, 10),
        circle(126, 136, 4.5) + circle(178, 136, 4.5),
      ),
      R('kalap', ['M150 20L108 94H192Z', circle(150, 20, 12)], 'M128 58H172'),
      R('orr', circle(150, 162, 18)),
      R('szaj', 'M104 188Q150 240 196 188Q150 208 104 188Z'),
      R('masni', ['M150 240L106 220V266Z' + 'M150 240L194 220V266Z', circle(150, 240, 10)]),
    ],
  },

  fagyi: {
    regions: [
      R('ostya', 'M176 96L198 40L212 46L190 102Z', 'M186 74l13 5M192 58l13 5'),
      R('tolcser', 'M106 148L150 276L194 148Z', 'M116 176H184M124 200H176M132 224H168M130 150L162 232M170 150L138 232'),
      R('gomboc', 'M102 120A48 48 0 0 1 198 120Q200 146 186 152Q176 160 166 150Q156 162 146 152Q136 162 126 152Q114 160 106 150Q98 142 102 120Z'),
      R('ontet', 'M104.8 104A48 48 0 0 1 195.2 104Q197 118 188 118Q182 134 174 120Q166 112 160 126Q152 138 146 122Q138 110 130 126Q122 134 118 118Q108 114 104.8 104Z'),
      R('cseresznye', circle(150, 64, 13), 'M150 51Q154 36 166 30'),
    ],
    ink: star(54, 70, 9, 4) + star(250, 96, 8, 3.5) + star(64, 220, 7, 3) + star(240, 230, 9, 4),
  },
}

/** The region ids of a picture, in painting order. */
export const regionIds = picture => (PICTURES[picture]?.regions ?? []).map(r => r.id)
