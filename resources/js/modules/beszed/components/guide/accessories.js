/**
 * Where each accessory sits on Csillám, in the avatar's SVG units (viewBox 200×222).
 * Ids, slots and unlock levels come from config/beszed.php → rewards.accessories.
 * One item per slot can be worn at once (head, face, neck, extra, mane), so
 * several show together. `mane` items recolour her mane and tail instead of
 * adding a picture: four colours, in the order of the mane's circles.
 */
export const ACCESSORY_ART = {
  // head
  cap: { char: '🧢', x: 106, y: 42, size: 58, rotate: 10 },
  hat: { char: '🎩', x: 140, y: 38, size: 46, rotate: 18 },
  crown: { char: '👑', x: 100, y: 46, size: 46, rotate: 0 },
  sunhat: { char: '👒', x: 132, y: 40, size: 58, rotate: 16 },
  gradcap: { char: '🎓', x: 100, y: 34, size: 56, rotate: -6 },
  // face
  glasses: { char: '🕶️', x: 100, y: 94, size: 58, rotate: 0 },
  goggles: { char: '🥽', x: 100, y: 93, size: 62, rotate: 0 },
  specs: { char: '👓', x: 100, y: 95, size: 56, rotate: 0 },
  // neck
  scarf: { char: '🧣', x: 100, y: 150, size: 60, rotate: -4 },
  medal: { char: '🏅', x: 100, y: 166, size: 38, rotate: 0 },
  ribbon: { char: '🎗️', x: 124, y: 160, size: 34, rotate: 10 },
  // extra
  bow: { char: '🎀', x: 58, y: 56, size: 36, rotate: -20 },
  flower: { char: '🌸', x: 146, y: 64, size: 32, rotate: 14 },
  balloon: { char: '🎈', x: 178, y: 104, size: 46, rotate: 12 },
  wand: { char: '🪄', x: 160, y: 174, size: 42, rotate: -24 },
  butterfly: { char: '🦋', x: 152, y: 18, size: 32, rotate: 14 },
  // mane colours
  mane_candy: { palette: ['#FF8FC4', '#FFC2DE', '#FF6FAF', '#FFD9EC'] },
  mane_ocean: { palette: ['#6EC6FF', '#A6E9F7', '#4F8DFF', '#BFE4FF'] },
  mane_sunset: { palette: ['#FF8A5C', '#FFC15E', '#FF6F91', '#FFD48A'] },
  mane_mint: { palette: ['#6FDCAE', '#B6F2D6', '#3FC196', '#D7F8E8'] },
  mane_galaxy: { palette: ['#8E7CFF', '#5B4BC4', '#C3A6FF', '#FF9EF0'] },
  mane_gold: { palette: ['#FFD84D', '#FFC21F', '#FFE98A', '#F5B02E'] },
}

/** Her own colours: the rainbow mane she has when no mane colour is worn. */
export const MANE_DEFAULT = ['#FF9EC7', '#FFD36E', '#9EE6C9', '#B9A6FF']
