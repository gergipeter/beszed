import { computed, ref } from 'vue'

const key = childId => `beszed.gift.${childId}`
const today = () => {
  const d = new Date()
  return `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`
}

/**
 * The daily gift: once a day the child opens a present, and it is always the same
 * thing, a new flower for the garden. Nothing random, nothing lost by skipping a day.
 * Kept on this device.
 */
export function useDailyGift(childId) {
  let saved = { day: '', total: 0 }
  try {
    saved = { ...saved, ...JSON.parse(localStorage.getItem(key(childId)) ?? '{}') }
  } catch {
    /* no memory: today's gift is on offer */
  }
  const day = ref(saved.day)
  const total = ref(saved.total)
  const available = computed(() => day.value !== today())

  function open() {
    if (!available.value) return false
    day.value = today()
    total.value++
    try {
      localStorage.setItem(key(childId), JSON.stringify({ day: day.value, total: total.value }))
    } catch {
      /* not kept */
    }
    return true
  }
  return { available, total, open }
}
