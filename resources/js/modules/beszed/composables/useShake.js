import { ref } from 'vue'

/** Which option is shaking after a wrong tap. */
export function useShake() {
  const shaking = ref(null)

  /** Restarts the animation, even when the same option is tapped twice in a row. */
  function shake(id) {
    shaking.value = null
    requestAnimationFrame(() => (shaking.value = id))
  }

  return { shaking, shake }
}
