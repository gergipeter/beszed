import { http } from './client'
import { postOrQueue } from './outbox'

/** @returns {Promise<import('../types').Session>} */
/** `category`: the picture theme picked before playing (Kirakó), if any. */
/** `level`: a pálya picked by hand, instead of the adaptive one. */
export const fetchSession = (childId, game, { category, level } = {}) =>
  http.get(`/children/${childId}/session`, { params: { game, ...(category ? { category } : {}), ...(level ? { level } : {}) } })

/**
 * @param {number} childId
 * @param {import('../types').AttemptBody} body
 * @returns {Promise<{ level: number, stars: number }>}
 */
export const recordAttempt = (childId, body) => postOrQueue(`/children/${childId}/attempts`, body)
