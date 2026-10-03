<?php

use App\Beszed\Rounds;

// Generate 1000 unique collector stickers
if (!function_exists('generateCollectorStickers')) {
function generateCollectorStickers() {
    $emojis = [
        // Animals (100+)
        '🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸', '🐵',
        '🙈', '🙉', '🙊', '🐒', '🐔', '🐧', '🐦', '🐤', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗', '🐴',
        '🦄', '🐝', '🐛', '🦋', '🐌', '🐞', '🐜', '🦟', '🦗', '🕷️', '🦂', '🐢', '🐍', '🐙', '🦑',
        '🦐', '🦞', '🦀', '🐡', '🐠', '🐟', '🐬', '🐳', '🐋', '🦈', '🐊', '🐅', '🐆', '🦓', '🦍',
        '🦧', '🐘', '🦛', '🦏', '🐪', '🐫', '🦒', '🦘', '🐃', '🐂', '🐄', '🐎', '🐖', '🐏', '🐑',
        '🦉', '🦅', '🦆', '🦢', '🦜', '🦗', '🦟', '🦗', '🦗', '🐢', '🐍', '🦎', '🦖', '🦕', '🦏',
        // Food & Drink (80+)
        '🍎', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🫐', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥', '🥑',
        '🍅', '🍆', '🥑', '🥦', '🥬', '🌽', '🌶️', '🥒', '🥐', '🍞', '🥖', '🥨', '🧀', '🥚', '🍳',
        '🧈', '🥞', '🥓', '🥞', '🍤', '🍗', '🍖', '🌭', '🍔', '🍟', '🍕', '🥪', '🥙', '🧆', '🌮',
        '🌯', '🥗', '🥘', '🍝', '🍜', '🍲', '🍛', '🍣', '🍱', '🥟', '🦪', '🍚', '🍙', '🍚', '🍘',
        '🍥', '🥠', '🥮', '🍢', '🍡', '🍧', '🍨', '🍦', '🍰', '🎂', '🧁', '🍮', '🍭', '🍬', '🍫',
        '🍿', '🍩', '🍪', '🌰', '🥜', '🍯', '🥛', '🍶', '🍵', '☕', '🍷', '🍸', '🍹', '🍺', '🍻',
        // Activities & Objects (100+)
        '⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🥏', '🎳', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏',
        '🪃', '🥅', '⛳', '⛸️', '🎣', '🎽', '🎿', '⛷️', '🏂', '🪂', '🛷', '🥌', '🎯', '🪀', '🪁',
        '🎮', '🎰', '🎲', '🧩', '♟️', '🎪', '🎨', '🎬', '🎤', '🎧', '🎼', '🎹', '🥁', '🎷', '🎺',
        '🎸', '🎻', '🎲', '♠️', '♥️', '♣️', '♦️', '🎴', '🎭', '🖼️', '🎞️', '📽️', '🎥', '📷', '📹',
        '🎞️', '📞', '☎️', '📟', '📠', '📺', '📻', '🎙️', '🎚️', '🎛️', '⏱️', '⏲️', '⏰', '🕰️', '⌚',
        // Nature & Space (80+)
        '🌍', '🌎', '🌏', '💫', '⭐', '🌟', '✨', '⚡', '☄️', '💥', '🔥', '🌪️', '🌈', '☀️', '🌤️',
        '⛅', '🌥️', '☁️', '🌦️', '🌧️', '⛈️', '🌩️', '🌨️', '❄️', '☃️', '⛄', '🌬️', '💨', '💧', '💦',
        '☔', '🌊', '🌴', '🌳', '🌲', '🌱', '🌿', '☘️', '🍀', '🎍', '🎎', '🎏', '🌾', '💐', '🌷',
        '🌹', '🥀', '🌺', '🌻', '🌞', '🌝', '🌛', '🌜', '🌚', '🌕', '🌖', '🌗', '🌘', '🌑', '🌒',
        // Symbols & Misc (100+)
        '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '💕', '💞', '💓', '💗', '💖',
        '💘', '💝', '💟', '👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🫰', '🤟',
        '🤘', '🤙', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲', '🤝', '🤜', '🤛',
        // Seasonal & Holiday (40+)
        '🎄', '🎃', '🎆', '🎇', '✨', '🎈', '🎉', '🎊', '🎁', '🎀', '🎗️', '🏆', '🥇', '🥈', '🥉',
        '⚜️', '🔱', '⚡', '☢️', '☣️', '⚠️', '🚀', '🛸', '🛰️', '🚁', '✈️', '🛩️', '🚂', '🚆', '🚇',
    ];

    $hints = [
        'Összegyűjtd őket!' => 1,
        'Ritka matricáim' => 5,
        'Ezért is játszok' => 10,
        'Szupergyűjtemény' => 15,
        'Ez az én kedvenc' => 20,
    ];

    $stickers = [];
    $emojiIdx = 1; // sticker #2 keeps the picture it had when the numbering started at #1

    // From the second game on: the first game already earns the "first_game" sticker.
    for ($i = 2; $i <= 975; $i++) { // 26 existing + 974 new = 1000
        $emoji = $emojis[$emojiIdx % count($emojis)];
        $emojiIdx++;

        $hint = array_rand($hints);
        $stickers["collector_$i"] = [
            'name' => "Gyűjtő sticker #$i",
            'emoji' => $emoji,
            'hint' => $hint,
            'rule' => ['sessions', $i],
        ];
    }

    return $stickers;
}
}

