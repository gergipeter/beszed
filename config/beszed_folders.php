<?php

/*
| The hub's folders: the games grouped by what they develop. A game can sit in one
| folder only; any game not listed here lands in a last "Egyéb" folder, so a new
| game shows up even before it is filed. `develops` is the parent-facing line.
*/

return [
    'folders' => [
        'matek' => [
            'name' => 'Matek és számolás', 'emoji' => '🔢', 'color' => '#FFD9A8',
            'develops' => 'Számfogalom, összeadás-kivonás, mennyiségek',
            'games' => ['szamol', 'szamok', 'beka', 'merleg', 'osztozas'],
        ],
        'hangok' => [
            'name' => 'Hangok és hallás', 'emoji' => '👂', 'color' => '#C9E4FF',
            'develops' => 'Beszédhanghallás, szótagolás, rímek, hangok megkülönböztetése',
            'games' => ['zs', 'kezdo', 'szotag', 'rimelo', 'ikerhangok', 'rimparok'],
        ],
        'beszed' => [
            'name' => 'Beszéd és szavak', 'emoji' => '🗣️', 'color' => '#FFD1E0',
            'develops' => 'Szókincs, mondatalkotás, szépen beszélés',
            'games' => ['papagaj', 'mondd', 'melyik', 'hallgasd'],
        ],
        'ter' => [
            'name' => 'Nézd és tájékozódj', 'emoji' => '🧭', 'color' => '#CFF0DA',
            'develops' => 'Vizuális észlelés, irányok, térbeli viszonyok',
            'games' => ['hol', 'irany', 'arnyek', 'kirako', 'kulonbseg'],
        ],
        'logika' => [
            'name' => 'Gondolkodj!', 'emoji' => '💡', 'color' => '#E1D4FF',
            'develops' => 'Logika, csoportosítás, sorrend, következtetés',
            'games' => ['okoska', 'valogato', 'tortenet', 'nagysag'],
        ],
        'figyelem' => [
            'name' => 'Emlékezet és figyelem', 'emoji' => '🧠', 'color' => '#FFF0B3',
            'develops' => 'Munkamemória, figyelem, utasítások követése',
            'games' => ['parkereso', 'mitunt', 'korus', 'utasitas'],
        ],
        'zene' => [
            'name' => 'Zene és ritmus', 'emoji' => '🎹', 'color' => '#F1D4FF',
            'develops' => 'Zenei hallás, hangmagasság, ritmusérzék',
            'games' => ['zongora', 'ritmus'],
        ],
        'kez' => [
            'name' => 'Kézügyesség', 'emoji' => '✏️', 'color' => '#FFE0C2',
            'develops' => 'Finommotorika, vonalvezetés, írás előkészítése',
            'games' => ['ceruza'],
        ],
        'szivem' => [
            'name' => 'Érzelmek és gondoskodás', 'emoji' => '💛', 'color' => '#FFD6D6',
            'develops' => 'Érzelmek felismerése, együttérzés, felelősség',
            'games' => ['erzelmek', 'tamagotchi'],
        ],
    ],
];
