<?php

/*
| Skill areas for the parent report, the skill map and the therapist share link.
| The first five follow the DIFER areas (Nagy József et al.); the games only
| practise something close to each area, so the mapping is approximate and the
| report is not a DIFER assessment. `difer: false` areas are extra groupings.
*/

return [
    'areas' => [
        'irasmozgas' => [
            'label' => 'Írásmozgás-koordináció', 'emoji' => '✏️', 'difer' => true,
            'games' => ['ceruza', 'betuiro'],
        ],
        'beszedhanghallas' => [
            'label' => 'Beszédhanghallás', 'emoji' => '👂', 'difer' => true,
            'games' => ['zs', 'kezdo', 'szotag', 'rimelo', 'ikerhangok'],
        ],
        'relacioszokincs' => [
            'label' => 'Relációszókincs', 'emoji' => '📦', 'difer' => true,
            'games' => ['hol', 'nagysag', 'irany'],
        ],
        'szamolas' => [
            'label' => 'Elemi számolás', 'emoji' => '🔢', 'difer' => true,
            'games' => ['szamol', 'szamok', 'beka', 'merleg', 'osztozas'],
        ],
        'kovetkeztetes' => [
            'label' => 'Tapasztalati következtetés', 'emoji' => '🧠', 'difer' => true,
            'games' => ['okoska', 'valogato', 'tortenet', 'napirend', 'keszul', 'elohely', 'szobak', 'ido'],
        ],
        'nyelv_emlekezet' => [
            'label' => 'Mondatok és emlékezet', 'emoji' => '🗣️', 'difer' => false,
            'games' => ['papagaj', 'mondd', 'melyik', 'parkereso', 'hallgasd', 'rimparok', 'mitunt', 'korus', 'utasitas', 'szajtorna', 'lepegeto', 'betuk', 'ellentet', 'tobbes', 'foglalkozas', 'mondat'],
        ],
        'vizualis' => [
            'label' => 'Vizuális észlelés', 'emoji' => '👀', 'difer' => false,
            'games' => ['arnyek', 'kirako', 'kulonbseg', 'szinek'],
        ],
        'zene' => [
            'label' => 'Zenei hallás', 'emoji' => '🎹', 'difer' => false,
            'games' => ['zongora', 'ritmus'],
        ],
        'erzelmek' => [
            'label' => 'Érzelmek felismerése', 'emoji' => '😊', 'difer' => false,
            'games' => ['erzelmek'],
        ],
    ],

    // A game's first answers find the child's level quickly: while it has at most this many answers,
    // every clean first-try win moves up a level (instead of `up_after` in a row). 0 = off.
    'placement_answers' => 8,

    // Fewer answers than this in the period: "not enough data" instead of a band.
    'min_answers' => 5,

    // Share of first-try answers for each band (checked from the top).
    'bands' => ['strong' => 0.75, 'growing' => 0.5, 'practice' => 0.0],

    // Change in first-try share between this period and the one before to call it a trend.
    'trend_delta' => 0.05,
];
