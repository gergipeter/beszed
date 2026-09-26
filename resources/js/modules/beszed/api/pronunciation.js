import { http } from './client'

/**
 * Scores a spoken attempt against `text` (Mondd utánam). `available: false` means
 * no server assessment is configured; the caller should fall back to the parent judging it.
 * @returns {Promise<{ available: boolean, correct?: boolean, tries?: number }>}
 */
export function assessPronunciation(text, blob, filename) {
  const form = new FormData()
  form.append('text', text)
  form.append('audio', blob, filename)
  return http.post('/pronunciation', form)
}
