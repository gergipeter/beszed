import { http } from './client'

/** @returns {Promise<import('../types').ProgressReport>} */
export const fetchProgress = (childId, days = 30) => http.get(`/children/${childId}/progress`, { params: { days } })
