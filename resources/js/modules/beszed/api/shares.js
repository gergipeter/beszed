import { http } from './client'

/** Read-only progress links for the speech therapist. */
export const fetchShares = childId => http.get(`/children/${childId}/shares`)

/** @returns {Promise<{ share: import('../types').Share, url: string }>} the link is only returned here, once */
export const createShare = (childId, body) => http.post(`/children/${childId}/shares`, body)

export const revokeShare = (childId, id) => http.delete(`/children/${childId}/shares/${id}`)
