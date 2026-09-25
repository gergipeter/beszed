import { http } from './client'

/** @returns {Promise<import('../types').Meta>} */
export const fetchMeta = () => http.get('/meta')
