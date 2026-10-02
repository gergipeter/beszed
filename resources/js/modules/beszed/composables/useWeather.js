import { computed, ref } from 'vue'
import { fetchWeather } from '../api/weather'
import { CITIES, DEFAULT_CITY } from '../config/cities'
import { t } from '../i18n'

const CITY_KEY = 'beszed.weatherCity'
const FRESH_MS = 30 * 60 * 1000
const ICONS = { clear: '☀️', night: '🌙', partly: '⛅', cloudy: '☁️', fog: '🌫️', rain: '🌧️', snow: '🌨️', storm: '⛈️' }

/** The parent's city for the weather, kept on this device (never sent anywhere but as the city's id). */
export function readCity() {
  try {
    const id = localStorage.getItem(CITY_KEY)
    return CITIES.some(c => c.id === id) ? id : DEFAULT_CITY
  } catch {
    return DEFAULT_CITY
  }
}
export function saveCity(id) {
  try {
    localStorage.setItem(CITY_KEY, id)
  } catch {
    /* not kept */
  }
  cache = null
}

let cache = null
/** Said aloud once per visit to the app (until the page is reloaded). */
let announced = false

/**
 * Csillám's weather report: today's weather in the parent's city, as an icon, a number and
 * a sentence with a little tip on what to wear. Quiet (null) when the weather can't be had.
 */
export function useWeather() {
  const weather = ref(cache?.data ?? null)

  async function load() {
    if (cache && Date.now() - cache.at < FRESH_MS) return (weather.value = cache.data)
    try {
      const { weather: data } = await fetchWeather(readCity())
      cache = { at: Date.now(), data }
      weather.value = data
    } catch {
      /* no weather today: nothing is shown or said */
    }
    return weather.value
  }

  const icon = computed(() => {
    const w = weather.value
    return w ? (w.kind === 'clear' && !w.day ? ICONS.night : ICONS[w.kind]) : ''
  })
  const tempText = computed(() => (weather.value ? `${weather.value.temp}°` : ''))

  /** "Ma 18 fok van, süt a nap. Szép idő van, menj ki játszani!" */
  const sentence = computed(() => {
    const w = weather.value
    if (!w) return ''
    const temp = w.temp < 0 ? t('weather.minus', { n: Math.abs(w.temp) }) : String(w.temp)
    const sky = t(w.kind === 'clear' && !w.day ? 'weather.sky.night' : `weather.sky.${w.kind}`)
    const tip =
      w.kind === 'storm' ? 'storm'
      : w.kind === 'rain' ? 'rain'
      : w.kind === 'snow' ? 'snow'
      : w.temp <= 5 ? 'cold'
      : w.temp <= 14 ? 'cool'
      : w.temp >= 28 ? 'hot'
      : w.kind === 'clear' || w.kind === 'partly' ? 'nice'
      : null
    return [t('weather.line', { temp, sky }), tip && t(`weather.tip.${tip}`)].filter(Boolean).join(' ')
  })

  return {
    weather,
    icon,
    tempText,
    sentence,
    load,
    /** True the first time it is asked in a visit: then Csillám says the weather. */
    shouldAnnounce() {
      if (announced || !weather.value) return false
      return (announced = true)
    },
  }
}
