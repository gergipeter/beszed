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
            'games' => ['ceruza'],
        ],
        'beszedhanghallas' => [
            'label' => 'Beszédhanghallás', 'emoji' => '👂', 'difer' => true,
            'games' => ['zs', 'kezdo', 'szotag', 'rimelo', 'ikerhangok'],
        ],
        'relacioszokincs' => [
            'label' => 'Relációszókincs', 'emoji' => '📦', 'difer' => true,
            'games' => ['hol'],
        ],
        'szamolas' => [
            'label' => 'Elemi számolás', 'emoji' => '🔢', 'difer' => true,
            'games' => ['szamol'],
        ],
        'kovetkeztetes' => [
            'label' => 'Tapasztalati következtetés', 'emoji' => '🧠', 'difer' => true,
            'games' => ['okoska', 'valogato'],
        ],
        'nyelv_emlekezet' => [
            'label' => 'Mondatok és emlékezet', 'emoji' => '🗣️', 'difer' => false,
            'games' => ['papagaj', 'mondd', 'melyik', 'parkereso', 'hallgasd', 'rimparok'],
        ],
        'vizualis' => [
            'label' => 'Vizuális észlelés', 'emoji' => '👀', 'difer' => false,
            'games' => ['arnyek', 'kirako'],
        ],
    ],

    // Fewer answers than this in the period: "not enough data" instead of a band.
    'min_answers' => 5,

    // Share of first-try answers for each band (checked from the top).
    'bands' => ['strong' => 0.75, 'growing' => 0.5, 'practice' => 0.0],

    // Change in first-try share between this period and the one before to call it a trend.
    'trend_delta' => 0.05,
];
