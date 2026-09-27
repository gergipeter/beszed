import { computed, onScopeDispose, ref, shallowRef, toValue } from 'vue'
import { fetchSession, recordAttempt } from '../api'
import { isNetworkError } from '../api/outbox'
import { config } from '../config/options'
import { preloadEngines } from '../engines'
import { t } from '../i18n'
import { useGuideStore } from '../stores/guide'
import { useMetaStore } from '../stores/meta'
import { useRecordingsStore } from '../stores/recordings'
import { useRewardsStore } from '../stores/rewards'
import { sleep } from '../utils/async'
import { chime } from '../services/audio/sfx'
import { burst } from '../services/effects/burst'
import { buzz, lastTouch } from '../services/touch/feel'
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

/** What Csillám announces about a finished game's rewards. */
function rewardSpeech(/** @type {import('../types').RewardResult | null} */ result, level) {
  if (!result) return []
  return [
    ...(result.level_up ? [t('game.levelUpSpeech', { level })] : []),
    ...result.unlocked.map(a => t('game.unlockSpeech', { name: a.name })),
    ...result.new_badges.map(b => t('game.stickerSpeech', { name: b.name })),
  ]
}

/**
 * One play-through of a game: loads the rounds, speaks the prompts, judges
 * answers, saves attempts, follows level changes and hands the finished game
 * to the rewards. SessionRunner only renders.
 *
 * @param {import('vue').MaybeRefOrGetter<number>} childId
 * @param {string} game
 */
export function useGameSession(childId, game) {
  const guide = useGuideStore()
  const meta = useMetaStore()
  const recordings = useRecordingsStore()
  const rewards = useRewardsStore()

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
  /** This game's score: rounds solved, and solved at the first try. */
  const solved = ref(0)
  const firstTry = ref(0)
  /** @type {import('vue').ShallowRef<import('../types').RewardResult | null>} */
  const result = shallowRef(null)
  /** The finished game couldn't be sent (offline); it waits in the outbox. */
  const savedLater = ref(false)
  let startedAt = 0
  let gameStartedAt = 0
  let active = true
  /** Rounds in a row solved at the first try. */
  let combo = 0

  const round = computed(() => session.value?.rounds[index.value] ?? null)
  const total = computed(() => session.value?.rounds.length ?? 0)

  const idle = useIdleHelp({
    ms: config.timing.idleHelpMs,
    enabled: () => Boolean(session.value) && !session.value.no_idle && !finished.value,
    // A little life before the full help: a hop, no speech, so it never talks over the child.
    onFidget: () => {
      if (!round.value || locked.value || guide.talking) return
      guide.hop()
    },
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
      solved.value = 0
      combo = 0
      firstTry.value = 0
      result.value = null
      savedLater.value = false
      gameStartedAt = Date.now()
      // The first prompt is requested right away by the player; warm what comes after it.
      const feedback = [...recordings.keysStartingWith('praise'), ...recordings.keysStartingWith('retry')]
      guide.preload([...feedback.map(rec => ({ rec })), ...(round.value ? roundItems(round.value) : [])])
      // The game's introduction only the first time the child plays it; otherwise straight to the question.
      startRound(Boolean(fresh.first_time))
    } catch (e) {
      error.value = errorMessage(e, t('game.loadFailed'))
    } finally {
      loading.value = false
    }
  }

  function startRound(withIntro = false) {
    tries.value = 0
    locked.value = false
    startedAt = Date.now()
    speakPrompt({ withIntro })
    idle.arm()
    const upcoming = session.value.rounds[index.value + 1]
    if (upcoming) guide.preload(roundItems(upcoming))
  }

  /**
   * Says the round's prompt (or `parts` instead); `promptDone` turns true afterwards.
   * `slow: true` says it at a reduced rate, for a child who wants it repeated more clearly.
   */
  async function speakPrompt({ withIntro = false, parts, slow = false } = {}) {
    const current = round.value
    if (!current) return
    const at = index.value
    promptDone.value = false
    const items = withIntro ? [{ rec: `intro_${game}`, alt: session.value.intro }] : []
    items.push(...(parts ?? promptItems(current)))
    await guide.speak(items, { caption: current.prompt.text, slow })
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
  async function answer({ correct, say, tries: graded }) {
    if (locked.value) return
    // Self-graded wins (puzzle, memory, sort) replace the count of wrong answers.
    tries.value = correct && graded ? graded : tries.value + 1
    if (!correct) {
      combo = 0
      buzz([18, 60, 18])
      guide.comfort()
      guide.speak(feedback('retry', say))
      return
    }
    locked.value = true
    idle.cancel()
    stars.value++
    solved.value++
    if (tries.value === 1) firstTry.value++
    // Right at the first try again and again: the chime climbs and the burst grows.
    combo = tries.value === 1 ? combo + 1 : 0
    guide.celebrate()
    chime(combo)
    buzz([12, 50, 20])
    burst(lastTouch(), combo >= 3 ? { pieces: 18, reach: 110 } : undefined)
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
      const saved = await recordAttempt(toValue(childId), {
        game,
        content_item_id: played.content_item_id,
        level: current.level,
        correct,
        tries: correct ? tries.value : Math.max(tries.value, 1),
        duration_ms: Date.now() - startedAt,
      })
      stars.value = saved.stars
      const remaining = current.rounds.length - index.value - 1
      if (saved.level !== current.level && remaining > 0) {
        const fresh = await fetchSession(toValue(childId), game)
        await preloadEngines(fresh.rounds.map(r => r.engine))
        // From the new session's second round on: its first one carries the how-to, already heard.
        current.rounds.splice(index.value + 1, remaining, ...stamp(fresh.rounds.slice(1, remaining + 1)))
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
      finish()
      return
    }
    index.value++
    startRound()
  }

  /** Last round done: celebrate, record the game, then announce what it earned. */
  async function finish() {
    finished.value = true
    idle.cancel()
    guide.party = true
    guide.setMood('happy')
    const played = session.value
    const earned = await rewards
      .complete(toValue(childId), {
        game,
        level: played.level,
        rounds: total.value,
        correct: solved.value,
        first_try: firstTry.value,
        duration_ms: Date.now() - gameStartedAt,
      })
      .catch(error => {
        // Offline: the game still ends happily; the outbox uploads it later.
        savedLater.value = isNetworkError(error)
        return null
      })
    if (!active || session.value !== played) return
    result.value = earned
    guide.speak([
      { rec: 'finish', alt: t('game.finishSpeech', { count: solved.value }) },
      ...rewardSpeech(earned, rewards.level?.number),
    ])
  }

  onScopeDispose(() => {
    active = false
    guide.reset()
  })

  return {
    session,
    round,
    index,
    total,
    locked,
    promptDone,
    finished,
    loading,
    stars,
    solved,
    result,
    savedLater,
    error,
    load,
    speakPrompt,
    answer,
    skip,
  }
}
