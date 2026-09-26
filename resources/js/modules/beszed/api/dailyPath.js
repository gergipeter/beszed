import { http } from './client'

/** "Mai kaland": today's suggested games. @returns {Promise<import('../types').DailyPath>} */
export const fetchDailyPath = childId => http.get(`/children/${childId}/daily-path`)
