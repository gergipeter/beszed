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
            'name' => 'Okoska', 'emoji' => '🧩', 'skill' => 'Mi jön ezután? Mi nem illik?', 'color' => '#D8C8FF',
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
];
