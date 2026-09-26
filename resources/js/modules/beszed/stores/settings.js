import { defineStore } from 'pinia'
import { fetchVoiceSettings, updateVoiceSettings } from '../api'
import { configure, config } from '../config/options'
import { setVolume } from '../services/audio/player'
import { setWebSpeechVolume } from '../services/audio/webSpeech'

/** Muted volume vs. the configured default, applied to both the shared <audio> and Web Speech. */
const VOLUME = { muted: 0, on: 1 }

function applyAudio(muted) {
  const volume = muted ? VOLUME.muted : VOLUME.on
  setVolume(volume)
  setWebSpeechVolume(volume)
  // Sound effects (services/audio/sfx.js) read config.sfx; muting the voice mutes them too.
  configure({ sfx: { enabled: !muted, volume: config.sfx.volume } })
}

/** How Csillám sounds: voice pick, rate/pitch, server-vs-browser voice, mute. Per parent account. */
export const useSettingsStore = defineStore('beszed/settings', {
  state: () => ({
    /** @type {import('../types').VoiceSettings | null} */
    settings: null,
  }),

  getters: {
    loaded: state => state.settings !== null,
    voice: state => state.settings?.voice ?? null,
    rate: state => state.settings?.rate ?? null,
    pitch: state => state.settings?.pitch ?? null,
    preferServerTts: state => state.settings?.preferServerTts ?? true,
    muted: state => state.settings?.muted ?? false,
  },

  actions: {
    async load() {
      this.settings = await fetchVoiceSettings()
      applyAudio(this.settings.muted)
      return this.settings
    },

    /** @param {Partial<import('../types').VoiceSettings>} patch */
    async save(patch) {
      const body = {
        voice: this.voice,
        rate: this.rate,
        pitch: this.pitch,
        preferServerTts: this.preferServerTts,
        muted: this.muted,
        ...patch,
      }
      this.settings = await updateVoiceSettings(body)
      applyAudio(this.settings.muted)
      return this.settings
    },

    toggleMuted() {
      return this.save({ muted: !this.muted })
    },
  },
})
