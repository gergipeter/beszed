import { http } from './client'

/**
 * line_key → audio URL of the parent's recordings.
 * (PHP serialises an empty map as `[]`, hence the normalising.)
 * @returns {Promise<Record<string, string>>}
 */
export const fetchRecordings = () =>
  http.get('/recordings').then(body => (Array.isArray(body.recordings) ? {} : body.recordings))

/** @returns {Promise<{ line_key: string, url: string }>} */
export function uploadRecording(lineKey, blob, filename) {
  const form = new FormData()
  form.append('line_key', lineKey)
  form.append('file', blob, filename)
  return http.post('/recordings', form)
}

export const deleteRecording = lineKey => http.delete(`/recordings/${encodeURIComponent(lineKey)}`)
