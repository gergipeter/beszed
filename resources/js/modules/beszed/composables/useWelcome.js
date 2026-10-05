import { ref } from 'vue'
import { t } from '../i18n'

const CARE_LINES = 8
const AGAIN_AFTER_MS = 20 * 60 * 1000

const key = childId => `beszed.visit.${childId}`
const daysBetween = (from, to) => Math.round((new Date(to).setHours(12) - new Date(from).setHours(12)) / 86400000)

/** Reads the last visit and stamps this one; `null` on a first visit or when nothing can be stored. */
function lastVisit(childId) {
  let at = null
  try {
    at = Number(localStorage.getItem(key(childId))) || null
    localStorage.setItem(key(childId), String(Date.now()))
  } catch {
    /* private mode: no memory of visits, a plain greeting */
  }
  return at
}

/**
 * Csillám's friendly talk on the hub: a welcome that fits how long the child has been
 * away (happy to see them, never sad they left), and every now and then a caring word,
 * so it feels like someone who cares. Warm only: no guilt, no pressure to come back.
 *
 * @param {{ childId: number, child: string, goalDone: () => boolean, intro: string, memories?: () => string[] }} options
 *   `intro`: the first visit's plain hello. `memories`: extra lines (a favourite game, a favourite mane colour),
 *   read fresh each time so they can depend on data that arrives after the hub opens.
 */
export function useWelcome({ childId, child, goalDone, intro, memories = () => [] }) {
  const before = lastVisit(childId)
  const now = Date.now()
  let lastCare = -1

  function care() {
    const extra = memories()
    // one line in four is a memory, when there is one to tell; otherwise the usual rotation
    if (extra.length && Math.random() < 0.25) return extra[Math.floor(Math.random() * extra.length)]
    let n
    do n = 1 + Math.floor(Math.random() * CARE_LINES)
    while (n === lastCare && CARE_LINES > 1)
    lastCare = n
    return t(`hub.care.c${n}`, { child })
  }

  function welcome() {
    if (!before) return intro
    const gap = daysBetween(before, now)
    if (gap >= 2) return t('hub.welcome.long', { child })
    if (gap === 1) return t('hub.welcome.back', { child })
    if (goalDone()) return t('hub.welcome.done', { child })
    return now - before > AGAIN_AFTER_MS ? t('hub.welcome.again', { child }) : care()
  }

  const line = ref(welcome())
  return {
    line,
    next: () => (line.value = care()),
    /** The rewards arrive after the hub opens: a child who already did today's goal hears the "see you tomorrow". */
    settle() {
      if (before && daysBetween(before, now) < 1 && goalDone()) line.value = t('hub.welcome.done', { child })
    },
  }
}
