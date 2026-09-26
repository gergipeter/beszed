import { http } from './client'
import { postOrQueue } from './outbox'

/** @returns {Promise<import('../types').RewardSummary>} */
export const fetchRewards = childId => http.get(`/children/${childId}/rewards`)

/**
 * A game was played to the end.
 * @param {{ game: string, level: number, rounds: number, correct: number, first_try: number, duration_ms: number }} body
 * @returns {Promise<import('../types').RewardSummary & { result: import('../types').RewardResult }>}
 */
export const completeSession = (childId, body) => postOrQueue(`/children/${childId}/sessions`, body)

/** @returns {Promise<import('../types').RewardSummary>} */
export const wearAccessory = (childId, accessory) => http.put(`/children/${childId}/profile`, { accessory })
