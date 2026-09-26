import { http } from './client'

/** @returns {Promise<import('../types').ProgressReport>} */
export const fetchProgress = (childId, days = 30) => http.get(`/children/${childId}/progress`, { params: { days } })

/**
 * Week-by-week trend (weeks start on Monday).
 * @returns {Promise<{ game: string | null, weeks: { week: string, games: number, answers: number, firstTryRate: number | null, minutes: number }[] }>}
 */
export const fetchProgressHistory = (childId, weeks = 8) =>
  http.get(`/children/${childId}/progress/history`, { params: { weeks } })
