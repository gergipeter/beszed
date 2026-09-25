import { onBeforeUnmount, ref } from 'vue'
import { config } from '../config/options'

const extensionFor = mime => (mime.includes('mp4') ? 'm4a' : mime.includes('ogg') ? 'ogg' : 'webm')

/** MediaRecorder wrapper: one recording at a time, stops itself after `timing.recordingMaxMs`. */
export function useRecorder() {
  const recordingKey = ref(/** @type {string | null} */ (null))
  const supported =
    typeof window !== 'undefined' && Boolean(navigator.mediaDevices?.getUserMedia) && 'MediaRecorder' in window
  let recorder = null
  let timer

  /**
   * Starts recording line `key`; `onDone(blob, ext)` runs when it stops.
   * @returns {Promise<boolean>} false when the microphone isn't available.
   */
  async function start(key, onDone) {
    stop()
    let stream
    let rec
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      rec = new MediaRecorder(stream)
    } catch {
      stream?.getTracks().forEach(track => track.stop())
      return false
    }

    const chunks = []
    rec.ondataavailable = e => e.data?.size && chunks.push(e.data)
    rec.onstop = () => {
      clearTimeout(timer)
      stream.getTracks().forEach(track => track.stop())
      if (recorder === rec) {
        recorder = null
        recordingKey.value = null
      }
      const mime = rec.mimeType || 'audio/mp4'
      const blob = new Blob(chunks, { type: mime })
      if (blob.size) onDone(blob, extensionFor(mime))
    }

    recorder = rec
    recordingKey.value = key
    rec.start()
    timer = setTimeout(stop, config.timing.recordingMaxMs)
    return true
  }

  function stop() {
    if (recorder && recorder.state !== 'inactive') recorder.stop()
  }

  onBeforeUnmount(stop)

  return { supported, recordingKey, start, stop }
}
