import { http } from './client'

/** @returns {Promise<import('../types').VoiceSettings>} */
export const fetchVoiceSettings = () => http.get('/voice-settings')

/** @param {import('../types').VoiceSettings} body @returns {Promise<import('../types').VoiceSettings>} */
export const updateVoiceSettings = body =>
  http.put('/voice-settings', {
    voice: body.voice,
    rate: body.rate,
    pitch: body.pitch,
    prefer_server_tts: body.preferServerTts,
    muted: body.muted,
  })
