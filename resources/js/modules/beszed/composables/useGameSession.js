import { computed, onScopeDispose, ref, toValue } from 'vue'
import { fetchSession, recordAttempt } from '../api'
import { config } from '../config/options'
import { preloadEngines } from '../engines'
import { t } from '../i18n'
import { useGuideStore } from '../stores/guide'
import { useMetaStore } from '../stores/meta'
import { useRecordingsStore } from '../stores/recordings'
import { sleep } from '../utils/async'
import { errorMessage } from '../utils/errors'
import { pick } from '../utils/random'
import { useIdleHelp } from './useIdleHelp'

/** @typedef {import('../types').Round} Round */

/** What Csillám says to open a round. */
const promptItems = (/** @type {Round} */ round) => round.prompt.parts ?? [round.prompt.text]

/** Everything a round will probably say, for preloading. */
const roundItems = (/** @type {Round} */ round) => [...promptItems(round), round.data?.onCorrect].filter(Boolean)

/** Unique keys, so rounds of a reloaded or re-levelled session always remount their engine. */
const stamp = (/** @type {Round[]} */ rounds) => {
  const at = Date.now()
  return rounds.map(round => ({ ...round, key: `${round.key}-${at}` }))
}

/**
 * One play-through of a game: loads the rounds, speaks the prompts, judges
 * answers, saves attempts and follows level changes. SessionRunner only renders.
 *
 * @param {import('vue').MaybeRefOrGetter<number>} childId
 * @param {string} game
 */
export function useGameSession(childId, game) {
  const guide = useGuideStore()
  const meta = useMetaStore()
  const recordings = useRecordingsStore()

  /** @type {import('vue').Ref<import('../types').Session | null>} */
  const session = ref(null)
  const index = ref(0)
  const tries = ref(0)
  const locked = ref(false)
  const promptDone = ref(false)
  const finished = ref(false)
  const loading = ref(false)
  const stars = ref(0)
  const error = ref('')
  let startedAt = 0
  let active = true

  const round = computed(() => session.value?.rounds[index.value] ?? null)
  const total = computed(() => session.value?.rounds.length ?? 0)

  const idle = useIdleHelp({
    ms: config.timing.idleHelpMs,
    enabled: () => Boolean(session.value) && !session.value.no_idle && !finished.value,
    onIdle: () => {
      if (!round.value || locked.value) return
      guide.hop()
      guide.speak([{ rec: 'help', alt: t('game.help') }, ...promptItems(round.value)])
    },
  })

  async function load() {
    error.value = ''
    finished.value = false
    loading.value = true
    guide.party = false
    try {
      const fresh = await fetchSession(toValue(childId), game)
      await preloadEngines(fresh.rounds.map(r => r.engine))
      if (!active) return
      session.value = { ...fresh, rounds: stamp(fresh.rounds) }
      stars.value = fresh.stars
      index.value = 0
      // The intro is requested right away by the player; warm what comes after it.
      const feedback = [...recordings.keysStartingWith('praise'), ...recordings.keysStartingWith('retry')]
      guide.preload([...feedback.map(rec => ({ rec })), ...(round.value ? roundItems(round.value) : [])])
      startRound(true)
    } catch (e) {
      error.value = errorMessage(e, t('game.loadFailed'))
    } finally {
      loading.value = false
    }
  }

  function startRound(first) {
    tries.value = 0
    locked.value = false
    startedAt = Date.now()
    speakPrompt({ withIntro: first })
    idle.arm()
    const upcoming = session.value.rounds[index.value + 1]
    if (upcoming) guide.preload(roundItems(upcoming))
  }

  /** Says the round's prompt (or `parts` instead); `promptDone` turns true afterwards. */
  async function speakPrompt({ withIntro = false, parts } = {}) {
    const current = round.value
    if (!current) return
    const at = index.value
    promptDone.value = false
    const items = withIntro ? [{ rec: `intro_${game}`, alt: session.value.intro }] : []
    items.push(...(parts ?? promptItems(current)))
    await guide.speak(items, { caption: current.prompt.text })
    if (at === index.value) promptDone.value = true
  }

  /** Parent's recorded praise/retry if there is one (then the specific sentence), else a sentence. */
  function feedback(kind, say) {
    const recorded = recordings.keysStartingWith(kind)
    if (recorded.length) return [{ rec: pick(recorded) }, ...(say ? [say] : [])]
    if (say) return [say]
    const phrases = meta.phrases(kind)
    return [phrases.length ? pick(phrases) : t(kind === 'praise' ? 'game.praiseFallback' : 'game.retryFallback')]
  }

  /** @param {import('../types').AnswerEvent} event */
  async function answer({ correct, say }) {
    if (locked.value) return
    tries.value++
    if (!correct) {
      guide.comfort()
      guide.speak(feedback('retry', say))
      return
    }
    locked.value = true
    idle.cancel()
    stars.value++
    guide.celebrate()
    const saved = saveAttempt(true)
    await Promise.all([guide.speak(feedback('praise', say)), sleep(config.timing.praisePauseMs), saved])
    next()
  }

  async function skip(say) {
    if (locked.value) return
    locked.value = true
    idle.cancel()
    const saved = saveAttempt(false)
    await Promise.all([guide.speak([say ?? t('game.skip')]), sleep(config.timing.skipPauseMs), saved])
    next()
  }

  /** Saves the attempt; if the server changed the level, swaps in rounds of the new level. */
  async function saveAttempt(correct) {
    const current = session.value
    const played = round.value
    if (!current || !played) return
    try {
      const result = await recordAttempt(toValue(childId), {
        game,
        content_item_id: played.content_item_id,
        level: current.level,
        correct,
        tries: correct ? tries.value : Math.max(tries.value, 1),
        duration_ms: Date.now() - startedAt,
      })
      stars.value = result.stars
      const remaining = current.rounds.length - index.value - 1
      if (result.level !== current.level && remaining > 0) {
        const fresh = await fetchSession(toValue(childId), game)
        await preloadEngines(fresh.rounds.map(r => r.engine))
        current.rounds.splice(index.value + 1, remaining, ...stamp(fresh.rounds.slice(0, remaining)))
        current.level = fresh.level
      }
    } catch {
      /* offline: keep playing, progress just isn't saved */
    }
  }

  function next() {
    // The child may have left mid-praise; don't start talking on another page.
    if (!active || !session.value) return
    if (index.value + 1 >= total.value) {
      finished.value = true
      idle.cancel()
      guide.party = true
      guide.setMood('happy')
      guide.speak([{ rec: 'finish', alt: t('game.finishSpeech', { count: total.value }) }])
      return
    }
    index.value++
    startRound(false)
  }

  onScopeDispose(() => {
    active = false
    guide.reset()
  })

  return { session, round, index, total, locked, promptDone, finished, loading, stars, error, load, speakPrompt, answer, skip }
}
