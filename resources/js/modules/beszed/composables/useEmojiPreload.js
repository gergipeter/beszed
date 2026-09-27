import { config } from '../config/options'
import { emojiAssetName, pictogram, upload, splitEmoji } from '../utils/emoji'

/**
 * Preload emoji SVGs before they're rendered.
 * This eliminates the waterfall when a new game loads with multiple emojis.
 * @param {string[]} chars emoji characters to preload
 * @returns {Promise<void>}
 */
export function preloadEmojis(chars) {
  if (!chars?.length) return Promise.resolve()

  const urls = new Set()

  chars.forEach(char => {
    const picto = pictogram(char)
    const uploaded = upload(char)

    if (picto) {
      urls.add(`${config.pictograms.baseUrl.replace(/\/?$/, '/')}${picto.id}.png`)
    } else if (uploaded) {
      urls.add(`/api/content-images/${uploaded.id}`)
    } else if (config.emoji.baseUrl) {
      splitEmoji(char).forEach(ch => {
        urls.add(`${config.emoji.baseUrl.replace(/\/?$/, '/')}${emojiAssetName(ch)}${config.emoji.ext}`)
      })
    }
  })

  if (urls.size === 0) return Promise.resolve()

  return Promise.all(
    Array.from(urls).map(url =>
      fetch(url, { priority: 'high' })
        .then(r => r.ok ? r.blob() : Promise.reject())
        .catch(() => {}) // graceful fallback to native emoji
    )
  )
}

/**
 * Extract all emoji chars from a round's data.
 * @param {import('../types').Round} round
 * @returns {string[]}
 */
export function extractRoundEmojis(round) {
  const emojis = []

  // Stimulus emoji (picture being asked about)
  if (round.data?.stimulus?.emoji) {
    emojis.push(round.data.stimulus.emoji)
  }

  // Answer options (Choice, Sequence, etc)
  if (Array.isArray(round.data?.options)) {
    round.data.options.forEach(opt => {
      if (opt.emoji) emojis.push(opt.emoji)
    })
  }

  // Sequence items
  if (round.data?.sequence) {
    emojis.push(...splitEmoji(round.data.sequence))
  }

  // Traced/Judged/etc stimulus
  if (round.data?.stimuli?.emoji) {
    emojis.push(round.data.stimuli.emoji)
  }

  return [...new Set(emojis)] // deduplicate
}

/**
 * Preload all emojis from upcoming rounds.
 * Call this after loading a session to warm the cache before rounds render.
 * @param {import('../types').Round[]} rounds
 * @returns {Promise<void>}
 */
export function preloadSessionEmojis(rounds) {
  const allEmojis = []
  // Preload first 3 rounds' emojis (the child won't see more than that before cached)
  rounds.slice(0, 3).forEach(round => {
    allEmojis.push(...extractRoundEmojis(round))
  })
  return preloadEmojis(allEmojis)
}
