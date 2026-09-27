import { http } from './client'
import { postOrQueue } from './outbox'

/** @returns {Promise<import('../types').Session>} */
/** `category`: the picture theme picked before playing (Kirakó), if any. */
export const fetchSession = (childId, game, { category } = {}) =>
  http.get(`/children/${childId}/session`, { params: { game, ...(category ? { category } : {}) } })

/**
 * @param {number} childId
 * @param {import('../types').AttemptBody} body
 * @returns {Promise<{ level: number, stars: number }>}
 */
export const recordAttempt = (childId, body) => postOrQueue(`/children/${childId}/attempts`, body)
