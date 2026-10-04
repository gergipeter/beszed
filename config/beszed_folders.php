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
            'games' => ['zs', 'kezdo', 'szotag', 'rimelo', 'ikerhangok', 'rimparok', 'hangvonat', 'csigabeszed'],
        ],
        'beszed' => [
            'name' => 'Beszéd és szavak', 'emoji' => '🗣️', 'color' => '#FFD1E0',
            'develops' => 'Szókincs, mondatalkotás, szépen beszélés',
            'games' => ['papagaj', 'mondd', 'melyik', 'hallgasd', 'szajtorna', 'lepegeto', 'hanggyakorlo', 'fujoka', 'hangrepulo', 'talalos', 'hangutanzo', 'mondoka'],
        ],
        'ter' => [
            'name' => 'Nézd és tájékozódj', 'emoji' => '🧭', 'color' => '#CFF0DA',
            'develops' => 'Vizuális észlelés, irányok, térbeli viszonyok',
            'games' => ['hol', 'irany', 'arnyek', 'kirako', 'kulonbseg', 'keresd', 'labirintus', 'robot'],
        ],
        'logika' => [
            'name' => 'Gondolkodj!', 'emoji' => '💡', 'color' => '#E1D4FF',
            'develops' => 'Logika, csoportosítás, sorrend, következtetés',
            'games' => ['okoska', 'valogato', 'tortenet', 'nagysag', 'illik', 'igazvagy'],
        ],
        'figyelem' => [
            'name' => 'Emlékezet és figyelem', 'emoji' => '🧠', 'color' => '#FFF0B3',
            'develops' => 'Munkamemória, figyelem, utasítások követése',
            'games' => ['parkereso', 'mitunt', 'korus', 'utasitas', 'kapdel'],
        ],
        'zene' => [
            'name' => 'Zene és ritmus', 'emoji' => '🎹', 'color' => '#F1D4FF',
            'develops' => 'Zenei hallás, hangmagasság, ritmusérzék',
            'games' => ['zongora', 'ritmus'],
        ],
        'kez' => [
            'name' => 'Kézügyesség', 'emoji' => '✏️', 'color' => '#FFE0C2',
            'develops' => 'Finommotorika, vonalvezetés, írás előkészítése',
            'games' => ['ceruza', 'betuiro', 'pontozo', 'szinezo'],
        ],
        'szivem' => [
            'name' => 'Érzelmek és gondoskodás', 'emoji' => '💛', 'color' => '#FFD6D6',
            'develops' => 'Érzelmek felismerése, együttérzés, felelősség',
            'games' => ['erzelmek', 'tamagotchi'],
        ],
        'nyelv' => [
            'name' => 'Betűk és szókincs', 'emoji' => '🔤', 'color' => '#FFE7A8',
            'develops' => 'Betűismeret, ellentétek, többes szám, foglalkozások, olvasás-előkészítés',
            'games' => ['betuk', 'szokirako', 'olvasd', 'ellentet', 'tobbes', 'foglalkozas', 'mondat', 'szoragaszto', 'igek'],
        ],
        'vilag' => [
            'name' => 'Világunk', 'emoji' => '🌍', 'color' => '#BDEBE3',
            'develops' => 'Környezetismeret: állatok élőhelye, otthon, napirend, időfogalmak, folyamatok',
            'games' => ['mese', 'elohely', 'szobak', 'napirend', 'keszul', 'ido', 'testreszek', 'szelektiv'],
        ],
    ],

    /*
    | The garden's zones: where each game stands on the map. A game not listed lands in the meadow
    | (a simple game) or the forest (an advanced one). The first zone, the meadow, stays open;
    | the others open once half of its games have a flower.
    */
    'zones' => [
        'meadow' => ['szamol', 'szamok', 'beka', 'merleg', 'osztozas', 'ceruza', 'kirako', 'parkereso', 'kulonbseg', 'szinek', 'tamagotchi', 'igek', 'pontozo', 'szinezo'],
        'forest' => ['okoska', 'valogato', 'nagysag', 'arnyek', 'mitunt', 'korus', 'utasitas', 'irany', 'hol', 'talalos', 'kapdel', 'keresd', 'labirintus', 'robot'],
        'sound' => ['zs', 'kezdo', 'szotag', 'rimelo', 'ikerhangok', 'rimparok', 'hallgasd', 'ritmus', 'zongora', 'szajtorna', 'hanggyakorlo', 'fujoka', 'hangrepulo', 'hangvonat'],
        'letters' => ['betuk', 'betuiro', 'szokirako', 'olvasd', 'papagaj', 'mondd', 'mondat', 'melyik', 'lepegeto', 'ellentet', 'tobbes', 'foglalkozas', 'csigabeszed', 'szoragaszto'],
        'world' => ['mese', 'ido', 'elohely', 'szobak', 'napirend', 'keszul', 'tortenet', 'erzelmek', 'igazvagy', 'hangutanzo', 'testreszek', 'illik', 'mondoka', 'szelektiv'],
    ],
];
