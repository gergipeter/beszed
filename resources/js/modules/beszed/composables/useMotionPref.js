import { ref } from 'vue'

const KEY = 'beszed.reduceMotion'
const read = () => {
  try {
    return localStorage.getItem(KEY) === '1'
  } catch {
    return false
  }
}

/** The parent's "less movement" choice, kept on this device (on top of the device's own setting). */
const reduceMotion = ref(read())

export function useMotionPref() {
  function set(on) {
    reduceMotion.value = on
    try {
      localStorage.setItem(KEY, on ? '1' : '0')
    } catch {
      /* not kept */
    }
  }
  return { reduceMotion, set }
}
