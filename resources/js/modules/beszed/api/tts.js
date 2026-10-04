import { apiUrl } from './client'

/**
 * mp3 of `text` in the server voice (synthesised once, then cached forever).
 * `variant` identifies the parent's voice settings: the server picks the voice per account, but the browser caches by URL,
 * so without it a changed voice would keep playing the old audio.
 */
export const ttsUrl = (text, variant = '') => `${apiUrl('/tts')}?t=${encodeURIComponent(text)}${variant ? `&v=${encodeURIComponent(variant)}` : ''}`
