import { apiUrl } from './client'

/** mp3 of `text` in the server voice (synthesised once, then cached forever). */
export const ttsUrl = text => `${apiUrl('/tts')}?t=${encodeURIComponent(text)}`
