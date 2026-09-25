let context = null

/** Short synthesised "drum" blip: instant, and no audio file to load. */
export function beep(freq = 160, duration = 0.14) {
  try {
    context ??= new (window.AudioContext || window.webkitAudioContext)()
    const osc = context.createOscillator()
    const gain = context.createGain()
    const now = context.currentTime
    osc.type = 'triangle'
    osc.frequency.setValueAtTime(freq, now)
    osc.frequency.exponentialRampToValueAtTime(freq * 0.5, now + duration)
    gain.gain.setValueAtTime(0.5, now)
    gain.gain.exponentialRampToValueAtTime(0.001, now + duration)
    osc.connect(gain).connect(context.destination)
    osc.start()
    osc.stop(now + duration)
  } catch {
    /* audio not available */
  }
}
