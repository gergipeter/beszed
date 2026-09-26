<?php

use App\Beszed\Rounds;

return [

    /*
    | Games of the "Beszéd & DIFER" module.
    | - factory:  builds the rounds of one session from content items
    | - rounds:   rounds per session
    | - adaptive: level range + how many clean wins move up (null = fixed level 1)
    | - no_idle:  Csillám does not jump in with help after 20 s (parent is talking)
    | - tier:     hub group: simple (one tap, little to remember) or advanced
    |             (sounds, rhymes, memory, reasoning: more attention needed)
    */
    'games' => [
        'zs' => [
            'name' => 'Zümi vagy Susi?', 'emoji' => '🐝', 'skill' => 'Hallod a zs-t és az s-t?', 'color' => '#FFE27A', 'tier' => 'advanced',
            'factory' => Rounds\ZsRounds::class, 'rounds' => 8,
            'intro' => 'Most hangokat figyelünk! A zs úgy zümmög, mint a méhecske. Az s úgy susog, mint amikor csendet kérünk. Figyelj jól! Kezdjük!',
        ],
        'szotag' => [
            'name' => 'Dobolós szavak', 'emoji' => '🥁', 'skill' => 'Szótagolás dobbal', 'color' => '#FFB8A8', 'tier' => 'simple',
            'factory' => Rounds\SzotagRounds::class, 'rounds' => 8,
            'intro' => 'Dobolni fogunk! Minden szótagra üss egyet a dobra. Például: ci… ca… Ez két dobbanás! Kezdjük!',
        ],
        'kezdo' => [
            'name' => 'Első hang', 'emoji' => '👂', 'skill' => 'Mivel kezdődik a szó?', 'color' => '#BDE7C5', 'tier' => 'advanced',
            'factory' => Rounds\KezdoRounds::class, 'rounds' => 8,
            'intro' => 'Most a szavak elejét figyeljük. Hallgasd jól, melyik szó kezdődik ugyanúgy!',
        ],
        'hol' => [
            'name' => 'Hol van?', 'emoji' => '🧸', 'skill' => 'Alatt, fölött, mögött…', 'color' => '#C9D7FF', 'tier' => 'simple',
            'factory' => Rounds\HolRounds::class, 'rounds' => 8,
            'intro' => 'Bújócskázunk! Keresd meg, hol bújt el a kis barátunk!',
        ],
        'szamol' => [
            'name' => 'Számolós', 'emoji' => '🍎', 'skill' => 'Több, kevesebb, pont ennyi', 'color' => '#FFD1E8', 'tier' => 'simple',
            'factory' => Rounds\SzamolRounds::class, 'rounds' => 8,
            'intro' => 'Számoljunk együtt! Egy, kettő, három… Készen állsz?',
        ],
        'okoska' => [
            'name' => 'Okoska', 'emoji' => '💡', 'skill' => 'Mi jön ezután? Mi nem illik?', 'color' => '#D8C8FF', 'tier' => 'advanced',
            'factory' => Rounds\OkoskaRounds::class, 'rounds' => 8,
            'intro' => 'Most okoskodunk! Nézd meg jól a képeket!',
        ],
        'hallgasd' => [
            'name' => 'Hallgasd meg!', 'emoji' => '🎧', 'skill' => 'Szavak jelentése, hallás alapján', 'color' => '#A8E6CF', 'tier' => 'simple',
            'factory' => Rounds\HallgasdRounds::class, 'rounds' => 8,
            'intro' => 'Most figyelj jól! Kimondok egy szót, te pedig megkeresed a hozzá illő képet. Kezdjük!',
        ],
        'ikerhangok' => [
            'name' => 'Ikerhangok', 'emoji' => '👯', 'skill' => 'Hasonló szavak megkülönböztetése', 'color' => '#FFB8D9', 'tier' => 'advanced',
            'factory' => Rounds\IkerhangokRounds::class, 'rounds' => 8,
            'intro' => 'Most nagyon hasonló szavakat hallasz! Figyelj jól, melyiket mondtam, és koppints a jó képre!',
        ],
        'papagaj' => [
            'name' => 'Papagáj', 'emoji' => '🦜', 'skill' => 'Szavak sorban visszamondva', 'color' => '#B8ECE6', 'tier' => 'advanced',
            'factory' => Rounds\PapagajRounds::class, 'rounds' => 6,
            'adaptive' => [
                'min' => 2, 'max' => 6, 'start' => 3, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 2, '5-6' => 3, '7+' => 4],
            ],
            'intro' => 'Játsszunk papagájosat! Én mondok szavakat, te pedig visszamondod, pont úgy, mint egy papagáj. Utána megmutatod a képeken!',
        ],
        'mondd' => [
            'name' => 'Mondd utánam', 'emoji' => '🗣️', 'skill' => 'Mondatismétlés, szülővel', 'color' => '#FFC9A8', 'tier' => 'simple',
            'factory' => Rounds\MonddRounds::class, 'rounds' => 6, 'no_idle' => true,
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Most mondatokat mondok. Figyelj jól, és mondd utánam! Anya vagy apa is segít.',
        ],
        'melyik' => [
            'name' => 'Melyik mondja szépen?', 'emoji' => '🐻', 'skill' => 'Magyaros mondatok', 'color' => '#E3F0A8', 'tier' => 'advanced',
            'factory' => Rounds\MelyikRounds::class, 'rounds' => 8,
            'intro' => 'Brumi és Nyuszi mesél. Az egyikük szépen mondja, a másik kicsit összekeveri. Segíts eldönteni, ki mondta szépen!',
        ],
        'ceruza' => [
            'name' => 'Méhecske útja', 'emoji' => '✏️', 'skill' => 'Vonalvezetés ujjal', 'color' => '#FFE0B8', 'tier' => 'simple',
            'factory' => Rounds\CeruzaRounds::class, 'rounds' => 4,
            'intro' => 'A méhecske virágot keres. Segíts neki az ujjaddal!',
        ],
        'kirako' => [
            'name' => 'Kirakó', 'emoji' => '🧩', 'skill' => 'Képkirakó, formaérzék', 'color' => '#FFDAC1', 'tier' => 'simple',
            'factory' => Rounds\KirakoRounds::class, 'rounds' => 3,
            // level = grid: 1 → 2×2, 2 → 3×2, 3 → 3×3 pieces
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Összekeveredtek a kép darabjai! Koppints két darabra, és helyet cserélnek. Rakd ki a képet!',
        ],
        'parkereso' => [
            'name' => 'Párkereső', 'emoji' => '🃏', 'skill' => 'Emlékezet és szókincs', 'color' => '#C7E9FF', 'tier' => 'simple',
            'factory' => Rounds\ParkeresoRounds::class, 'rounds' => 2,
            // level = number of pairs; can also include difficulty: easy, medium, hard
            'adaptive' => [
                'min' => 3, 'max' => 6, 'start' => 3, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 3, '5-6' => 4, '7+' => 5],
            ],
            'intro' => 'Kártyázzunk! Fordíts fel két kártyát. Ha egyformák, megtaláltad a párt. Jegyezd meg, mi hol van!',
        ],
        'rimparok' => [
            'name' => 'Rímpárok', 'emoji' => '🎶', 'skill' => 'Rímelő szavak megjegyzése', 'color' => '#F0D9FF', 'tier' => 'advanced',
            'factory' => Rounds\RimparokRounds::class, 'rounds' => 2,
            // level = number of rhyme pairs
            'adaptive' => [
                'min' => 2, 'max' => 4, 'start' => 2, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 2, '5-6' => 3, '7+' => 4],
            ],
            'intro' => 'Kártyázzunk! De most nem ugyanaz a párja egy kártyának, hanem az, amelyik rímel rá. Fordíts fel kettőt, és figyelj a hangjukra!',
        ],
        'arnyek' => [
            'name' => 'Árnyékkereső', 'emoji' => '👤', 'skill' => 'Alak és forma felismerése', 'color' => '#D6DCE4', 'tier' => 'simple',
            'factory' => Rounds\ArnyekRounds::class, 'rounds' => 8,
            'intro' => 'Nézd, csak az árnyékuk látszik! Találd ki, kinek az árnyéka!',
        ],
        'rimelo' => [
            'name' => 'Rímelő', 'emoji' => '🎵', 'skill' => 'Rímek, a szavak vége', 'color' => '#F8C8DC', 'tier' => 'advanced',
            'factory' => Rounds\RimeloRounds::class, 'rounds' => 8,
            'intro' => 'Rímeljünk! A ló és a hó rímel, mert ugyanúgy végződik. Figyelj a szavak végére!',
        ],
        'valogato' => [
            'name' => 'Válogató', 'emoji' => '🧺', 'skill' => 'Csoportosítás, fogalmak', 'color' => '#D4F0C0', 'tier' => 'simple',
            'factory' => Rounds\ValogatoRounds::class, 'rounds' => 3,
            // level = pictures per round: 1 → 4, 2 → 6, 3 → 8
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Rendet rakunk! Minden kép a saját kosarába kerül. Koppints a jó kosárra!',
        ],
        'kulonbseg' => [
            'name' => 'Mi a különbség?', 'emoji' => '🔍', 'skill' => 'Két kép, egy különbség', 'color' => '#CDE7FF', 'tier' => 'simple',
            'factory' => Rounds\KulonbsegRounds::class, 'rounds' => 6,
            // level = grid on each panel: 1 → 2×2, 2 → 3×2, 3 → 3×3 (and a look-alike swapped in)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Nézd, két kép! Majdnem egyformák, de egy helyen más van rajtuk. Keresd meg, és koppints rá!',
        ],
        'nagysag' => [
            'name' => 'Kicsitől a nagyig', 'emoji' => '🪆', 'skill' => 'Sorba rendezés nagyság szerint', 'color' => '#FFE9A8', 'tier' => 'simple',
            'factory' => Rounds\NagysagRounds::class, 'rounds' => 6,
            // level = sizes to order: 1 → 3, 2 → 4, 3 → 5 (and every other round biggest first)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Rakjunk rendet! Koppints a képekre sorban: először a legkisebbre, a végén a legnagyobbra!',
        ],
        'erzelmek' => [
            'name' => 'Hogy érzi magát?', 'emoji' => '😊', 'skill' => 'Érzelmek felismerése', 'color' => '#FFD6C9', 'tier' => 'simple',
            'factory' => Rounds\ErzelmekRounds::class, 'rounds' => 8,
            'intro' => 'Az arcunk megmutatja, hogy érezzük magunkat: vidámak vagyunk, szomorúak, vagy éppen mérgesek. Segíts kitalálni!',
        ],
        'mitunt' => [
            'name' => 'Mi tűnt el?', 'emoji' => '🎩', 'skill' => 'Képek megjegyzése', 'color' => '#E6D9FF', 'tier' => 'advanced',
            'factory' => Rounds\MituntRounds::class, 'rounds' => 6,
            // level = pictures to remember (3–6)
            'adaptive' => [
                'min' => 3, 'max' => 6, 'start' => 3, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 3, '5-6' => 4, '7+' => 5],
            ],
            'intro' => 'Varázsoljunk! Jegyezd meg jól a képeket. Utána az egyik eltűnik, te pedig kitalálod, melyik volt az!',
        ],
        'tortenet' => [
            'name' => 'Mi történt előbb?', 'emoji' => '🐣', 'skill' => 'Történetek sorrendje', 'color' => '#D2F2DF', 'tier' => 'advanced',
            'factory' => Rounds\TortenetRounds::class, 'rounds' => 5,
            // level = steps of a story: 1 → 3, 2 → 4
            'adaptive' => [
                'min' => 1, 'max' => 2, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Mesélek neked! Nézd meg a képeket, és rakd őket sorba: mi történt először, és mi a végén?',
        ],
        'korus' => [
            'name' => 'Állatkórus', 'emoji' => '🎼', 'skill' => 'Nézd, hallgasd, ismételd!', 'color' => '#C4EEF7', 'tier' => 'advanced',
            'factory' => Rounds\KorusRounds::class, 'rounds' => 5,
            // level = notes the choir sings (2–7)
            'adaptive' => [
                'min' => 2, 'max' => 7, 'start' => 2, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 2, '5-6' => 3, '7+' => 3],
            ],
            'intro' => 'Az állatok egymás után énekelnek. Figyeld, ki énekel, aztán koppints rájuk ugyanabban a sorrendben!',
        ],
        'utasitas' => [
            'name' => 'Csináld, amit mondok!', 'emoji' => '👆', 'skill' => 'Utasítások követése', 'color' => '#FFDDEE', 'tier' => 'advanced',
            'factory' => Rounds\UtasitasRounds::class, 'rounds' => 6,
            // level = kind of direction: 1 → one step, 2 → two steps / "all", 3 → three steps, "before", "not"
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Most jól figyelj! Mondok valamit, te pedig pontosan azt csinálod. Várd meg, amíg végigmondom, és csak utána koppints!',
        ],
    ],

    'praise' => [
        'Hűha! Ügyes vagy!', 'Igen! Megcsináltad!', 'Ez az! Pacsi!',
        'Szuper! Nagyon büszke vagyok rád!', 'Hurrá! Eltaláltad!', 'Nagyon jó! Te egy igazi okoska vagy!',
    ],
    'retry' => [
        'Hmm… nem egészen. Semmi baj, próbáljuk újra!', 'Majdnem! Nézd meg még egyszer!', 'Ó-ó! Nem baj, együtt kitaláljuk!',
    ],
    'lines' => [
        'greet' => ['Köszönés a kezdőlapon', 'Szia! Úgy örülök, hogy itt vagy! Mit játsszunk ma?'],
        'help' => ['Segítség, ha elakad', 'Segítek! Figyelj.'],
        'finish' => ['Játék vége', 'Hurrá! Nagyon ügyes voltál! Adj egy ötöst!'],
    ],

    'recordings' => [
        'disk' => env('BESZED_RECORDINGS_DISK', 'local'),
        'max_kb' => 5120,
    ],

    // Pronunciation Assessment (Mondd utánam): max spoken-attempt upload size.
    'pronunciation' => [
        'max_kb' => 2048,
    ],

    /*
    | Rewards. Stars (correct answers) raise the player level; finished games
    | build the daily streak and the daily goal; a game's best session earns
    | 1–3 medals; stickers (badges) follow the rules below; levels unlock
    | accessories for Csillám.
    */
    'rewards' => [
        // Day boundaries for the streak and the daily goal.
        'timezone' => env('BESZED_TIMEZONE', 'Europe/Budapest'),
        // Games to finish per day.
        'daily_goal' => 3,
        // Stars needed for level n: step · n · (n − 1) → 0, 10, 30, 60, 100, 150…
        'level_step' => 5,
        // Medals for a game = the best session's share of first-try answers.
        'medals' => [1 => 0.5, 2 => 0.75, 3 => 1.0],

        /*
        | rule: [type, ...args]
        |   sessions n · stars n · streak n · perfect n (flawless sessions)
        |   all_games (each game finished once) · game <id> n · daily_goal
        |   daily_path n ("Mai kaland" finished on n days)
        | ":goal" in a hint becomes daily_goal.
        | email: true → a milestone worth mailing the parent about (MilestoneEarned),
        |   if they haven't turned that off. Left off the common, small ones on purpose.
        */
        'badges' => [
            'first_game' => ['name' => 'Első játék', 'emoji' => '🎈', 'hint' => 'Játssz végig egy játékot!', 'rule' => ['sessions', 1]],
            'daily_goal' => ['name' => 'Napi cél', 'emoji' => '🎯', 'hint' => 'Játssz :goal játékot egy nap alatt!', 'rule' => ['daily_goal']],
            'perfect' => ['name' => 'Hibátlan', 'emoji' => '💎', 'hint' => 'Oldj meg egy egész játékot elsőre!', 'rule' => ['perfect', 1]],
            'streak_3' => ['name' => 'Három nap', 'emoji' => '🔥', 'hint' => 'Játssz három nap egymás után!', 'rule' => ['streak', 3], 'email' => true],
            'streak_7' => ['name' => 'Egész héten', 'emoji' => '🌈', 'hint' => 'Játssz hét nap egymás után!', 'rule' => ['streak', 7], 'email' => true],
            'stars_50' => ['name' => 'Csillaggyűjtő', 'emoji' => '⭐', 'hint' => 'Gyűjts 50 csillagot!', 'rule' => ['stars', 50]],
            'stars_200' => ['name' => 'Csillagszóró', 'emoji' => '🌟', 'hint' => 'Gyűjts 200 csillagot!', 'rule' => ['stars', 200], 'email' => true],
            'sessions_10' => ['name' => 'Kis bajnok', 'emoji' => '🏅', 'hint' => 'Játssz végig 10 játékot!', 'rule' => ['sessions', 10], 'email' => true],
            'sessions_30' => ['name' => 'Nagy bajnok', 'emoji' => '🏆', 'hint' => 'Játssz végig 30 játékot!', 'rule' => ['sessions', 30], 'email' => true],
            'explorer' => ['name' => 'Felfedező', 'emoji' => '🧭', 'hint' => 'Próbáld ki az összes játékot!', 'rule' => ['all_games'], 'email' => true],
            'puzzle' => ['name' => 'Kirakóbajnok', 'emoji' => '🖼️', 'hint' => 'Játssz ötször a Kirakóval!', 'rule' => ['game', 'kirako', 5]],
            'memory' => ['name' => 'Jó memória', 'emoji' => '🧠', 'hint' => 'Játssz ötször a Párkeresővel!', 'rule' => ['game', 'parkereso', 5]],
            'rhyme' => ['name' => 'Rímfaragó', 'emoji' => '🎶', 'hint' => 'Játssz ötször a Rímelővel!', 'rule' => ['game', 'rimelo', 5]],
            'speaker' => ['name' => 'Szószóló', 'emoji' => '🎤', 'hint' => 'Játssz ötször a Mondd utánammal!', 'rule' => ['game', 'mondd', 5]],
            'path_1' => ['name' => 'Kalandor', 'emoji' => '🗺️', 'hint' => 'Járd végig Csillám mai kalandját!', 'rule' => ['daily_path', 1]],
            'path_7' => ['name' => 'Kalandmester', 'emoji' => '🏕️', 'hint' => 'Járj végig hét napi kalandot!', 'rule' => ['daily_path', 7], 'email' => true],
            'memory_easy' => ['name' => 'Memória kezdő', 'emoji' => '🧠', 'hint' => 'Játssz a Párkereső könnyű szintjén!', 'rule' => ['game', 'parkereso', 1]],
            'memory_hard' => ['name' => 'Memória mester', 'emoji' => '🧠‍💨', 'hint' => 'Játssz a Párkereső nehéz szintjén és nyerj!', 'rule' => ['game', 'parkereso', 3]],
        ],

        /*
        | Csillám's wardrobe, unlocked at these player levels. Grouped into slots
        | (head, face, extra) so the child can wear one item per slot at once —
        | e.g. a hat AND glasses AND a flower, but not a hat and a crown together.
        */
        'accessories' => [
            'bow' => ['name' => 'Masni', 'emoji' => '🎀', 'level' => 2, 'slot' => 'extra'],
            'glasses' => ['name' => 'Napszemüveg', 'emoji' => '🕶️', 'level' => 3, 'slot' => 'face'],
            'flower' => ['name' => 'Virág', 'emoji' => '🌸', 'level' => 4, 'slot' => 'extra'],
            'hat' => ['name' => 'Varázskalap', 'emoji' => '🎩', 'level' => 5, 'slot' => 'head'],
            'crown' => ['name' => 'Korona', 'emoji' => '👑', 'level' => 7, 'slot' => 'head'],
        ],

        // Scene backgrounds for the sticker board; the child picks one, then places earned stickers on it.
        'backgrounds' => [
            'meadow' => ['name' => 'Rét', 'emoji' => '🌼', 'colors' => ['#CDEFAE', '#EAF7C9']],
            'sky' => ['name' => 'Éjszakai ég', 'emoji' => '🌙', 'colors' => ['#2B2A5C', '#4B3F80']],
            'castle' => ['name' => 'Kastély', 'emoji' => '🏰', 'colors' => ['#FFD9EA', '#D8C8FF']],
        ],
        // Stickers placed on the scene at once.
        'scene_max_stickers' => 24,
    ],
];
