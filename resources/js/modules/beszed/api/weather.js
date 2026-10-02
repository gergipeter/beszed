import { http } from './client'

/**
 * Csillám's weather report for a city the parent picked: only the city's id is sent
 * (nothing about the child). `weather` is null when the weather service is out of reach.
 * @param {string} city
 * @returns {Promise<{ weather: { city: string, temp: number, kind: string, day: boolean } | null }>}
 */
export const fetchWeather = city => http.get('/weather', { params: { city } })
