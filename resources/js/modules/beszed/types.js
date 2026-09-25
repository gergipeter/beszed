/**
 * API contract of the Beszéd module, written as JSDoc so editors still offer
 * completion and checks without TypeScript. Nothing here runs; reference a type
 * with `@type {import('../types').Session}`.
 *
 * @typedef {'choice' | 'sequence' | 'tapcount' | 'trace' | 'judged'} EngineName
 *
 * @typedef {object} Prompt
 * @property {string} text      Shown as caption and spoken.
 * @property {string[]} [parts] Spoken one by one (natural pauses), e.g. the Papagáj word list.
 *
 * @typedef {object} Round
 * @property {string} key
 * @property {EngineName} engine
 * @property {number | null} content_item_id
 * @property {Prompt} prompt
 * @property {ChoiceData | SequenceData | TapCountData | TraceData | JudgedData} data
 *
 * @typedef {object} Session
 * @property {string} game
 * @property {number} level
 * @property {string} intro
 * @property {boolean} no_idle
 * @property {number} stars
 * @property {Round[]} rounds
 *
 * @typedef {object} GameMeta
 * @property {string} id
 * @property {string} name
 * @property {string} emoji
 * @property {string} skill
 * @property {string} color
 * @property {number} rounds
 * @property {boolean} noIdle
 *
 * @typedef {{ key: string, label: string, text: string }} Line  A line a parent can record.
 *
 * @typedef {object} Meta
 * @property {GameMeta[]} games
 * @property {string[]} praise
 * @property {string[]} retry
 * @property {Line[]} lines
 * @property {boolean} serverTts
 *
 * @typedef {object} Option
 * @property {string} id
 * @property {string} [emoji]
 * @property {string[]} [emojis]
 * @property {string} [label]
 * @property {string} [scene]  Relation key rendered by SceneView (folott, alatt…).
 * @property {string} [say]
 *
 * @typedef {{ emoji: string, label?: string, say?: string, highlight?: boolean }} Stimulus
 *
 * @typedef {object} ChoiceData
 * @property {Stimulus} [stimulus]
 * @property {string[]} [sequence]
 * @property {'two' | 'three' | 'four'} layout
 * @property {'plates' | 'speakers'} [variant]
 * @property {Option[]} options
 * @property {string} answer
 * @property {string} [onCorrect]
 * @property {string | Record<string, string>} [onWrong]
 *
 * @typedef {object} SequenceData
 * @property {string[]} order
 * @property {Option[]} grid
 * @property {string} [onCorrect]
 * @property {string[]} [replayParts]
 *
 * @typedef {object} TapCountData
 * @property {'drum' | 'basket'} mode
 * @property {number} target
 * @property {Stimulus} [stimulus]
 * @property {string[]} [help]
 * @property {string} [emoji]
 * @property {string} [name]
 * @property {string} [accusative]
 * @property {number} [pool]
 * @property {string} [onCorrect]
 *
 * @typedef {{ path: 'wave' | 'loops' | 'zigzag' | 'arches', onCorrect?: string }} TraceData
 *
 * @typedef {{ text: string, chunks: string[], emoji: string, levelLabel: string }} JudgedData
 *
 * @typedef {object} AnswerEvent
 * @property {boolean} correct
 * @property {string} [say]  Feedback sentence; without it the runner picks a praise/retry line.
 *
 * @typedef {object} AttemptBody
 * @property {string} game
 * @property {number | null} content_item_id
 * @property {number} level
 * @property {boolean} correct
 * @property {number} tries
 * @property {number} duration_ms
 *
 * @typedef {object} GameProgress
 * @property {string} id
 * @property {string} name
 * @property {string} emoji
 * @property {string} skill
 * @property {number} rounds
 * @property {number | null} firstTryRate
 * @property {number | null} solvedRate
 * @property {number | null} level
 * @property {number | null} maxLevel
 * @property {string | null} lastPlayed
 *
 * @typedef {object} ProgressReport
 * @property {{ id: number, name: string }} child
 * @property {string} since
 * @property {GameProgress[]} games
 */

export {}
