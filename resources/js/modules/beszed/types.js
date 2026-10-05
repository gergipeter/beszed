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
 * @typedef {{ id: string, name: string, emoji: string, color: string, level: number | null, maxLevel: number | null, firstTryRate: number | null, state: 'new' | 'learning' | 'mastered', held: boolean, lastPlayed: string | null }} JourneyStep
 * @typedef {{ key: string, label: string, emoji: string, steps: JourneyStep[], mastered: number, total: number, next: string | null }} JourneyArea
 * @typedef {{ areas: JourneyArea[], recommended: { area: string, game: string } | null }} Journey
 *
 * @typedef {object} GameMeta
 * @property {string} id
 * @property {string} name
 * @property {string} emoji
 * @property {string} skill
 * @property {string} color
 * @property {'simple' | 'advanced'} tier  Hub group: the meadow (simple) or the enchanted forest (advanced).
 * @property {string} [zone]  Where the game stands on the garden map (meadow, forest, sound, letters, world); by tier when absent.
 * @property {string} stage  The scene it's played in (components/game/GameStage.vue).
 * @property {number} rounds
 * @property {boolean} noIdle
 * @property {number | null} [freeMaxLevel]  top level a free account plays in this game
 * @property {{ id: string, name: string, emoji: string }[]} [categories]  picture themes to pick before playing (Kirakó)
 * @property {string} [pickPrompt]  what Csillám asks above those themes (default: Kirakó's question)
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
 * @property {number | null} freeMaxLevel  the free plan's top game level; null = no cap
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
 * @typedef {{ path: 'wave' | 'loops' | 'zigzag' | 'arches' | 'steps' | 'hills', difficulty?: number, onCorrect?: string }} TraceData
 *
 * @typedef {{ text: string, chunks: string[], emoji: string, levelLabel: string }} JudgedData
 *
 * @typedef {object} PuzzleData
 * @property {string} emoji     The picture.
 * @property {number} cols
 * @property {number} rows
 * @property {number[]} pieces  pieces[position] = piece lying there; solved when pieces[i] === i.
 * @property {string} [prop]       a fairy tale's thing beside the figure (Hófehérke's apple)
 * @property {string} [scene]      the drawn scene behind it (SceneBackdrop)
 * @property {number} [previewMs]  high levels: the example fades after this long
 * @property {string} [levelLabel] "12. pálya"
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
 * Grid games (engine grid). Cells are numbered row by row: index = row × cols + col.
 * @typedef {object} GridMazeData  Labirintus: drag the hero through a perfect maze to its goal.
 * @property {'maze'} mode
 * @property {number} cols
 * @property {number} rows
 * @property {number[]} walls      Per cell, a bitmask of its walls: 1 up, 2 right, 4 down, 8 left.
 * @property {number} start
 * @property {number} goal
 * @property {number[]} path       The one way from start to goal (cells, start first).
 * @property {number | null} star  Level 3: a star on the way to pick up.
 * @property {string} hero         Picture that walks.
 * @property {string} target       Picture at the goal.
 * @property {[number, number]} grade  Wrong turns (+ half the bumps) for tries 1 / 2; more = 3.
 * @property {number} hintAfter    Wrong turns before footprints show the next steps.
 * @property {string} onCorrect
 * @property {string} onStar
 * @property {string} onBump       Said at the first bump into a wall.
 * @property {string} onDeadEnd    Said the first time the hero gets into a dead end.
 *
 * @typedef {{ id: 'up' | 'down' | 'left' | 'right', label: string, say: string }} GridMove
 * @typedef {object} GridProgramData  Kis robot: arrows move the robot, at once (direct) or as a program.
 * @property {'program'} mode
 * @property {boolean} direct      Each arrow moves at once (level 1); otherwise commands go into a strip.
 * @property {number} maxSteps     Commands that fit the strip (0 when direct).
 * @property {number} cols
 * @property {number} rows
 * @property {number} start
 * @property {number} goal
 * @property {number[]} blocks     Obstacle cells.
 * @property {number} best         Length of the shortest way.
 * @property {number[]} path       One shortest way (cells, start first), shown as a hint.
 * @property {string[]} solution   Its moves.
 * @property {string} hero
 * @property {string} target
 * @property {string} obstacle
 * @property {GridMove[]} moves    The four arrows, with the word each one says.
 * @property {[number, number]} grade  Mistakes for tries 1 / 2 (direct: bumps + half the wasted steps; program: failed runs).
 * @property {number} hintAfter
 * @property {string} onCorrect
 * @property {string} onBumpBlock
 * @property {string} onBumpEdge
 * @property {string} onShort      The program ended before the goal.
 *
 * @typedef {object} ColorFill  One part to colour in a step.
 * @property {string} region       Region id of the picture (engines/color/pictures.js).
 * @property {string} color        Paint id: piros, narancs, sarga, zold, kek, lila, rozsaszin, barna, szurke, fekete.
 * @property {string} wrongColor   Said when this part is tapped with another paint.
 * @property {string} wrongPart    Said when another part is tapped with this paint.
 *
 * @typedef {object} ColorStep  One sentence of Csillám: one part (levels 1–2) or two (level 3).
 * @property {string} say          The instruction ("Színezd a tetőt pirosra!").
 * @property {string} [lead]       Said when the step follows another one ("Szép! Most …").
 * @property {ColorFill[]} fills   In any order.
 *
 * @typedef {object} ColorData  Színező: colour a line picture as told, then freely.
 * @property {string} picture      Picture id in engines/color/pictures.js.
 * @property {string} name
 * @property {Object<string, string>} regions   Region id → its Hungarian name (accessible labels).
 * @property {Object<string, string>} colors    Paint id → its Hungarian name.
 * @property {string[]} pots       The paints offered during the steps, in paint-box order.
 * @property {string[]} freePots   The paints offered for free colouring.
 * @property {ColorStep[]} steps
 * @property {string} pickFirst    Said when a part is tapped before any paint.
 * @property {string} free         Said when the steps are done.
 * @property {string} [onCorrect]
 *
 * @typedef {object} SimonData  Állatkórus: repeat the tune.
 * @property {Option[]} pads       Four animals; each position has its own note and colour.
 * @property {string[]} order      Pad ids, in the order they sing.
 * @property {string} [onCorrect]
 * @property {string[]} [replayParts]
 *
 * @typedef {object} SayData  Hanggyakorló: say the picture's name (or a sentence), hear yourself, judge it.
 * @property {'repeat' | 'name'} mode  repeat: Csillám says it first; name: "Mi ez?", the word stays hidden until judged or helped.
 * @property {string} word         The word or sentence to say.
 * @property {string} emoji        Picture (1–3 emoji).
 * @property {string} sound        The sound practised, as named on the badge ("R", "Sz").
 * @property {string} where        Where the sound is ("a szó elején", "mondatban").
 * @property {string | null} model 🔊 says this; null (naming) = the question again.
 * @property {string[]} slow       🐢: said one by one (syllables, or a sentence's words), then whole.
 * @property {string[]} retry      Said after "Még gyakorlom": slowly, as the model.
 * @property {string} onCorrect
 * @property {string} onSkip
 * @property {number} skipAfter    "Tovább" shows after this many practice tries.
 *
 * @typedef {object} VoiceData  Fújóka (mode blow) and Hangrepülő (sustain, pitch): the microphone plays the round.
 * @property {'blow' | 'sustain' | 'pitch'} mode
 * @property {{ puffs?: number, holdMs?: number, softMs?: number, stepMs?: number, ms?: number, continuous?: boolean }} [target]
 *   blow: puffs (short blows) · holdMs (one long blow) · softMs (a gentle blow) · stepMs (each soft/strong step);
 *   sustain: ms of sound, `continuous` = without stopping (the flyer falls back after a pause).
 * @property {'candles' | 'dandelion' | 'boat' | 'pinwheel' | 'bubbles' | 'feather'} [scene]  blow: the picture
 * @property {'puffs' | 'long' | 'gentle' | 'alternate'} [kind]  blow
 * @property {{ strength: 'soft' | 'strong', say: string }[]} [steps]  blow, alternate: said as each step starts
 * @property {string} [friend]     blow: who is in the picture
 * @property {string} [shown]      sustain/pitch: the sound as written ("sssz"); never spoken (the voice would spell it)
 * @property {string} [sound]
 * @property {string} [helper]
 * @property {string} [emoji]      the helper's picture
 * @property {string} [sayModel]   said when the sound's chip is tapped ("Sziszegj hosszan, mint a kígyó.")
 * @property {'rocket' | 'bee' | 'balloon'} [flyer]  sustain/pitch: what flies (FlyScene draws it and its goal)
 * @property {{ x: number, high: boolean }[]} [stars]  pitch: left to right
 * @property {Record<string, string>} hints  voiced · quiet · tooStrong · fell · high · low: Csillám's nudges, at most twice each
 * @property {string} onCorrect
 *
 * @typedef {{ emojis: string[], text?: string, avoid?: string[] }} CatchGoal  What to catch (pictures, or the sound's letter) and what not to.
 * @typedef {object} CatchItem  One bubble of the stream.
 * @property {string} id
 * @property {string} emoji
 * @property {string} name     Said as it appears in `hear` mode; the bubble's accessible name.
 * @property {boolean} target  To be caught.
 * @property {number} at       ms on the stream clock (from the start, not counting pauses).
 * @property {number} rise     ms to float from the bottom to the top.
 * @property {number} x        Lane, 0–1 across the sky.
 * @property {string} why      Said the first time this bubble is a mistake (caught though wrong, or let go though right).
 * @typedef {object} CatchData  Kapd el!: catch the bubbles that fit the rule, let the others go.
 * @property {'see' | 'hear'} mode  hear: each picture says its word as it appears.
 * @property {CatchGoal} goal
 * @property {number} need          Targets to catch to win the round.
 * @property {CatchItem[]} stream   In order of `at`.
 * @property {{ at: number, goal: CatchGoal, say: string }} [switch]  The rule turns round at `at`; later bubbles follow the new rule.
 * @property {[number, number]} grade  Mistakes up to [0] → tries 1, up to [1] → 2, more → 3.
 * @property {string} again         Said before the stream comes round once more (too few caught).
 * @property {string} onCorrect
 * @property {string} onEnd         The second round of the stream ended with too few caught: a gentle end.
 *
 * @typedef {object} HiddenItem  One picture of a Keresd meg! scene, in drawing order.
 * @property {string} id
 * @property {string} emoji
 * @property {boolean} target
 * @property {number} x        Centre, % of the (square) board's width.
 * @property {number} y        Centre, % of the board's height.
 * @property {number} size     Width, % of the board.
 * @property {number} rotate   Degrees.
 * @property {string} [say]    Said when tapped: the name of a found target, or why another picture isn't one (clue rounds).
 * @typedef {object} HiddenData  Keresd meg!: find every target in a busy picture.
 * @property {'garden' | 'sea' | 'kitchen' | 'sky' | 'forest' | 'room' | 'night' | 'snow'} backdrop
 * @property {number} seed          The layout's seed (the same seed lays out the same picture).
 * @property {HiddenItem[]} items
 * @property {number} count         Targets to find.
 * @property {{ emoji?: string, swatch?: string, sound?: string }} tray  What the tray shows: the target, or the clue's colour / letter.
 * @property {string[]} [counts]    Said as the targets are found ("Egy!", "Kettő!"), unless the item has its own `say`.
 * @property {string} [wrong]       Said on a wrong tap, unless the item has its own `say`.
 * @property {string} onCorrect
 *
 * @typedef {object} DotsDot
 * @property {number} x        0–1 across the square board.
 * @property {number} y        0–1 down.
 * @property {string} label    "1"… or a letter ("cs" is one dot).
 * @property {string} say      Said when the dot is joined ("Három!").
 * @property {string} hint     Said on a wrong tap while this dot is the next one ("A kettő után melyik jön?").
 * @typedef {object} DotsData  Pontról pontra: join the dots in order; the picture pops out.
 * @property {DotsDot[]} dots  In order; the outline closes from the last back to the first.
 * @property {string} emoji
 * @property {string} name
 * @property {boolean} pulse   The next dot is always shown (the youngest).
 * @property {string} onCorrect
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
 * @property {number} stars   correct answers this period
 * @property {number | null} firstTryRate
 * @property {number | null} solvedRate
 * @property {number | null} level
 * @property {number | null} maxLevel
 * @property {number | null} levelProgress   0-1 through [min, max] of the game's adaptive difficulty
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
 * @property {SoundReport} sounds
 * @property {string} narrative
 * @property {string[]} recommendations
 */

/**
 * How the child does on single sounds (app/Beszed/SoundProgress.php). Only sounds with
 * at least `minAttempts` answers in the period are in `items`; `attempts` counts all.
 * @typedef {object} SoundReport
 * @property {number} minAttempts
 * @property {number} attempts   answers about sounds this period, in `items` or not
 * @property {number} few        sounds played this period with too few answers to show
 * @property {string | null} strongest   key of the sound that goes best (needs another to compare with)
 * @property {string | null} weakest     key of the one to practise a little more (never a firm one)
 * @property {string | null} improved    key of the one that improved most
 * @property {string | null} [summary]   a few plain sentences about the three above
 * @property {string | null} [tip]       one short thing to do at home (parent's view only)
 * @property {SoundRow[]} items
 *
 * @typedef {object} SoundRow
 * @property {string} key
 * @property {'start' | 'contrast' | 'rhyme'} kind   first sound, sound pair (s – sz) or rhyme ending (-ó)
 * @property {string} label
 * @property {string[]} games    ids of the games that practise it, most answers first
 * @property {string[]} examples
 * @property {number} attempts
 * @property {number} firstTryRate
 * @property {number | null} previousRate
 * @property {'up' | 'flat' | 'down' | null} trend
 * @property {'strong' | 'growing' | 'practice' | 'noData'} band
 * @property {number} overallAttempts
 * @property {number | null} overallRate   since the beginning
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
 * @property {number} stars   correct answers across the area's games, this period
 * @property {number|null} firstTryRate   null below the minimum number of answers
 * @property {number|null} previousRate   the same-length period before
 * @property {'up'|'flat'|'down'|null} trend
 * @property {'strong'|'growing'|'practice'|'noData'} band
 */

export {}
