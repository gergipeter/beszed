import { onBeforeUnmount, ref } from 'vue'

/**
 * The microphone as a level meter, for the voice engine. The sound goes into an AnalyserNode and each
 * frame is read and forgotten: nothing is recorded, kept or sent. stop() ends the tracks (the browser's
 * mic light goes off) and closes the audio context; it runs on unmount too.
 *
 * status: idle → asking → ready (or suspended: the audio needs a tap first, iOS) · denied · unsupported
 */
export function useMic() {
  const status = ref('idle')
  const supported =
    typeof navigator !== 'undefined' &&
    Boolean(navigator.mediaDevices?.getUserMedia) &&
    typeof window !== 'undefined' &&
    Boolean(window.AudioContext || window.webkitAudioContext)

  let stream = null
  let context = null
  let analyser = null
  let source = null
  let buffer = null
  let disposed = false

  async function start() {
    if (!supported) {
      status.value = 'unsupported'
      return false
    }
    if (status.value === 'asking' || status.value === 'ready') return true
    disposed = false
    status.value = 'asking'
    try {
      // the browser's noise suppression would wipe a blow out (it is noise); its gain control would hide soft vs strong
      const got = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: true, noiseSuppression: false, autoGainControl: false },
      })
      if (disposed) {
        got.getTracks().forEach(track => track.stop())
        return false
      }
      stream = got
      stream.getAudioTracks().forEach(track => track.addEventListener('ended', () => (status.value = 'denied')))
      const Ctor = window.AudioContext || window.webkitAudioContext
      context = new Ctor()
      source = context.createMediaStreamSource(stream)
      analyser = context.createAnalyser()
      analyser.fftSize = 2048
      analyser.smoothingTimeConstant = 0
      // some browsers only run a graph that reaches the speakers: through a silent gain, so nothing is heard
      const mute = context.createGain()
      mute.gain.value = 0
      source.connect(analyser)
      analyser.connect(mute).connect(context.destination)
      buffer = new Float32Array(analyser.fftSize)
      if (context.state === 'suspended') await context.resume().catch(() => {})
      status.value = context.state === 'suspended' ? 'suspended' : 'ready'
      return true
    } catch {
      stop()
      status.value = 'denied'
      return false
    }
  }

  /** From a tap: iOS lets the audio run only after one. */
  async function resume() {
    if (!context) return
    await context.resume().catch(() => {})
    if (context.state === 'running') status.value = 'ready'
  }

  /** This frame's samples (−1…1), or null when the mic isn't running. */
  function read() {
    if (!analyser || status.value !== 'ready') return null
    analyser.getFloatTimeDomainData(buffer)
    return buffer
  }

  const sampleRate = () => context?.sampleRate ?? 48000

  function stop() {
    disposed = true
    stream?.getTracks().forEach(track => track.stop())
    try {
      source?.disconnect()
    } catch {
      /* already gone */
    }
    context?.close().catch(() => {})
    stream = context = analyser = source = buffer = null
    if (status.value === 'ready' || status.value === 'suspended' || status.value === 'asking') status.value = 'idle'
  }

  onBeforeUnmount(stop)

  return { status, supported, start, resume, read, sampleRate, stop }
}
