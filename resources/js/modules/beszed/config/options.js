/**
 * Module-wide settings. The defaults work out of the box; the host app can
 * override any of them once at startup: `app.use(beszed, { ...options })`.
 */
const defaults = {
  /** Base URL of the Laravel route group (routes/beszed.php). */
  apiBase: '/api/beszed',

  /** Name of the guide when the layout gets no `guideName` prop. */
  guideName: 'Csillám',

  /**
   * Where the hub's "Gyerekek" button leads, e.g. the host app's child picker
   * (`{ name: 'children' }`). null = no button.
   */
  exitTo: null,

  /**
   * Optional emoji image set, so pictures look the same on every device.
   * Twemoji-style file names (`1f41d.svg`), e.g. `{ baseUrl: '/vendor/twemoji/svg/' }`.
   * `null` = the device's own emoji font. Missing images fall back to it too.
   */
  emoji: { baseUrl: null, ext: '.svg' },

  /** Browser Web Speech fallback voice. */
  voice: { lang: 'hu-HU', rate: 0.85, pitch: 1.15 },

  /** Synthesised sound effects (chime, fanfare…); volume 0–1. */
  sfx: { enabled: true, volume: 0.8 },

  timing: {
    /** Csillám offers help when nothing is tapped for this long. */
    idleHelpMs: 20_000,
    /** Minimum pause after a correct answer before the next round. */
    praisePauseMs: 1500,
    /** Minimum pause after a skipped round. */
    skipPauseMs: 1200,
    /** Give up waiting for an audio file's `ended` event after this long. */
    playbackTimeoutMs: 30_000,
    /** Parent recordings stop automatically after this long. */
    recordingMaxMs: 25_000,
  },

  /** Server-TTS failures in a row before the visit stays on Web Speech. */
  ttsMaxFailures: 3,

  /** Fetch the next round's audio while the current round is played. */
  preloadAudio: true,
}

export const config = structuredClone(defaults)

const isPlainObject = value => value !== null && typeof value === 'object' && !Array.isArray(value)

function merge(target, source) {
  for (const [key, value] of Object.entries(source)) {
    if (isPlainObject(value) && isPlainObject(target[key])) merge(target[key], value)
    else if (value !== undefined) target[key] = value
  }
}

/** Deep-merges `options` into the active config. */
export function configure(options = {}) {
  merge(config, options)
}
