/**
 * API contract of the Beszéd module, written as JSDoc so editors still offer
 * completion and checks without TypeScript. Nothing here runs; reference a type
 * with `@type {import('../types').Session}`.
 *
 * @typedef {'choice' | 'sequence' | 'tapcount' | 'trace' | 'judged' | 'puzzle' | 'memory' | 'sort'} EngineName
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
 * @property {ChoiceData | SequenceData | TapCountData | TraceData | JudgedData | PuzzleData | MemoryData | SortData} data
 *
 * @typedef {object} Session
 * @property {string} game
 * @property {number} level
 * @property {string} intro
 * @property {boolean} first_time  never played before: Csillám introduces the game
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
 * @property {'simple' | 'advanced'} tier  Hub group: the meadow (simple) or the enchanted forest (advanced).
 * @property {string} stage  The scene it's played in (components/game/GameStage.vue).
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
 * @property {boolean} serverStt
 * @property {VoiceOption[]} voices
 * @property {{ min: number, max: number }} rateRange
 * @property {{ min: number, max: number }} pitchRange
 *
 * @typedef {{ id: string, label: string, gender: 'female' | 'male' }} VoiceOption
 *
 * @typedef {object} VoiceSettings
 * @property {string | null} voice
 * @property {number | null} rate
 * @property {number | null} pitch
 * @property {boolean} preferServerTts
 * @property {boolean} muted
 *
 * @typedef {object} Option
 * @property {string} id
 * @property {string} [emoji]
 * @property {string[]} [emojis]
 * @property {string} [label]
 * @property {string} [scene]  Relation key rendered by SceneView (folott, alatt…).
 * @property {string} [say]
 *
 * @typedef {{ emoji: string, label?: string, say?: string, highlight?: boolean, silhouette?: boolean }} Stimulus
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
 * @property {Guess} [guess]  Csillám has a go first (App\Beszed\CsillamGuess).
 *
 * @typedef {object} Guess  Csillám points at an answer (sometimes wrongly on purpose) and asks the child.
 * @property {string} id           the option she picks
 * @property {string} ask          "Szerintem ez az. Igazam van?"
 * @property {string} confirmed    the child agreed, and she was right
 * @property {string} caught       the child caught her mistake (then picks the right one)
 * @property {string} agreedWrong  the child agreed with a wrong guess
 * @property {string} deniedRight  the child said no to a right guess
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
 * @typedef {{ path: 'wave' | 'loops' | 'zigzag' | 'arches' | 'steps' | 'hills', onCorrect?: string }} TraceData
 *
 * @typedef {{ text: string, chunks: string[], emoji: string, levelLabel: string }} JudgedData
 *
 * @typedef {object} PuzzleData
 * @property {string} emoji     The picture.
 * @property {number} cols
 * @property {number} rows
 * @property {number[]} pieces  pieces[position] = piece lying there; solved when pieces[i] === i.
 * @property {string} [onCorrect]
 *
 * @typedef {{ id: string, pair: string, emoji: string, label: string }} MemoryCard
 * @typedef {{ cards: MemoryCard[], onCorrect?: string }} MemoryData
 *
 * @typedef {{ id: string, emoji: string, label: string }} SortBin
 * @typedef {{ id: string, emoji: string, label: string, bin: string, wrong: string }} SortItem
 * @typedef {{ bins: SortBin[], items: SortItem[], onCorrect?: string }} SortData
 *
 * @typedef {object} DifferenceData  Spot the difference: two panels, alike but for cell `diff`.
 * @property {number} cols
 * @property {number} rows
 * @property {string[]} left   One picture per cell, row by row.
 * @property {string[]} right
 * @property {number} diff
 * @property {string} [onCorrect]
 * @property {string} [onWrong]
 *
 * @typedef {{ id: string, emoji: string, label?: string, scale?: number }} OrderItem  scale: 0–1 size (seriation).
 * @typedef {object} OrderData  Tap the items in `order` (sizes, story steps).
 * @property {OrderItem[]} items   Shuffled.
 * @property {string[]} order
 * @property {boolean} [arrows]    Show "first → last" arrows between the slots.
 * @property {string} wrong        Said on a wrong tap.
 * @property {string} [onCorrect]
 *
 * @typedef {object} VanishData  "Mi tűnt el?": look, hide, find the missing one.
 * @property {Option[]} items      The pictures to remember.
 * @property {string} missing      The id that is gone when they come back.
 * @property {Option[]} options    The missing one + pictures that weren't shown.
 * @property {number} lookMs       Looking time after the prompt.
 * @property {string} question     Said when the pictures come back.
 * @property {string} [onCorrect]
 * @property {string} [onWrong]
 *
 * @typedef {object} DirectionsData  Csináld, amit mondok!: tap what was said.
 * @property {Option[]} grid
 * @property {string[][]} steps    Picture ids to tap, step by step; one step's pictures in any order.
 * @property {string} wrong        Said on a wrong tap, before the direction is repeated.
 * @property {string} [onCorrect]
 *
 * @typedef {object} SimonData  Állatkórus: repeat the tune.
 * @property {Option[]} pads       Four animals; each position has its own note and colour.
 * @property {string[]} order      Pad ids, in the order they sing.
 * @property {string} [onCorrect]
 * @property {string[]} [replayParts]
 *
 * @typedef {object} AnswerEvent
 * @property {boolean} correct
 * @property {string} [say]  Feedback sentence; without it the runner picks a praise/retry line.
 * @property {number} [tries] Self-graded win (1–3) for engines where mistakes are part of play.
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
 * @property {number} sessions  Games finished in the period.
 * @property {number} rounds
 * @property {number | null} firstTryRate
 * @property {number | null} solvedRate
 * @property {number | null} level
 * @property {number | null} maxLevel
 * @property {string | null} lastPlayed
 *
 * @typedef {{ number: number, stars: number, from: number, to: number, progress: number }} PlayerLevel
 * @typedef {{ id: string, name: string, emoji: string, hint: string, earned_at: string | null }} Badge
 * @typedef {{ id: string, name: string, emoji: string, level: number, slot: 'head' | 'face' | 'extra', unlocked: boolean }} Accessory
 * @typedef {{ id: string, name: string, emoji: string }} Background
 * @typedef {{ badge: string, x: number, y: number, rotate: number, scale: number }} PlacedSticker  x/y in % of the board.
 * @typedef {{ background: string | null, stickers: PlacedSticker[] }} Scene
 *
 * @typedef {object} RewardSummary
 * @property {number} stars
 * @property {PlayerLevel} level
 * @property {{ days: number, today: boolean, recent: { date: string, played: boolean }[] }} streak
 * @property {{ done: number, goal: number }} daily
 * @property {number} sessions
 * @property {Record<string, number>} medals  game id → 0–3
 * @property {Badge[]} badges
 * @property {Record<'head' | 'face' | 'extra', string>} worn  slot → worn accessory id
 * @property {Accessory[]} accessories
 * @property {Scene} scene
 * @property {Background[]} backgrounds
 *
 * @typedef {object} RewardResult  What one finished game changed.
 * @property {number} stars
 * @property {number} medal
 * @property {PlayerLevel} level_before
 * @property {boolean} level_up
 * @property {{ id: string, name: string, emoji: string }[]} new_badges
 * @property {Accessory[]} unlocked
 * @property {(DailyPath & { ticked: boolean, just_completed: boolean }) | null} daily_path  set when this game was a step of the day's path
 *
 * @typedef {object} DailyPath  "Mai kaland": Csillám's games for one day.
 * @property {string} day        local calendar day, "YYYY-MM-DD"
 * @property {string[]} games    game ids, in order
 * @property {string[]} done     the ones played that day
 * @property {boolean} completed
 *
 * @typedef {object} Share  A read-only progress link for the speech therapist.
 * @property {number} id
 * @property {string | null} label
 * @property {string} createdAt
 * @property {string} expiresAt
 * @property {string | null} revokedAt
 * @property {string | null} lastViewedAt
 * @property {number} views
 * @property {boolean} active
 *
 * @typedef {object} ProgressReport
 * @property {{ id: number, name: string, age: string | null }} child
 * @property {string} since
 * @property {number} days
 * @property {GameProgress[]} games
 * @property {SkillArea[]} areas
 * @property {string} narrative
 * @property {string[]} recommendations
 */

/**
 * A skill area (config/beszed_skills.php): its games' answers pooled.
 * @typedef {object} SkillArea
 * @property {string} key
 * @property {string} label
 * @property {string} emoji
 * @property {boolean} difer      One of the DIFER areas (approximate mapping)
 * @property {string[]} games
 * @property {number} sessions
 * @property {number} rounds
 * @property {number|null} firstTryRate   null below the minimum number of answers
 * @property {number|null} previousRate   the same-length period before
 * @property {'up'|'flat'|'down'|null} trend
 * @property {'strong'|'growing'|'practice'|'noData'} band
 */

export {}
