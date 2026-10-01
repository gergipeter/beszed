import { http } from './client'

/** "Utazás": the games of each skill area as a track, and what to play next. @returns {Promise<import('../types').Journey>} */
export const fetchJourney = childId => http.get(`/children/${childId}/journey`)
