import { onBeforeUnmount } from 'vue'

/** setTimeout that is cleared automatically when the component unmounts. */
export function useTimers() {
  const pending = new Set()

  function later(fn, ms) {
    const id = setTimeout(() => {
      pending.delete(id)
      fn()
    }, ms)
    pending.add(id)
    return id
  }

  function clearAll() {
    pending.forEach(clearTimeout)
    pending.clear()
  }

  onBeforeUnmount(clearAll)

  return { later, clearAll }
}
