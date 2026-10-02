import { jsPDF } from 'jspdf'
import { formatDate, formatPercent } from '../../i18n'

const INK = '#2B2A3A'
const MUTED = '#6B6B7A'
const LINE = '#D8D8E2'
const BAND_COLOR = { strong: '#2E9E6C', growing: '#3A7BD5', practice: '#D98A2B', noData: '#9A9AA8' }

/** In-game, non-diagnostic wording only — the app has no peer/age norms to compare against. */
export const BAND_LABEL = Object.freeze({
  strong: 'Biztosan megy',
  growing: 'Fejlődik',
  practice: 'Gyakoroljuk még',
  noData: 'Még kevés adat',
})

const SOUND_KIND = Object.freeze({ start: 'kezdőhang', contrast: 'hangpár', rhyme: 'rím' })
const TREND = Object.freeze({ up: '↗ javult', flat: '→ hasonló', down: '↘ kevesebb' })

const MARGIN = 48
const PAGE_W = 595.28 // A4 pt

/**
 * Builds a clinical-style PDF summary of a child's progress: letterhead, per-area
 * bands (from the server's ProgressReport, grouped the way a logopédus/DIFER
 * assessment would), a narrative paragraph, recommendations, and the per-game table.
 * @param {{ child: { name: string }, since: string, games: import('../../types').GameProgress[], areas: SkillArea[], sounds?: import('../../types').SoundReport, narrative?: string, recommendations?: string[] }} report
 * @param {{ ageLabel?: string }} [meta]
 *
 * @typedef {object} SkillArea
 * @property {string} key
 * @property {string} label
 * @property {boolean} difer
 * @property {number} sessions
 * @property {number | null} firstTryRate
 * @property {'strong'|'growing'|'practice'|'noData'} band
 */
export function buildClinicalReportPdf(report, meta = {}) {
  const doc = new jsPDF({ unit: 'pt', format: 'a4' })
  let y = MARGIN

  y = drawLetterhead(doc, y, report, meta)
  y = drawAreas(doc, y, report.areas ?? [])
  y = drawSounds(doc, y, report.sounds)
  y = drawNarrative(doc, y, report.narrative)
  y = drawRecommendations(doc, y, report.recommendations ?? [])
  drawGameTable(doc, y, report.games ?? [])
  drawFooter(doc)

  return doc
}

export function downloadClinicalReport(report, meta) {
  const doc = buildClinicalReportPdf(report, meta)
  const safeName = (report.child?.name || 'gyermek').replace(/[^\p{L}\p{N}]+/gu, '_')
  doc.save(`beszed-osszefoglalo-${safeName}-${report.since}.pdf`)
}

function drawLetterhead(doc, y, report, meta) {
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(17)
  doc.setTextColor(INK)
  doc.text('Fejlődési összefoglaló', MARGIN, y)
  y += 16

  doc.setFont('helvetica', 'normal')
  doc.setFontSize(10)
  doc.setTextColor(MUTED)
  doc.text('Beszédfejlesztő és DIFER-készségeket gyakorló program – szülőknek és szakembereknek', MARGIN, y)
  y += 18

  doc.setDrawColor(LINE)
  doc.line(MARGIN, y, PAGE_W - MARGIN, y)
  y += 20

  const rows = [
    ['Gyermek neve', report.child?.name ?? '–'],
    ['Életkor', meta.ageLabel ?? '–'],
    ['Vizsgált időszak', `${formatDate(report.since)} – ${formatDate(new Date())}`],
    ['Készült', formatDate(new Date())],
  ]
  doc.setFontSize(10)
  rows.forEach(([label, value], i) => {
    const col = i % 2 === 0 ? MARGIN : PAGE_W / 2 + 10
    const row = Math.floor(i / 2)
    doc.setFont('helvetica', 'bold')
    doc.setTextColor(INK)
    doc.text(`${label}:`, col, y + row * 16)
    doc.setFont('helvetica', 'normal')
    doc.setTextColor(MUTED)
    doc.text(String(value), col + 90, y + row * 16)
  })
  y += Math.ceil(rows.length / 2) * 16 + 14

  doc.setFontSize(8.5)
  doc.setTextColor(MUTED)
  const disclaimer = doc.splitTextToSize(
    'Ez az összefoglaló a program játékain mért teljesítményből készült automatikusan, nem sztenderdizált teszt, ' +
      'és nem helyettesíti a logopédiai vagy pszichológiai szakvizsgálatot. A DIFER-területekhez való hozzárendelés ' +
      'közelítő. Célja a szakemberrel folytatott beszélgetés segítése.',
    PAGE_W - 2 * MARGIN,
  )
  doc.text(disclaimer, MARGIN, y)
  y += disclaimer.length * 11 + 12

  return y
}

function sectionTitle(doc, y, text) {
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(12.5)
  doc.setTextColor(INK)
  doc.text(text, MARGIN, y)
  return y + 16
}

function drawAreas(doc, y, areas) {
  if (!areas.length) return y
  y = sectionTitle(doc, y, 'Területenkénti áttekintés')

  const difer = areas.filter(a => a.difer)
  const extra = areas.filter(a => !a.difer)
  if (difer.length) y = drawAreaGroup(doc, y, 'DIFER-készségek', difer)
  if (extra.length) y = drawAreaGroup(doc, y, 'További gyakorolt készségek', extra)

  return y + 8
}

