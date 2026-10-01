const HU_ONES = ['', 'egy', 'kettő', 'három', 'négy', 'öt', 'hat', 'hét', 'nyolc', 'kilenc']
const HU_TENS = ['', 'tíz', 'húsz', 'harminc', 'negyven', 'ötven', 'hatvan', 'hetven', 'nyolcvan', 'kilencven']
const HU_HUNDREDS = ['', 'száz', 'kétszáz', 'háromszáz', 'négyszáz', 'ötszáz', 'hatszáz', 'hétszáz', 'nyolcszáz', 'kilencszáz']

const EN_ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen']
const EN_TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety']

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
 * @param {'hu' | 'en'} [lang]
 */
export function numberWords(n, lang = 'hu') {
  const value = Math.trunc(n)
  if (!(value >= 1 && value <= 999)) throw new RangeError(`numberWords: ${n} is out of range (1–999)`)

  if (lang === 'en') {
    const hundreds = Math.floor(value / 100)
    const rest = value % 100
    const restWords = rest < 20 ? EN_ONES[rest] : EN_TENS[Math.floor(rest / 10)] + (rest % 10 ? `-${EN_ONES[rest % 10]}` : '')
    return [hundreds ? `${EN_ONES[hundreds]} hundred` : '', restWords].filter(Boolean).join(' ')
  }

  return HU_HUNDREDS[Math.floor(value / 100)] + huBelowHundred(value % 100)
}
