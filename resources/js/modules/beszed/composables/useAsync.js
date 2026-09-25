import { ref, shallowRef } from 'vue'
import { errorMessage } from '../utils/errors'

/**
 * Loading / error / data state around an async function. Only the latest call
 * updates the state, so quick re-runs (e.g. changing a filter) can't race.
 * @template T
 * @param {(...args: any[]) => Promise<T>} fn
 * @param {{ fallback: string }} options  message shown when the server gives none
 */
export function useAsync(fn, { fallback }) {
  const data = shallowRef(/** @type {T | null} */ (null))
  const error = ref('')
  const loading = ref(false)
  let latest = 0

  async function run(...args) {
    const call = ++latest
    loading.value = true
    error.value = ''
    try {
      const result = await fn(...args)
      if (call === latest) data.value = result
    } catch (e) {
      if (call === latest) error.value = errorMessage(e, fallback)
    } finally {
      if (call === latest) loading.value = false
    }
  }

  return { data, error, loading, run }
}
