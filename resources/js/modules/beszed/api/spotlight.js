import { http } from './client'

/**
 * "Hang a nap": the game the child's weakest at right now, or null when
 * there isn't enough data yet to tell.
 * @returns {Promise<{ game: string } | null>}
 */
export const fetchSpotlight = childId => http.get(`/children/${childId}/spotlight`)
