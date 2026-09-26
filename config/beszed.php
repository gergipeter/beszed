<?php

use App\Beszed\Rounds;

return [

    /*
    | Games of the "Beszéd & DIFER" module.
    | - factory:  builds the rounds of one session from content items
    | - rounds:   rounds per session
    | - adaptive: level range + how many clean wins move up (null = fixed level 1)
    | - no_idle:  Csillám does not jump in with help after 20 s (parent is talking)
    */
    'games' => [
        'zs' => [
            'name' => 'Zümi vagy Susi?', 'emoji' => '🐝', 'skill' => 'Hallod a zs-t és az s-t?', 'color' => '#FFE27A',
            'factory' => Rounds\ZsRounds::class, 'rounds' => 8,
            'intro' => 'Most hangokat figyelünk! A zs úgy zümmög, mint a méhecske. Az s úgy susog, mint amikor csendet kérünk. Figyelj jól! Kezdjük!',
        ],
        'szotag' => [
            'name' => 'Dobolós szavak', 'emoji' => '🥁', 'skill' => 'Szótagolás dobbal', 'color' => '#FFB8A8',
            'factory' => Rounds\SzotagRounds::class, 'rounds' => 8,
            'intro' => 'Dobolni fogunk! Minden szótagra üss egyet a dobra. Például: ci… ca… Ez két dobbanás! Kezdjük!',
        ],
        'kezdo' => [
            'name' => 'Első hang', 'emoji' => '👂', 'skill' => 'Mivel kezdődik a szó?', 'color' => '#BDE7C5',
            'factory' => Rounds\KezdoRounds::class, 'rounds' => 8,
            'intro' => 'Most a szavak elejét figyeljük. Hallgasd jól, melyik szó kezdődik ugyanúgy!',
        ],
        'hol' => [
            'name' => 'Hol van?', 'emoji' => '🧸', 'skill' => 'Alatt, fölött, mögött…', 'color' => '#C9D7FF',
            'factory' => Rounds\HolRounds::class, 'rounds' => 8,
            'intro' => 'Bújócskázunk! Keresd meg, hol bújt el a kis barátunk!',
        ],
        'szamol' => [
            'name' => 'Számolós', 'emoji' => '🍎', 'skill' => 'Több, kevesebb, pont ennyi', 'color' => '#FFD1E8',
            'factory' => Rounds\SzamolRounds::class, 'rounds' => 8,
            'intro' => 'Számoljunk együtt! Egy, kettő, három… Készen állsz?',
        ],
        'okoska' => [
            'name' => 'Okoska', 'emoji' => '💡', 'skill' => 'Mi jön ezután? Mi nem illik?', 'color' => '#D8C8FF',
            'factory' => Rounds\OkoskaRounds::class, 'rounds' => 8,
            'intro' => 'Most okoskodunk! Nézd meg jól a képeket!',
        ],
        'papagaj' => [
            'name' => 'Papagáj', 'emoji' => '🦜', 'skill' => 'Szavak sorban visszamondva', 'color' => '#B8ECE6',
            'factory' => Rounds\PapagajRounds::class, 'rounds' => 6,
            'adaptive' => ['min' => 2, 'max' => 6, 'start' => 3, 'up_after' => 2],
            'intro' => 'Játsszunk papagájosat! Én mondok szavakat, te pedig visszamondod, pont úgy, mint egy papagáj. Utána megmutatod a képeken!',
        ],
        'mondd' => [
            'name' => 'Mondd utánam', 'emoji' => '🗣️', 'skill' => 'Mondatismétlés, szülővel', 'color' => '#FFC9A8',
            'factory' => Rounds\MonddRounds::class, 'rounds' => 6, 'no_idle' => true,
            'adaptive' => ['min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3],
            'intro' => 'Most mondatokat mondok. Figyelj jól, és mondd utánam! Anya vagy apa is segít.',
        ],
        'melyik' => [
            'name' => 'Melyik mondja szépen?', 'emoji' => '🐻', 'skill' => 'Magyaros mondatok', 'color' => '#E3F0A8',
            'factory' => Rounds\MelyikRounds::class, 'rounds' => 8,
            'intro' => 'Brumi és Nyuszi mesél. Az egyikük szépen mondja, a másik kicsit összekeveri. Segíts eldönteni, ki mondta szépen!',
        ],
        'ceruza' => [
            'name' => 'Méhecske útja', 'emoji' => '✏️', 'skill' => 'Vonalvezetés ujjal', 'color' => '#FFE0B8',
            'factory' => Rounds\CeruzaRounds::class, 'rounds' => 4,
            'intro' => 'A méhecske virágot keres. Segíts neki az ujjaddal!',
        ],
        'kirako' => [
            'name' => 'Kirakó', 'emoji' => '🧩', 'skill' => 'Képkirakó, formaérzék', 'color' => '#FFDAC1',
            'factory' => Rounds\KirakoRounds::class, 'rounds' => 3,
            // level = grid: 1 → 2×2, 2 → 3×2, 3 → 3×3 pieces
            'adaptive' => ['min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2],
            'intro' => 'Összekeveredtek a kép darabjai! Koppints két darabra, és helyet cserélnek. Rakd ki a képet!',
        ],
        'parkereso' => [
            'name' => 'Párkereső', 'emoji' => '🃏', 'skill' => 'Emlékezet és szókincs', 'color' => '#C7E9FF',
            'factory' => Rounds\ParkeresoRounds::class, 'rounds' => 2,
            // level = number of pairs
            'adaptive' => ['min' => 3, 'max' => 6, 'start' => 3, 'up_after' => 2],
            'intro' => 'Kártyázzunk! Fordíts fel két kártyát. Ha egyformák, megtaláltad a párt. Jegyezd meg, mi hol van!',
        ],
        'arnyek' => [
            'name' => 'Árnyékkereső', 'emoji' => '👤', 'skill' => 'Alak és forma felismerése', 'color' => '#D6DCE4',
            'factory' => Rounds\ArnyekRounds::class, 'rounds' => 8,
            'intro' => 'Nézd, csak az árnyékuk látszik! Találd ki, kinek az árnyéka!',
        ],
        'rimelo' => [
            'name' => 'Rímelő', 'emoji' => '🎵', 'skill' => 'Rímek, a szavak vége', 'color' => '#F8C8DC',
            'factory' => Rounds\RimeloRounds::class, 'rounds' => 8,
            'intro' => 'Rímeljünk! A ló és a hó rímel, mert ugyanúgy végződik. Figyelj a szavak végére!',
        ],
        'valogato' => [
            'name' => 'Válogató', 'emoji' => '🧺', 'skill' => 'Csoportosítás, fogalmak', 'color' => '#D4F0C0',
            'factory' => Rounds\ValogatoRounds::class, 'rounds' => 3,
            // level = pictures per round: 1 → 4, 2 → 6, 3 → 8
            'adaptive' => ['min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2],
            'intro' => 'Rendet rakunk! Minden kép a saját kosarába kerül. Koppints a jó kosárra!',
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
        | ":goal" in a hint becomes daily_goal.
        */
        'badges' => [
            'first_game' => ['name' => 'Első játék', 'emoji' => '🎈', 'hint' => 'Játssz végig egy játékot!', 'rule' => ['sessions', 1]],
            'daily_goal' => ['name' => 'Napi cél', 'emoji' => '🎯', 'hint' => 'Játssz :goal játékot egy nap alatt!', 'rule' => ['daily_goal']],
            'perfect' => ['name' => 'Hibátlan', 'emoji' => '💎', 'hint' => 'Oldj meg egy egész játékot elsőre!', 'rule' => ['perfect', 1]],
            'streak_3' => ['name' => 'Három nap', 'emoji' => '🔥', 'hint' => 'Játssz három nap egymás után!', 'rule' => ['streak', 3]],
            'streak_7' => ['name' => 'Egész héten', 'emoji' => '🌈', 'hint' => 'Játssz hét nap egymás után!', 'rule' => ['streak', 7]],
            'stars_50' => ['name' => 'Csillaggyűjtő', 'emoji' => '⭐', 'hint' => 'Gyűjts 50 csillagot!', 'rule' => ['stars', 50]],
            'stars_200' => ['name' => 'Csillagszóró', 'emoji' => '🌟', 'hint' => 'Gyűjts 200 csillagot!', 'rule' => ['stars', 200]],
            'sessions_10' => ['name' => 'Kis bajnok', 'emoji' => '🏅', 'hint' => 'Játssz végig 10 játékot!', 'rule' => ['sessions', 10]],
            'sessions_30' => ['name' => 'Nagy bajnok', 'emoji' => '🏆', 'hint' => 'Játssz végig 30 játékot!', 'rule' => ['sessions', 30]],
            'explorer' => ['name' => 'Felfedező', 'emoji' => '🧭', 'hint' => 'Próbáld ki az összes játékot!', 'rule' => ['all_games']],
            'puzzle' => ['name' => 'Kirakóbajnok', 'emoji' => '🖼️', 'hint' => 'Játssz ötször a Kirakóval!', 'rule' => ['game', 'kirako', 5]],
            'memory' => ['name' => 'Jó memória', 'emoji' => '🧠', 'hint' => 'Játssz ötször a Párkeresővel!', 'rule' => ['game', 'parkereso', 5]],
            'rhyme' => ['name' => 'Rímfaragó', 'emoji' => '🎶', 'hint' => 'Játssz ötször a Rímelővel!', 'rule' => ['game', 'rimelo', 5]],
            'speaker' => ['name' => 'Szószóló', 'emoji' => '🎤', 'hint' => 'Játssz ötször a Mondd utánammal!', 'rule' => ['game', 'mondd', 5]],
        ],

        // Csillám's wardrobe, unlocked at these player levels.
        'accessories' => [
            'bow' => ['name' => 'Masni', 'emoji' => '🎀', 'level' => 2],
            'glasses' => ['name' => 'Napszemüveg', 'emoji' => '🕶️', 'level' => 3],
            'flower' => ['name' => 'Virág', 'emoji' => '🌸', 'level' => 4],
            'hat' => ['name' => 'Varázskalap', 'emoji' => '🎩', 'level' => 5],
            'crown' => ['name' => 'Korona', 'emoji' => '👑', 'level' => 7],
        ],
    ],
];
