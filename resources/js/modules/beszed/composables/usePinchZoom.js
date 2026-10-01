import { onMounted, onBeforeUnmount, ref } from 'vue'

/**
 * Two-finger pinch zoom for any element.
 * Usage: const { scale, resetZoom } = usePinchZoom(elementRef)
 */
export const usePinchZoom = (element) => {
  const scale = ref(1)
  let lastDistance = 0

  const getDistance = (touches) => {
    if (touches.length !== 2) return 0
    const dx = touches[0].clientX - touches[1].clientX
    const dy = touches[0].clientY - touches[1].clientY
    return Math.sqrt(dx * dx + dy * dy)
  }

  const handleTouchStart = (e) => {
    if (e.touches.length === 2) {
      e.preventDefault()
      lastDistance = getDistance(e.touches)
    }
  }

  const handleTouchMove = (e) => {
    if (e.touches.length === 2) {
      e.preventDefault()
      const currentDistance = getDistance(e.touches)
      if (lastDistance > 0) {
        const delta = currentDistance / lastDistance
        scale.value = Math.max(1, Math.min(3, scale.value * delta))
      }
      lastDistance = currentDistance
    }
  }

  const handleTouchEnd = () => {
    lastDistance = 0
  }

  const resetZoom = () => {
    scale.value = 1
  }

  onMounted(() => {
    if (element.value) {
      const el = element.value
      el.addEventListener('touchstart', handleTouchStart, { passive: false })
      el.addEventListener('touchmove', handleTouchMove, { passive: false })
      el.addEventListener('touchend', handleTouchEnd)
      // one finger still scrolls; the two-finger pinch is ours (preventDefault above)
      el.style.touchAction = 'pan-x pan-y'
    }
  })

  onBeforeUnmount(() => {
    if (element.value) {
      const el = element.value
      el.removeEventListener('touchstart', handleTouchStart)
      el.removeEventListener('touchmove', handleTouchMove)
      el.removeEventListener('touchend', handleTouchEnd)
    }
  })

  return { scale, resetZoom }
}