function drawAreaGroup(doc, y, groupLabel, areas) {
  doc.setFont('helvetica', 'italic')
  doc.setFontSize(9)
  doc.setTextColor(MUTED)
  doc.text(groupLabel, MARGIN, y)
  y += 14

  const barX = MARGIN + 165
  const barW = PAGE_W - MARGIN - barX - 90

  areas.forEach(a => {
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(9.5)
    doc.setTextColor(INK)
    doc.text(a.label, MARGIN, y + 8)

    doc.setDrawColor(LINE)
    doc.setFillColor(240, 240, 245)
    doc.roundedRect(barX, y, barW, 10, 3, 3, 'F')
    if (a.firstTryRate != null) {
      const color = hexToRgb(BAND_COLOR[a.band] ?? BAND_COLOR.noData)
      doc.setFillColor(color.r, color.g, color.b)
      doc.roundedRect(barX, y, Math.max(6, barW * a.firstTryRate), 10, 3, 3, 'F')
    }

    doc.setFontSize(8.5)
    doc.setTextColor(MUTED)
    doc.text(BAND_LABEL[a.band] ?? BAND_LABEL.noData, barX + barW + 8, y + 8)
    y += 20
  })

  return y + 6
}

/** Per first sound / sound pair / rhyme: first-try share as a bar, band and trend; then the plain-words summary. */
function drawSounds(doc, y, sounds) {
  const items = sounds?.items ?? []
  if (!items.length) return y
  y = sectionTitle(doc, y, 'Hangonkénti áttekintés')

  const barX = MARGIN + 165
  const barW = PAGE_W - MARGIN - barX - 110

  items.forEach(s => {
    if (y > 770) {
      doc.addPage()
      y = MARGIN
    }
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(9.5)
    doc.setTextColor(INK)
    doc.text(s.label, MARGIN, y + 8)
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(8)
    doc.setTextColor(MUTED)
    doc.text(SOUND_KIND[s.kind] ?? '', MARGIN + 52, y + 8, { maxWidth: 108 })

    doc.setFillColor(240, 240, 245)
    doc.roundedRect(barX, y, barW, 10, 3, 3, 'F')
    if (s.firstTryRate != null) {
      const color = hexToRgb(BAND_COLOR[s.band] ?? BAND_COLOR.noData)
      doc.setFillColor(color.r, color.g, color.b)
      doc.roundedRect(barX, y, Math.max(6, barW * s.firstTryRate), 10, 3, 3, 'F')
    }

    doc.setFontSize(8.5)
    doc.text(`${formatPercent(s.firstTryRate)} · ${BAND_LABEL[s.band] ?? ''}${s.trend ? ` · ${TREND[s.trend]}` : ''}`, barX + barW + 8, y + 8)
    y += 20
  })

  if (sounds.summary) {
    y += 2
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(10)
    doc.setTextColor(INK)
    const lines = doc.splitTextToSize(sounds.summary, PAGE_W - 2 * MARGIN)
    doc.text(lines, MARGIN, y)
    y += lines.length * 13
  }

  doc.setFontSize(8.5)
  doc.setTextColor(MUTED)
  const note = doc.splitTextToSize(
    `Legalább ${sounds.minAttempts} válasz kell egy hanghoz az időszakban, hogy megjelenjen. A bontás a játékbeli felismerést és ` +
      'megkülönböztetést írja le, nem a kiejtést; nem diagnózis.',
    PAGE_W - 2 * MARGIN,
  )
  doc.text(note, MARGIN, y + 10)

  return y + 10 + note.length * 11 + 12
}

function drawNarrative(doc, y, narrative) {
  if (!narrative) return y
  y = sectionTitle(doc, y, 'Szöveges összefoglaló')
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(10)
  doc.setTextColor(INK)
  const lines = doc.splitTextToSize(narrative, PAGE_W - 2 * MARGIN)
  doc.text(lines, MARGIN, y)
  return y + lines.length * 13 + 14
}

function drawRecommendations(doc, y, items) {
  if (!items.length) return y
  y = sectionTitle(doc, y, 'Javasolt következő lépések')
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(10)
  items.forEach(item => {
    doc.setTextColor(INK)
    doc.text('•', MARGIN, y)
    const lines = doc.splitTextToSize(item, PAGE_W - 2 * MARGIN - 14)
    doc.text(lines, MARGIN + 14, y)
    y += lines.length * 13 + 4
  })
  return y + 10
}

function drawGameTable(doc, y, games) {
  if (!games.length) return
  y = sectionTitle(doc, y, 'Játékonkénti részletek')
  const cols = [
    { label: 'Játék', w: 150 },
    { label: 'Készség', w: 150 },
    { label: 'Végigjátszva', w: 70 },
    { label: 'Elsőre jó', w: 60 },
    { label: 'Szint', w: 60 },
  ]
  let x = MARGIN
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(9)
  doc.setTextColor(MUTED)
  cols.forEach(c => {
    doc.text(c.label, x, y)
    x += c.w
  })
  y += 6
  doc.setDrawColor(LINE)
  doc.line(MARGIN, y, PAGE_W - MARGIN, y)
  y += 12

  doc.setFont('helvetica', 'normal')
  doc.setTextColor(INK)
  games.forEach(g => {
    if (y > 780) {
      doc.addPage()
      y = MARGIN
    }
    x = MARGIN
    const values = [
      g.name,
      g.skill,
      String(g.sessions),
      formatPercent(g.firstTryRate),
      g.level ? `${g.level}/${g.maxLevel}` : '–',
    ]
    values.forEach((v, i) => {
      doc.text(String(v), x, y, { maxWidth: cols[i].w - 6 })
      x += cols[i].w
    })
    y += 16
  })
}

function drawFooter(doc) {
  const pages = doc.getNumberOfPages()
  for (let p = 1; p <= pages; p++) {
    doc.setPage(p)
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(8)
    doc.setTextColor(MUTED)
    doc.text(`${p}. oldal / ${pages}`, PAGE_W - MARGIN, 820, { align: 'right' })
  }
}

function hexToRgb(hex) {
  const n = parseInt(hex.slice(1), 16)
  return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 }
}
