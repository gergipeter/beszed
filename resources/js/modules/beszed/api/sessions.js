import { http } from './client'
import { postOrQueue } from './outbox'

/** @returns {Promise<import('../types').Session>} */
export const fetchSession = (childId, game) => http.get(`/children/${childId}/session`, { params: { game } })

/**
 * @param {number} childId
 * @param {import('../types').AttemptBody} body
 * @returns {Promise<{ level: number, stars: number }>}
 */
export const recordAttempt = (childId, body) => postOrQueue(`/children/${childId}/attempts`, body)
