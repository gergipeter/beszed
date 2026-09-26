/**
 * Where each accessory sits on Csillám, in the avatar's SVG units (viewBox 200×222).
 * Ids, slots and unlock levels come from config/beszed.php → rewards.accessories.
 * One item per slot can be worn at once (head, face, extra), so several can show together.
 */
export const ACCESSORY_ART = {
  bow: { char: '🎀', x: 58, y: 56, size: 36, rotate: -20 },
  glasses: { char: '🕶️', x: 100, y: 94, size: 58, rotate: 0 },
  flower: { char: '🌸', x: 146, y: 64, size: 32, rotate: 14 },
  hat: { char: '🎩', x: 140, y: 38, size: 46, rotate: 18 },
  crown: { char: '👑', x: 100, y: 46, size: 46, rotate: 0 },
}
