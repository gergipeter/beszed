/**
 * "Hol van?" scene layouts, keyed by relation (see HolRounds::RELATIONS on the server).
 * Each item: [emoji, x %, y %, size (of 150), z-index]; HIDDEN = the object being looked for.
 */
export const HIDDEN = Symbol('hidden')
export const BOX = '📦'

export const SCENES = {
  folott: [[BOX, 50, 70, 56, 1], [HIDDEN, 50, 24, 44, 2]],
  alatt: [[BOX, 50, 30, 56, 1], [HIDDEN, 50, 78, 44, 2]],
  jobb: [[BOX, 34, 55, 56, 1], [HIDDEN, 76, 55, 44, 2]],
  bal: [[BOX, 66, 55, 56, 1], [HIDDEN, 24, 55, 44, 2]],
  mogott: [[HIDDEN, 60, 40, 44, 1], [BOX, 50, 60, 70, 2]],
  elott: [[BOX, 50, 42, 60, 1], [HIDDEN, 46, 68, 50, 2]],
  kozott: [[BOX, 20, 58, 46, 1], [HIDDEN, 50, 58, 42, 2], [BOX, 80, 58, 46, 1]],
}
