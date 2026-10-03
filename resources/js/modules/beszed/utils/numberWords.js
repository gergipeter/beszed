const HU_ONES = ['', 'egy', 'kettő', 'három', 'négy', 'öt', 'hat', 'hét', 'nyolc', 'kilenc']
const HU_TENS = ['', 'tíz', 'húsz', 'harminc', 'negyven', 'ötven', 'hatvan', 'hetven', 'nyolcvan', 'kilencven']
const HU_HUNDREDS = ['', 'száz', 'kétszáz', 'háromszáz', 'négyszáz', 'ötszáz', 'hatszáz', 'hétszáz', 'nyolcszáz', 'kilencszáz']

const EN_ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen']
const EN_TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety']

const ES_ONES = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve']
const ES_TENS = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa']
const ES_HUNDREDS = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos']

/** 1–999 in Spanish: 21 "veintiuno", 45 "cuarenta y cinco", 100 "cien", 123 "ciento veintitrés". */
function esWords(n) {
  if (n === 100) return 'cien'
  const hundreds = ES_HUNDREDS[Math.floor(n / 100)]
  const rest = n % 100
  const restWords = rest < 30 ? ES_ONES[rest] : ES_TENS[Math.floor(rest / 10)] + (rest % 10 ? ` y ${ES_ONES[rest % 10]}` : '')
  return [hundreds, restWords].filter(Boolean).join(' ')
}

/** 1–99 in Hungarian, written together: 14 "tizennégy", 22 "huszonkettő", 35 "harmincöt". */
function huBelowHundred(n) {
  const tens = Math.floor(n / 10)
  const ones = n % 10
  if (tens === 0) return HU_ONES[ones]
  if (ones === 0) return HU_TENS[tens]
  const prefix = tens === 1 ? 'tizen' : tens === 2 ? 'huszon' : HU_TENS[tens]
  return prefix + HU_ONES[ones]
}

/**
 * A whole number from 1 to 999 in words ("háromszázhuszonnégy" / "three hundred twenty-four"). Used by the parental
 * gate: reading the number takes an adult (or a school child), a four-year-old cannot do it.
 * @param {number} n
 * @param {'hu' | 'en' | 'es'} [lang]
 */
export function numberWords(n, lang = 'hu') {
  const value = Math.trunc(n)
  if (!(value >= 1 && value <= 999)) throw new RangeError(`numberWords: ${n} is out of range (1–999)`)

  if (lang === 'es') return esWords(value)

  if (lang === 'en') {
    const hundreds = Math.floor(value / 100)
    const rest = value % 100
    const restWords = rest < 20 ? EN_ONES[rest] : EN_TENS[Math.floor(rest / 10)] + (rest % 10 ? `-${EN_ONES[rest % 10]}` : '')
    return [hundreds ? `${EN_ONES[hundreds]} hundred` : '', restWords].filter(Boolean).join(' ')
  }

  return HU_HUNDREDS[Math.floor(value / 100)] + huBelowHundred(value % 100)
}