return [

    /*
    | Games of the "Beszéd & DIFER" module.
    | - factory:  builds the rounds of one session from content items
    | - rounds:   rounds per session
    | - adaptive: level range + how many clean wins move up (null = fixed level 1)
    | - no_idle:  Csillám does not jump in with help after 20 s (parent is talking)
    | - tier:     hub group: simple (one tap, little to remember) or advanced
    |             (sounds, rhymes, memory, reasoning: more attention needed)
    | - stage:    the scene it's played in (GameStage.vue): meadow, hive, theatre,
    |             magic, workshop, pond, forest, market, storybook, table
    */
    'games' => [
        'zs' => [
            'name' => 'Zümi vagy Susi?', 'emoji' => '🐝', 'skill' => 'Hallod a zs-t és az s-t?', 'color' => '#FFE27A', 'tier' => 'advanced', 'stage' => 'hive',
            'factory' => Rounds\ZsRounds::class, 'rounds' => 8,
            // level = word difficulty (short/common → long/rare), from the content's own level field
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Most hangokat figyelünk! A zs úgy zümmög, mint a méhecske. Az s úgy susog, mint amikor csendet kérünk. Figyelj jól! Kezdjük!',
        ],
        'szotag' => [
            'name' => 'Dobolós szavak', 'emoji' => '🥁', 'skill' => 'Szótagolás dobbal', 'color' => '#FFB8A8', 'tier' => 'simple', 'stage' => 'theatre',
            'factory' => Rounds\SzotagRounds::class, 'rounds' => 8,
            // level = syllable count, from the content's own level field
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 2],
            ],
            'intro' => 'Dobolni fogunk! Minden szótagra üss egyet a dobra. Például: ci… ca… Ez két dobbanás! Kezdjük!',
        ],
        'kezdo' => [
            'name' => 'Első hang', 'emoji' => '👂', 'skill' => 'Mivel kezdődik a szó?', 'color' => '#BDE7C5', 'tier' => 'advanced', 'stage' => 'meadow',
            'factory' => Rounds\KezdoRounds::class, 'rounds' => 8,
            // level = word difficulty, from the content's own level field
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Most a szavak elejét figyeljük. Hallgasd jól, melyik szó kezdődik ugyanúgy!',
        ],
        'hol' => [
            'name' => 'Hol van?', 'emoji' => '🧸', 'skill' => 'Alatt, fölött, mögött…', 'color' => '#C9D7FF', 'tier' => 'simple', 'stage' => 'forest',
            'factory' => Rounds\HolRounds::class, 'rounds' => 8,
            // level = which relations are asked: 1 → fölött/alatt only (2 options), 2 → + mögött/előtt, 3 → + jobb/bal/között (3 options, HolRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Bújócskázunk! Keresd meg, hol bújt el a kis barátunk!',
        ],
        'szamol' => [
            'name' => 'Számolós', 'emoji' => '🍎', 'skill' => 'Több, kevesebb, pont ennyi', 'color' => '#FFD1E8', 'tier' => 'simple', 'stage' => 'market',
            'factory' => Rounds\SzamolRounds::class, 'rounds' => 8,
            // level = number range: 1 → 1-4, 2 → 2-6, 3 → 3-9 (SzamolRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Számoljunk együtt! Egy, kettő, három… Készen állsz?',
        ],
        'okoska' => [
            'name' => 'Okoska', 'emoji' => '💡', 'skill' => 'Mi jön ezután? Mi nem illik?', 'color' => '#D8C8FF', 'tier' => 'advanced', 'stage' => 'workshop',
            'factory' => Rounds\OkoskaRounds::class, 'rounds' => 8,
            // level = pattern kinds unlocked (AB → +AAB/ABB → +ABC) and odd-one-out category difficulty (OkoskaRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Most okoskodunk! Nézd meg jól a képeket!',
        ],
        'hallgasd' => [
            'name' => 'Hallgasd meg!', 'emoji' => '🎧', 'skill' => 'Szavak jelentése, hallás alapján', 'color' => '#A8E6CF', 'tier' => 'simple', 'stage' => 'meadow',
            'factory' => Rounds\HallgasdRounds::class, 'rounds' => 8,
            // level = word difficulty, from the content's own level field
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Most figyelj jól! Kimondok egy szót, te pedig megkeresed a hozzá illő képet. Kezdjük!',
        ],
        'ikerhangok' => [
            'name' => 'Ikerhangok', 'emoji' => '👯', 'skill' => 'Hasonló szavak megkülönböztetése', 'color' => '#FFB8D9', 'tier' => 'advanced', 'stage' => 'pond',
            'factory' => Rounds\IkerhangokRounds::class, 'rounds' => 8,
            // level = options shown: 1-2 → the pair only, 3 → +1 distractor word from another pair, and the grade of the
            // pairs given (content level 1 clear sounds … 3 the fine Hungarian contrasts: s/sz/zs, c/cs, long/short vowels) (IkerhangokRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Most nagyon hasonló szavakat hallasz! Figyelj jól, melyiket mondtam, és koppints a jó képre!',
        ],
        'papagaj' => [
            'name' => 'Papagáj', 'emoji' => '🦜', 'skill' => 'Szavak sorban visszamondva', 'color' => '#B8ECE6', 'tier' => 'advanced', 'stage' => 'forest',
            'factory' => Rounds\PapagajRounds::class, 'rounds' => 6,
            'adaptive' => [
                'min' => 2, 'max' => 6, 'start' => 3, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 2, '5-6' => 3, '7+' => 4],
            ],
            'intro' => 'Játsszunk papagájosat! Én mondok szavakat, te pedig visszamondod, pont úgy, mint egy papagáj. Utána megmutatod a képeken!',
        ],
        'mondd' => [
            'name' => 'Mondd utánam', 'emoji' => '🗣️', 'skill' => 'Mondatismétlés, szülővel', 'color' => '#FFC9A8', 'tier' => 'simple', 'stage' => 'storybook',
            'factory' => Rounds\MonddRounds::class, 'rounds' => 6, 'no_idle' => true,
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Most mondatokat mondok. Figyelj jól, és mondd utánam! Anya vagy apa is segít.',
        ],
        'szajtorna' => [
            'name' => 'Szájtorna', 'emoji' => '😗', 'skill' => 'Szájizmok erősítése, tükör előtt', 'color' => '#FFC2D4', 'tier' => 'simple', 'stage' => 'storybook',
            'factory' => Rounds\SzajtornaRounds::class, 'rounds' => 4, 'no_idle' => true, 'guess' => false,
            // level = repetitions per exercise: 1 → 3, 2 → 5, 3 → 10 (SzajtornaRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Most a szádat edzzük! Nézz tükörbe, és csináld, amit a kép mutat. Anya vagy apa is csinálja veled!',
        ],
        'lepegeto' => [
            'name' => 'Lépegető', 'emoji' => '🎲', 'skill' => 'Társasjáték beszédfeladatokkal', 'color' => '#FFD9A0', 'tier' => 'simple', 'stage' => 'forest',
            'factory' => Rounds\LepegetoRounds::class, 'rounds' => 1, 'no_idle' => true, 'guess' => false,
            // level = fields on the path: 1 → 8, 2 → 12, 3 → 16 (LepegetoRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            // the season of the board; without a pick it is the season it is now
            'pick_prompt' => 'Melyik évszakban lépegessünk ma? Válassz!',
            'categories' => [
                'osz' => ['name' => 'Ősz', 'emoji' => '🍂'],
                'tel' => ['name' => 'Tél', 'emoji' => '⛄'],
                'tavasz' => ['name' => 'Tavasz', 'emoji' => '🌷'],
                'nyar' => ['name' => 'Nyár', 'emoji' => '🏖️'],
            ],
            'intro' => 'Társasjátékozunk! Dobj a kockával, lépj előre, és csináld meg, amit a mező kér. Anya vagy apa is segít!',
        ],
        'betuk' => [
            'name' => 'Betűvadász', 'emoji' => '🔤', 'skill' => 'Betűk ismerete, első betű', 'color' => '#FFE08A', 'tier' => 'simple', 'stage' => 'storybook',
            'factory' => Rounds\BetukRounds::class, 'rounds' => 8,
            // level = the letters in play: 1 plain ones, 2 + long vowels (and picture-from-letter rounds), 3 + cs, sz, gy… (BetukRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Betűket keresünk! Megmutatok egy képet, te pedig megkeresed, melyik betűvel kezdődik a neve.',
        ],
        'szinek' => [
            'name' => 'Színek és formák', 'emoji' => '🎨', 'skill' => 'Színek és alakzatok nevei', 'color' => '#FFC2E0', 'tier' => 'simple', 'stage' => 'meadow',
            'factory' => Rounds\SzinekRounds::class, 'rounds' => 8,
            // level: 1 colours, 2 shapes, 3 both (SzinekRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Színeket és formákat keresünk! Hallgasd meg, melyiket kérem, és koppints rá!',
        ],
        'ellentet' => [
            'name' => 'Ellentétek', 'emoji' => '↔️', 'skill' => 'Szókincs: ellentétes szavak', 'color' => '#C5D8FF', 'tier' => 'simple', 'stage' => 'theatre',
            'factory' => Rounds\EllentetRounds::class, 'rounds' => 8,
            // level = what to pick from (2 → 3 options) and the pairs' own level (EllentetRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Az ellentétek párban járnak: nagy és kicsi, meleg és hideg. Mondok egyet, te megkeresed a párját!',
        ],
        'tobbes' => [
            'name' => 'Egy vagy sok?', 'emoji' => '🐶', 'skill' => 'Egyes és többes szám', 'color' => '#D5C8FF', 'tier' => 'advanced', 'stage' => 'storybook',
            'factory' => Rounds\TobbesRounds::class, 'rounds' => 8,
            // level = the plural: 1 plain -k (kutya → kutyák), 2 -ak/-ek/-ok/-ök, 3 changing stems (ló → lovak) (content level)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Egy kutya, sok kutya! Hallgasd meg, melyiket kérem, és koppints a jó képre!',
        ],
        'foglalkozas' => [
            'name' => 'Ki mit csinál?', 'emoji' => '🧑‍🚒', 'skill' => 'Foglalkozások, szókincs', 'color' => '#FFD0B0', 'tier' => 'simple', 'stage' => 'market',
            'factory' => Rounds\FoglalkozasRounds::class, 'rounds' => 8,
            // level = what to pick from: 1 → two, 2 → three, 3 → four (FoglalkozasRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Ki mit csinál? Mondok egy munkát, te pedig megkeresed, ki végzi!',
        ],
        'napirend' => [
            'name' => 'Napirend és idő', 'emoji' => '🌅', 'skill' => 'Napszakok, évszakok, napi teendők sorrendje', 'color' => '#FFE3A3', 'tier' => 'advanced', 'stage' => 'storybook',
            'factory' => Rounds\TortenetRounds::class, 'rounds' => 5,
            'adaptive' => [
                'min' => 1, 'max' => 2, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Mikor mi jön? Nézd meg a képeket, és rakd őket a helyes sorrendbe!',
        ],
        'keszul' => [
            'name' => 'Hogyan készül?', 'emoji' => '🥞', 'skill' => 'Folyamatok: lépések sorrendje', 'color' => '#FFD6B8', 'tier' => 'advanced', 'stage' => 'workshop',
            'factory' => Rounds\TortenetRounds::class, 'rounds' => 5,
            'adaptive' => [
                'min' => 1, 'max' => 2, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Hogyan készül a palacsinta? És a torta? Rakd sorba a lépéseket!',
        ],
        'elohely' => [
            'name' => 'Ki hol él?', 'emoji' => '🦊', 'skill' => 'Állatok élőhelye', 'color' => '#C6EBC9', 'tier' => 'simple', 'stage' => 'forest',
            'factory' => Rounds\ValogatoRounds::class, 'rounds' => 3,
            // level = pictures per round: 1 → 4, 2 → 6, 3 → 8
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Ki hol lakik? A vízben, az erdőben, a tanyán vagy az égen? Koppints a jó helyre!',
        ],
        'szobak' => [
            'name' => 'Melyik szobába való?', 'emoji' => '🏠', 'skill' => 'Otthon: tárgyak és szobák', 'color' => '#F7D9C4', 'tier' => 'simple', 'stage' => 'table',
            'factory' => Rounds\ValogatoRounds::class, 'rounds' => 3,
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Rendet rakunk a lakásban! Melyik tárgy melyik szobába való? Koppints a jó helyre!',
        ],
        'melyik' => [
            'name' => 'Melyik mondja szépen?', 'emoji' => '🐻', 'skill' => 'Magyaros mondatok', 'color' => '#E3F0A8', 'tier' => 'advanced', 'stage' => 'theatre',
            'factory' => Rounds\MelyikRounds::class, 'rounds' => 8,
            // level = sentence complexity, from the content's own level field
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Brumi és Nyuszi mesél. Az egyikük szépen mondja, a másik kicsit összekeveri. Segíts eldönteni, ki mondta szépen!',
        ],
        'ceruza' => [
            'name' => 'Méhecske útja', 'emoji' => '✏️', 'skill' => 'Vonalvezetés ujjal', 'color' => '#FFE0B8', 'tier' => 'simple', 'stage' => 'meadow',
            'factory' => Rounds\CeruzaRounds::class, 'rounds' => 4,
            // level = path difficulty (wave/arches/hills → zigzag/steps → loops), from the content's own level field
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'A méhecske virágot keres. Segíts neki az ujjaddal!',
        ],
        'kirako' => [
            'name' => 'Kirakó', 'emoji' => '🧩', 'skill' => 'Képkirakó, formaérzék', 'color' => '#FFDAC1', 'tier' => 'simple', 'stage' => 'workshop',
            'factory' => Rounds\KirakoRounds::class, 'rounds' => 3,
            // level = "pálya" 1–200: the grid grows 2×2 → 8×7, scenes and princesses come, the example fades (KirakoRounds)
            'adaptive' => [
                // clean_tries 2: a puzzle graded 1 or 2 (few wasted swaps) moves on to the next pálya; only a messy one (3) steps back
                'min' => 1, 'max' => 200, 'start' => 1, 'up_after' => 1, 'clean_tries' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 8, '7+' => 16],
            ],
            // A puzzle's grade is not a wrong answer: no "practise what was missed" repeats, and a picture is not shown
            // again until the last 150 pictures have come by (SessionBuilder).
            'review' => false, 'fresh' => 150,
            /*
            | Picture themes the child picks before playing. `lexicon`: word-bank categories
            | (database/lexicon/hu.json → c); `emojis`: pictures that belong too; `tales`:
            | the fairy-tale rows (kind: tale). "mix" is everything.
            */
            'categories' => [
                'mix' => ['name' => 'Mindenféle', 'emoji' => '🎲'],
                'tales' => ['name' => 'Hercegnők és mesék', 'emoji' => '👸', 'tales' => true, 'emojis' => ['🧚', '🧜', '🧙', '🧝', '🦄', '🐉', '👼', '🧞']],
                'animals' => ['name' => 'Állatok', 'emoji' => '🦁', 'lexicon' => ['animal']],
                'vehicles' => ['name' => 'Járművek', 'emoji' => '🚒', 'lexicon' => ['vehicle']],
                'food' => ['name' => 'Finomságok', 'emoji' => '🍓', 'lexicon' => ['fruit', 'vegetable', 'food']],
                'toys' => ['name' => 'Játékok, hangszerek', 'emoji' => '🧸', 'lexicon' => ['toy', 'instrument']],
                'nature' => ['name' => 'Természet', 'emoji' => '🌻', 'lexicon' => ['nature', 'weather', 'flower']],
                'home' => ['name' => 'Otthon', 'emoji' => '🏠', 'lexicon' => ['house', 'kitchen', 'thing', 'tool', 'clothing', 'school', 'building']],
            ],
            'intro' => 'Összekeveredtek a kép darabjai! Koppints két darabra, vagy húzd az egyiket a másikra, és helyet cserélnek. Rakd ki a képet! Minden kép után jön a következő pálya.',
        ],
        'parkereso' => [
            'name' => 'Párkereső', 'emoji' => '🃏', 'skill' => 'Emlékezet és szókincs', 'color' => '#C7E9FF', 'tier' => 'simple', 'stage' => 'magic',
            'factory' => Rounds\ParkeresoRounds::class, 'rounds' => 2,
            // level = number of pairs; can also include difficulty: easy, medium, hard
            'adaptive' => [
                'min' => 3, 'max' => 6, 'start' => 3, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 3, '5-6' => 4, '7+' => 5],
            ],
            'intro' => 'Kártyázzunk! Fordíts fel két kártyát. Ha egyformák, megtaláltad a párt. Jegyezd meg, mi hol van!',
        ],
        'rimparok' => [
            'name' => 'Rímpárok', 'emoji' => '🎶', 'skill' => 'Rímelő szavak megjegyzése', 'color' => '#F0D9FF', 'tier' => 'advanced', 'stage' => 'pond',
            'factory' => Rounds\RimparokRounds::class, 'rounds' => 2,
            // level = number of rhyme pairs
            'adaptive' => [
                'min' => 2, 'max' => 4, 'start' => 2, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 2, '5-6' => 3, '7+' => 4],
            ],
            'intro' => 'Kártyázzunk! De most nem ugyanaz a párja egy kártyának, hanem az, amelyik rímel rá. Fordíts fel kettőt, és figyelj a hangjukra!',
        ],
        'arnyek' => [
            'name' => 'Árnyékkereső', 'emoji' => '👤', 'skill' => 'Alak és forma felismerése', 'color' => '#D6DCE4', 'tier' => 'simple', 'stage' => 'forest',
            'factory' => Rounds\ArnyekRounds::class, 'rounds' => 8,
            // level = shape difficulty (distinct silhouettes → look-alikes), from the content's own level field
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 2],
            ],
            'intro' => 'Nézd, csak az árnyékuk látszik! Találd ki, kinek az árnyéka!',
        ],
        'rimelo' => [
            'name' => 'Rímelő', 'emoji' => '🎵', 'skill' => 'Rímek, a szavak vége', 'color' => '#F8C8DC', 'tier' => 'advanced', 'stage' => 'pond',
            'factory' => Rounds\RimeloRounds::class, 'rounds' => 8,
            // level = rhyme difficulty, from the content's own level field
            'adaptive' => [
                'min' => 1, 'max' => 2, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Rímeljünk! A ló és a hó rímel, mert ugyanúgy végződik. Figyelj a szavak végére!',
        ],
        'valogato' => [
            'name' => 'Válogató', 'emoji' => '🧺', 'skill' => 'Csoportosítás, fogalmak', 'color' => '#D4F0C0', 'tier' => 'simple', 'stage' => 'market',
            'factory' => Rounds\ValogatoRounds::class, 'rounds' => 3,
            // level = pictures per round: 1 → 4, 2 → 6, 3 → 8
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Rendet rakunk! Minden kép a saját kosarába kerül. Koppints a jó kosárra!',
        ],
        'kulonbseg' => [
            'name' => 'Mi a különbség?', 'emoji' => '🔍', 'skill' => 'Két kép, egy különbség', 'color' => '#CDE7FF', 'tier' => 'simple', 'stage' => 'meadow',
            'factory' => Rounds\KulonbsegRounds::class, 'rounds' => 6,
            // level = grid on each panel: 1 → 2×2, 2 → 3×2, 3 → 3×3 (and a look-alike swapped in)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Nézd, két kép! Majdnem egyformák, de egy helyen más van rajtuk. Keresd meg, és koppints rá!',
        ],
        'nagysag' => [
            'name' => 'Kicsitől a nagyig', 'emoji' => '🪆', 'skill' => 'Sorba rendezés nagyság szerint', 'color' => '#FFE9A8', 'tier' => 'simple', 'stage' => 'workshop',
            'factory' => Rounds\NagysagRounds::class, 'rounds' => 6,
            // level = sizes to order: 1 → 3, 2 → 4, 3 → 5 (and every other round biggest first)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Rakjunk rendet! Koppints a képekre sorban: először a legkisebbre, a végén a legnagyobbra!',
        ],
        'erzelmek' => [
            'name' => 'Hogy érzi magát?', 'emoji' => '😊', 'skill' => 'Érzelmek felismerése', 'color' => '#FFD6C9', 'tier' => 'simple', 'stage' => 'storybook',
            'factory' => Rounds\ErzelmekRounds::class, 'rounds' => 8,
            // level = feeling difficulty (basic → more nuanced), from the content's own level field
            'adaptive' => [
                'min' => 1, 'max' => 2, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Az arcunk megmutatja, hogy érezzük magunkat: vidámak vagyunk, szomorúak, vagy éppen mérgesek. Segíts kitalálni!',
        ],
        'mitunt' => [
            'name' => 'Mi tűnt el?', 'emoji' => '🎩', 'skill' => 'Képek megjegyzése', 'color' => '#E6D9FF', 'tier' => 'advanced', 'stage' => 'magic',
            'factory' => Rounds\MituntRounds::class, 'rounds' => 6,
            // level = pictures to remember (3–6)
            'adaptive' => [
                'min' => 3, 'max' => 6, 'start' => 3, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 3, '5-6' => 4, '7+' => 5],
            ],
            'intro' => 'Varázsoljunk! Jegyezd meg jól a képeket. Utána az egyik eltűnik, te pedig kitalálod, melyik volt az!',
        ],
        'tortenet' => [
            'name' => 'Mi történt előbb?', 'emoji' => '🐣', 'skill' => 'Történetek sorrendje', 'color' => '#D2F2DF', 'tier' => 'advanced', 'stage' => 'storybook',
            'factory' => Rounds\TortenetRounds::class, 'rounds' => 5,
            // level = steps of a story: 1 → 3, 2 → 4
            'adaptive' => [
                'min' => 1, 'max' => 2, 'start' => 1, 'up_after' => 3,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Mesélek neked! Nézd meg a képeket, és rakd őket sorba: mi történt először, és mi a végén?',
        ],
        'korus' => [
            'name' => 'Állatkórus', 'emoji' => '🎼', 'skill' => 'Nézd, hallgasd, ismételd!', 'color' => '#C4EEF7', 'tier' => 'advanced', 'stage' => 'theatre',
            'factory' => Rounds\KorusRounds::class, 'rounds' => 5,
            // level = notes the choir sings (2–7)
            'adaptive' => [
                'min' => 2, 'max' => 7, 'start' => 2, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 2, '5-6' => 3, '7+' => 3],
            ],
            'intro' => 'Az állatok egymás után énekelnek. Figyeld, ki énekel, aztán koppints rájuk ugyanabban a sorrendben!',
        ],
        'utasitas' => [
            'name' => 'Csináld, amit mondok!', 'emoji' => '👆', 'skill' => 'Utasítások követése', 'color' => '#FFDDEE', 'tier' => 'advanced', 'stage' => 'meadow',
            'factory' => Rounds\UtasitasRounds::class, 'rounds' => 6,
            // level = kind of direction: 1 → one step, 2 → two steps / "all", 3 → three steps, "before", "not"
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Most jól figyelj! Mondok valamit, te pedig pontosan azt csinálod. Várd meg, amíg végigmondom, és csak utána koppints!',
        ],
        'beka' => [
            'name' => 'Ugráló béka', 'emoji' => '🐸', 'skill' => 'Számegyenes, hozzáadás és elvétel', 'color' => '#C8F0D2', 'tier' => 'simple', 'stage' => 'pond',
            'factory' => Rounds\BekaRounds::class, 'rounds' => 6, 'no_idle' => true,
            // level 1: pads 0–5, forward · 2: pads 0–10, forward and back · 3: also two-part hops (BekaRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Ez a békató! A béka számozott levelekről levelekre ugrál. Ugrasd te, vagy figyeld meg, hova érkezik!',
        ],
        'merleg' => [
            'name' => 'Mérleg', 'emoji' => '⚖️', 'skill' => 'Egyenlőség, mennyiségek összehasonlítása', 'color' => '#FFE3A3', 'tier' => 'advanced', 'stage' => 'workshop',
            'factory' => Rounds\MerlegRounds::class, 'rounds' => 5, 'no_idle' => true, 'guess' => false,
            // level = how many items: 1 → up to 4 and an empty pan, 2 → up to 7, 3 → up to 10 (MerlegRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Ez egy mérleg! Tegyél a jobb oldalra annyit, amennyi a bal oldalon van, hogy vízszintbe álljon!',
        ],
        'osztozas' => [
            'name' => 'Osztozkodás', 'emoji' => '🍰', 'skill' => 'Egyenlő részekre osztás', 'color' => '#FFD3E4', 'tier' => 'simple', 'stage' => 'table',
            'factory' => Rounds\OsztozasRounds::class, 'rounds' => 5, 'no_idle' => true, 'guess' => false,
            // level 1: 2 plates · 2: 2–3 plates · 3: one is left over (OsztozasRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Osszuk szét igazságosan! Koppints egy tányérra, és az asztalról odaugrik egy darab. Mindegyik tányérra ugyanannyi kerüljön!',
        ],
        'szamok' => [
            'name' => 'Számok tízig', 'emoji' => '🔢', 'skill' => 'Számfogalom, sorozatok', 'color' => '#FFE8B8', 'tier' => 'simple', 'stage' => 'market',
            'factory' => Rounds\SzamokRounds::class, 'rounds' => 8,
            // level = range: 1 → 1–5, 2 → 1–8, 3 → 1–10 and counting backwards (SzamokRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 2, '7+' => 3],
            ],
            'intro' => 'Számok tízig! Számold meg a képeket, és találd ki, melyik szám jön ezután!',
        ],
        'irany' => [
            'name' => 'Jobb és bal', 'emoji' => '↔️', 'skill' => 'Irányok, tájékozódás', 'color' => '#CFF0DA', 'tier' => 'simple', 'stage' => 'meadow',
            'factory' => Rounds\IranyRounds::class, 'rounds' => 6,
            // level 1: arrows · 2: leftmost / rightmost / middle of three · 3: the neighbour on the left or right of one of four (IranyRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Merre van jobbra, merre balra? Nézd meg jól a képeket, és koppints a jó válaszra!',
        ],
        'ritmus' => [
            'name' => 'Ritmus', 'emoji' => '🥁', 'skill' => 'Ritmusérzék, hallási figyelem', 'color' => '#FFD6CC', 'tier' => 'simple', 'stage' => 'theatre',
            'factory' => Rounds\RitmusRounds::class, 'rounds' => 5, 'no_idle' => true,
            // level = length of the rhythm (3 → 5 beats) and its speed (RitmusRounds)
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Figyelj a dobra! Ritmust játszom, te pedig ugyanúgy megtapsolod: koppints a dobra, ugyanolyan gyorsan!',
        ],
        'zongora' => [
            'name' => 'Zongora', 'emoji' => '🎹', 'skill' => 'Zenei hallás, hangmagasság', 'color' => '#E3D4FF', 'tier' => 'simple', 'stage' => 'theatre',
            'factory' => Rounds\ZongoraRounds::class, 'rounds' => 5,
            // level 1: coloured keys with their names · 2: names, and the note is played · 3: by ear only, smaller steps, longer tunes
            'adaptive' => [
                'min' => 1, 'max' => 3, 'start' => 1, 'up_after' => 2,
                'starts_by_age' => ['3-4' => 1, '5-6' => 1, '7+' => 2],
            ],
            'intro' => 'Ez egy zongora! Minden billentyű más hangot szólaltat meg: a bal oldalon vannak a mélyek, a jobb oldalon a magasak. Próbáld ki, aztán keresd meg a hangokat!',
        ],
        'tamagotchi' => [
            'name' => 'Kis kedvenc', 'emoji' => '🐾', 'skill' => 'Gondoskodás, felelősség', 'color' => '#FFE0C2', 'tier' => 'simple', 'stage' => 'hive',
            'factory' => Rounds\TamagotchiRounds::class, 'rounds' => 1, 'no_idle' => true, 'guess' => false,
            'intro' => 'Nézd, egy kis állatkád! Etesd, játssz vele, és vigyázz rá, hogy mindig boldog legyen!',
        ],
    ],

    /*
    | Óvodai jelek: the picture signs Hungarian kindergartens give each child (on
    | their towel, cup and cubby). A child picks theirs and finds themselves by it
    | on the "Ki játszik ma?" screen, before they can read their name.
    */
    'signs' => [
        'alma' => ['name' => 'Alma', 'emoji' => '🍎'], 'korte' => ['name' => 'Körte', 'emoji' => '🍐'],
        'cseresznye' => ['name' => 'Cseresznye', 'emoji' => '🍒'], 'eper' => ['name' => 'Eper', 'emoji' => '🍓'],
        'szolo' => ['name' => 'Szőlő', 'emoji' => '🍇'], 'banan' => ['name' => 'Banán', 'emoji' => '🍌'],
        'repa' => ['name' => 'Répa', 'emoji' => '🥕'], 'gomba' => ['name' => 'Gomba', 'emoji' => '🍄'],
        'napraforgo' => ['name' => 'Napraforgó', 'emoji' => '🌻'], 'tulipan' => ['name' => 'Tulipán', 'emoji' => '🌷'],
        'rozsa' => ['name' => 'Rózsa', 'emoji' => '🌹'], 'fenyo' => ['name' => 'Fenyőfa', 'emoji' => '🌲'],
        'level' => ['name' => 'Falevél', 'emoji' => '🍁'], 'makk' => ['name' => 'Makk', 'emoji' => '🌰'],
        'katica' => ['name' => 'Katica', 'emoji' => '🐞'], 'pillango' => ['name' => 'Pillangó', 'emoji' => '🦋'],
        'csiga' => ['name' => 'Csiga', 'emoji' => '🐌'], 'mehecske' => ['name' => 'Méhecske', 'emoji' => '🐝'],
        'hal' => ['name' => 'Halacska', 'emoji' => '🐟'], 'beka' => ['name' => 'Béka', 'emoji' => '🐸'],
        'cica' => ['name' => 'Cica', 'emoji' => '🐱'], 'kutya' => ['name' => 'Kutya', 'emoji' => '🐶'],
        'nyuszi' => ['name' => 'Nyuszi', 'emoji' => '🐰'], 'sun' => ['name' => 'Süni', 'emoji' => '🦔'],
        'mokus' => ['name' => 'Mókus', 'emoji' => '🐿️'], 'bagoly' => ['name' => 'Bagoly', 'emoji' => '🦉'],
        'maci' => ['name' => 'Maci', 'emoji' => '🧸'], 'labda' => ['name' => 'Labda', 'emoji' => '⚽'],
        'lufi' => ['name' => 'Léggömb', 'emoji' => '🎈'], 'dob' => ['name' => 'Dob', 'emoji' => '🥁'],
        'auto' => ['name' => 'Autó', 'emoji' => '🚗'], 'vonat' => ['name' => 'Vonat', 'emoji' => '🚂'],
        'hajo' => ['name' => 'Hajó', 'emoji' => '⛵'], 'csillag' => ['name' => 'Csillag', 'emoji' => '⭐'],
        'hold' => ['name' => 'Hold', 'emoji' => '🌙'], 'esernyo' => ['name' => 'Esernyő', 'emoji' => '☂️'],
    ],

    /*
    | "Csillám makes mistakes" (App\Beszed\CsillamGuess): in picture-choice rounds
    | she sometimes has a go first and asks the child if she's right; half the time
    | she's wrong on purpose. `chance` per round, at most `max` per session. A game
    | can opt out with 'guess' => false.
    */
    'guesses' => ['chance' => 0.25, 'max' => 2],

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
        // Original achievement stickers (26) + 974 collector stickers = 1000 total
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
            'memory_hard' => ['name' => 'Memória mester', 'emoji' => '🎓', 'hint' => 'Játssz a Párkereső nehéz szintjén és nyerj!', 'rule' => ['game', 'parkereso', 3]],
            'spotter' => ['name' => 'Sasszem', 'emoji' => '🦅', 'hint' => 'Játssz ötször a „Mi a különbség?” játékkal!', 'rule' => ['game', 'kulonbseg', 5]],
            'sizes' => ['name' => 'Rendrakó', 'emoji' => '📏', 'hint' => 'Játssz ötször a „Kicsitől a nagyig” játékkal!', 'rule' => ['game', 'nagysag', 5]],
            'feelings' => ['name' => 'Jó barát', 'emoji' => '🤗', 'hint' => 'Játssz ötször a „Hogy érzi magát?” játékkal!', 'rule' => ['game', 'erzelmek', 5]],
            'magician' => ['name' => 'Bűvész', 'emoji' => '🪄', 'hint' => 'Játssz ötször a „Mi tűnt el?” játékkal!', 'rule' => ['game', 'mitunt', 5]],
            'stories' => ['name' => 'Mesemondó', 'emoji' => '📖', 'hint' => 'Játssz ötször a „Mi történt előbb?” játékkal!', 'rule' => ['game', 'tortenet', 5]],
            'choir' => ['name' => 'Kórusmester', 'emoji' => '🎼', 'hint' => 'Játssz ötször az Állatkórussal!', 'rule' => ['game', 'korus', 5]],
            'hopper' => ['name' => 'Ugróbajnok', 'emoji' => '🐸', 'hint' => 'Játssz ötször az Ugráló békával!', 'rule' => ['game', 'beka', 5]],
            'balancer' => ['name' => 'Egyensúlyművész', 'emoji' => '⚖️', 'hint' => 'Játssz ötször a Mérleggel!', 'rule' => ['game', 'merleg', 5]],
            'sharer' => ['name' => 'Igazságos osztó', 'emoji' => '🍰', 'hint' => 'Játssz ötször az Osztozkodással!', 'rule' => ['game', 'osztozas', 5]],
            'counter' => ['name' => 'Számolóművész', 'emoji' => '🔢', 'hint' => 'Játssz ötször a Számok tízig játékkal!', 'rule' => ['game', 'szamok', 5]],
            'compass' => ['name' => 'Tájékozódó', 'emoji' => '🧭', 'hint' => 'Játssz ötször a Jobb és bal játékkal!', 'rule' => ['game', 'irany', 5]],
            'drummer' => ['name' => 'Dobos', 'emoji' => '🥁', 'hint' => 'Játssz ötször a Ritmus játékkal!', 'rule' => ['game', 'ritmus', 5]],
            'pianist' => ['name' => 'Zongorista', 'emoji' => '🎹', 'hint' => 'Játssz ötször a Zongorával!', 'rule' => ['game', 'zongora', 5]],
            'listener' => ['name' => 'Figyelmes', 'emoji' => '🦉', 'hint' => 'Játssz ötször a „Csináld, amit mondok!” játékkal!', 'rule' => ['game', 'utasitas', 5]],
            ...generateCollectorStickers(),
        ],


        /*
        | Csillám's wardrobe, unlocked at these player levels (about two a level,
        | so every level-up brings something). Grouped into slots so the child can
        | wear one item per slot at once: a hat AND glasses AND a scarf AND a
        | balloon, and her mane in a new colour, but not a hat and a crown together.
        | Where each sits on her: components/guide/accessories.js.
        */
        'accessories' => [
            // on her head
            'cap' => ['name' => 'Baseballsapka', 'emoji' => '🧢', 'level' => 2, 'slot' => 'head'],
            'hat' => ['name' => 'Varázskalap', 'emoji' => '🎩', 'level' => 5, 'slot' => 'head'],
            'crown' => ['name' => 'Korona', 'emoji' => '👑', 'level' => 7, 'slot' => 'head'],
            'sunhat' => ['name' => 'Virágos kalap', 'emoji' => '👒', 'level' => 8, 'slot' => 'head'],
            'gradcap' => ['name' => 'Okoskalap', 'emoji' => '🎓', 'level' => 10, 'slot' => 'head'],
            // on her face
            'glasses' => ['name' => 'Napszemüveg', 'emoji' => '🕶️', 'level' => 3, 'slot' => 'face'],
            'goggles' => ['name' => 'Búvárszemüveg', 'emoji' => '🥽', 'level' => 6, 'slot' => 'face'],
            'specs' => ['name' => 'Okosszemüveg', 'emoji' => '👓', 'level' => 12, 'slot' => 'face'],
            // round her neck
            'scarf' => ['name' => 'Sál', 'emoji' => '🧣', 'level' => 4, 'slot' => 'neck'],
            'medal' => ['name' => 'Aranyérem', 'emoji' => '🏅', 'level' => 8, 'slot' => 'neck'],
            'ribbon' => ['name' => 'Díszszalag', 'emoji' => '🎗️', 'level' => 11, 'slot' => 'neck'],
            // in her hoof or at her side
            'bow' => ['name' => 'Masni', 'emoji' => '🎀', 'level' => 2, 'slot' => 'extra'],
            'flower' => ['name' => 'Virág', 'emoji' => '🌸', 'level' => 4, 'slot' => 'extra'],
            'balloon' => ['name' => 'Lufi', 'emoji' => '🎈', 'level' => 6, 'slot' => 'extra'],
            'wand' => ['name' => 'Varázspálca', 'emoji' => '🪄', 'level' => 9, 'slot' => 'extra'],
            'butterfly' => ['name' => 'Pillangó', 'emoji' => '🦋', 'level' => 10, 'slot' => 'extra'],
            // her mane in a new colour (the palettes are in accessories.js)
            'mane_candy' => ['name' => 'Rózsaszín sörény', 'emoji' => '🩷', 'level' => 3, 'slot' => 'mane'],
            'mane_ocean' => ['name' => 'Tengerkék sörény', 'emoji' => '💙', 'level' => 5, 'slot' => 'mane'],
            'mane_sunset' => ['name' => 'Naplemente sörény', 'emoji' => '🧡', 'level' => 7, 'slot' => 'mane'],
            'mane_mint' => ['name' => 'Mentazöld sörény', 'emoji' => '💚', 'level' => 9, 'slot' => 'mane'],
            'mane_galaxy' => ['name' => 'Csillagos sörény', 'emoji' => '💜', 'level' => 11, 'slot' => 'mane'],
            'mane_gold' => ['name' => 'Arany sörény', 'emoji' => '💛', 'level' => 12, 'slot' => 'mane'],
        ],

        // Scene backgrounds for the sticker picture, drawn by SceneBackdrop.vue; `colors` is the plain fallback.
        'backgrounds' => [
            'meadow' => ['name' => 'Rét', 'emoji' => '🌼', 'colors' => ['#CDEFAE', '#EAF7C9']],
            'sky' => ['name' => 'Éjszakai ég', 'emoji' => '🌙', 'colors' => ['#2B2A5C', '#4B3F80']],
            'castle' => ['name' => 'Kastély', 'emoji' => '🏰', 'colors' => ['#FFD9EA', '#D8C8FF']],
            'beach' => ['name' => 'Tengerpart', 'emoji' => '🏖️', 'colors' => ['#9ED8FF', '#F6DFA6']],
            'underwater' => ['name' => 'Víz alatt', 'emoji' => '🐠', 'colors' => ['#5FC3E8', '#1F6FA8']],
            'snow' => ['name' => 'Havas táj', 'emoji' => '⛄', 'colors' => ['#DCEFFF', '#FFFFFF']],
            'forest' => ['name' => 'Varázserdő', 'emoji' => '🌲', 'colors' => ['#BFE6B0', '#4F9E6A']],
        ],
        // Stickers placed on the scene at once.
        'scene_max_stickers' => 24,
    ],
];
