<?php

/*
| The content editor: who may use it, and what each game's content item looks like.
| Field types: text · emoji · select (options) · list (separator for typing it) ·
| emoji_list · pairs ([emoji, name]). `when` = [field, value] shows a field only
| for one variant. Items also have a level (1 easy … 3 hard): games with an
| adaptive level use it for difficulty, the others for the child's age.
*/

$text = fn (string $label, array $extra = []) => ['type' => 'text', 'label' => $label] + $extra;
$emoji = fn (string $label = 'Kép (emoji)') => ['type' => 'emoji', 'label' => $label];

return [
    // Parents (by e-mail) who may edit content, comma-separated.
    'admins' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

    // ARASAAC pictograms instead of emojis wherever the word bank has one
    // (Sergio Palao, ARASAAC, Government of Aragón, CC BY-NC-SA 4.0: non-commercial use).
    // Set BESZED_PICTOGRAMS=false before charging for the app, unless ARASAAC has agreed to commercial use:
    // it then plays only content that works with emojis, serves no pictogram and accepts no new one.
    // See docs/pictogram-licensing.md.
    'pictograms' => (bool) env('BESZED_PICTOGRAMS', true),

    // Games without an adaptive level: which item level suits each age band
    // (AgeBands). Items above it come up rarely, items below it a bit less often.
    'age_levels' => ['3-4' => 1, '5-6' => 2, '7+' => 3],

    'schemas' => [
        'zs' => ['title' => 'word', 'fields' => [
            'word' => $text('Szó'),
            'emoji' => $emoji(),
            'sound' => ['type' => 'select', 'label' => 'Hang', 'options' => ['zs' => 'zs – zümmögő', 's' => 's – susogó']],
        ]],
        'szotag' => ['title' => 'word', 'fields' => [
            'word' => $text('Szó'),
            'emoji' => $emoji(),
            'syllables' => ['type' => 'list', 'label' => 'Szótagok', 'separator' => '-', 'hint' => 'kötőjellel: ci-ca'],
        ]],
        'kezdo' => ['title' => 'word', 'fields' => [
            'word' => $text('Szó'),
            'emoji' => $emoji(),
            'sound' => $text('Kezdőhang', ['max' => 3, 'hint' => 'pl. m, sz, gy']),
        ]],
        'hol' => ['title' => 'name', 'fields' => [
            'name' => $text('Tárgy / állat neve'),
            'emoji' => $emoji(),
        ]],
        'szamol' => ['title' => 'name', 'fields' => [
            'name' => $text('Név (több …)', ['hint' => 'alma']),
            'accusative' => $text('Tárgyeset (tegyél … a kosárba)', ['hint' => 'almát']),
            'emoji' => $emoji(),
        ]],
        // Counting games share Számolós's shape: a noun and its -t form.
        'merleg' => ['title' => 'name', 'fields' => [
            'name' => $text('Név', ['hint' => 'alma']),
            'accusative' => $text('Tárgyeset', ['hint' => 'almát']),
            'emoji' => $emoji(),
        ]],
        'osztozas' => ['title' => 'name', 'fields' => [
            'name' => $text('Név', ['hint' => 'alma']),
            'accusative' => $text('Tárgyeset', ['hint' => 'almát']),
            'emoji' => $emoji(),
        ]],
        'szamok' => ['title' => 'name', 'fields' => [
            'name' => $text('Név', ['hint' => 'alma']),
            'accusative' => $text('Tárgyeset', ['hint' => 'almát']),
            'emoji' => $emoji(),
        ]],
        'beka' => ['title' => 'name', 'fields' => ['name' => $text('Mi van a képen?'), 'emoji' => $emoji()]],
        'irany' => ['title' => 'name', 'fields' => ['name' => $text('Mi van a képen?'), 'emoji' => $emoji()]],
        // Rhythm: 1 = a short beat (ta), 2 = a long one (táá).
        'ritmus' => ['title' => 'name', 'fields' => [
            'name' => $text('Ritmus (ta/táá)', ['hint' => 'tatatáá']),
            'pattern' => ['type' => 'int_list', 'label' => 'Ütemek (1 = ta, 2 = táá)', 'min' => 2, 'range' => [1, 2], 'hint' => 'szóközzel: 1 1 2'],
        ]],
        // Piano: the note's place on the scale, 0 = do … 7 = a higher do.
        'zongora' => ['title' => 'name', 'fields' => [
            'name' => $text('Hang neve', ['hint' => 'do']),
            'note' => ['type' => 'int', 'label' => 'Hang helye (0 = do … 7 = magas do)', 'range' => [0, 12]],
        ]],
        'tamagotchi' => ['title' => 'petName', 'fields' => ['petName' => $text('A kedvenc neve', ['max' => 30])]],
        'okoska' => ['title' => 'category', 'fields' => [
            'kind' => ['type' => 'select', 'label' => 'Fajta', 'options' => ['category' => 'Csoport (mi nem illik?)', 'symbols' => 'Jelek a mintázatokhoz']],
            'category' => $text('Csoport neve', ['when' => ['kind', 'category'], 'hint' => 'állat']),
            'emojis' => ['type' => 'emoji_list', 'label' => 'Képek', 'min' => 4],
        ]],
        'papagaj' => ['title' => 'word', 'fields' => ['word' => $text('Szó'), 'emoji' => $emoji()]],
        'hallgasd' => ['title' => 'word', 'fields' => ['word' => $text('Szó'), 'emoji' => $emoji()]],
        'ikerhangok' => ['title' => 'wordA', 'fields' => [
            'wordA' => $text('Szó A'),
            'emojiA' => $emoji('Kép A'),
            'wordB' => $text('Szó B'),
            'emojiB' => $emoji('Kép B'),
            // the sounds that tell the two words apart; the parents' report groups answers by it
            'contrast' => $text('Hangpár', ['required' => false, 'max' => 12, 'hint' => 's – sz']),
        ]],
        'mondd' => ['title' => 'text', 'fields' => [
            'text' => $text('Mondat'),
            'chunks' => ['type' => 'list', 'label' => 'Darabok (lassú kimondáshoz)', 'separator' => '|', 'hint' => 'A cica | alszik.'],
            'emoji' => $emoji('Kép (1–3 emoji)'),
        ]],
        'szajtorna' => ['title' => 'name', 'fields' => [
            'name' => $text('Gyakorlat neve'),
            'moves' => ['type' => 'pairs', 'label' => 'Mozdulatok egymás után', 'min' => 2, 'hint' => 'soronként: emoji mit csinálj'],
        ]],
        'lepegeto' => ['title' => 'text', 'fields' => [
            'theme' => ['type' => 'select', 'label' => 'Évszak', 'options' => ['osz' => 'Ősz', 'tel' => 'Tél', 'tavasz' => 'Tavasz', 'nyar' => 'Nyár']],
            'kind' => ['type' => 'select', 'label' => 'Fajta', 'options' => [
                'ask' => 'Kérdés (válaszolj)', 'echo' => 'Utánozd (hang)', 'move' => 'Mozogj', 'clap' => 'Tapsold el', 'mouth' => 'Szájtorna',
            ]],
            'emoji' => $emoji(),
            'text' => $text('Feladat'),
        ]],
        'betuk' => ['title' => 'word', 'fields' => [
            'letter' => $text('Első betű (vagy betűpár)', ['max' => 3, 'hint' => 'a, cs, sz']),
            'word' => $text('Szó'),
            'emoji' => $emoji(),
        ]],
        'szinek' => ['title' => 'name', 'fields' => [
            'name' => $text('Neve'),
            'kind' => ['type' => 'select', 'label' => 'Fajta', 'options' => ['color' => 'Szín', 'shape' => 'Alakzat']],
            'emoji' => $emoji(),
        ]],
        'ellentet' => ['title' => 'a', 'fields' => [
            'a' => $text('Szó'),
            'emojiA' => $emoji('Kép'),
            'b' => $text('Az ellentéte'),
            'emojiB' => $emoji('Kép'),
        ]],
        'tobbes' => ['title' => 'sg', 'fields' => [
            'sg' => $text('Egyes szám', ['hint' => 'kutya']),
            'pl' => $text('Többes szám', ['hint' => 'kutyák']),
            'emoji' => $emoji(),
        ]],
        'foglalkozas' => ['title' => 'job', 'fields' => [
            'job' => $text('Foglalkozás', ['hint' => 'tűzoltó']),
            'emoji' => $emoji(),
            'does' => $text('Mit csinál? (a „Ki …?” kérdés után)', ['hint' => 'oltja a tüzet']),
        ]],
        'napirend' => ['title' => 'title', 'fields' => [
            'title' => $text('Cím', ['speak' => false]),
            'steps' => ['type' => 'pairs', 'label' => 'Képek sorrendben', 'min' => 3, 'hint' => 'soronként: emoji mi történik'],
            'story' => $text('Elmesélve (a végén hangzik el)', ['max' => 300]),
        ]],
        'keszul' => ['title' => 'title', 'fields' => [
            'title' => $text('Cím', ['speak' => false]),
            'steps' => ['type' => 'pairs', 'label' => 'Képek sorrendben', 'min' => 3, 'hint' => 'soronként: emoji mi történik'],
            'story' => $text('Elmesélve (a végén hangzik el)', ['max' => 300]),
        ]],
        'elohely' => ['title' => 'label', 'fields' => [
            'key' => $text('Azonosító', ['max' => 20, 'hint' => 'viz', 'speak' => false]),
            'label' => $text('Hely neve', ['hint' => 'Vízben élők']),
            'singular' => $text('„Ez nem …” alakja', ['hint' => 'vízi állat']),
            'icon' => $emoji('Hely képe'),
            'items' => ['type' => 'pairs', 'label' => 'Képek', 'min' => 4, 'hint' => 'soronként: emoji név'],
        ]],
        'szobak' => ['title' => 'label', 'fields' => [
            'key' => $text('Azonosító', ['max' => 20, 'hint' => 'konyha', 'speak' => false]),
            'label' => $text('Szoba neve', ['hint' => 'Konyha']),
            'singular' => $text('„Ez nem …” alakja', ['hint' => 'a konyhába való']),
            'icon' => $emoji('Szoba képe'),
            'items' => ['type' => 'pairs', 'label' => 'Képek', 'min' => 4, 'hint' => 'soronként: emoji név'],
        ]],
        'mondat' => ['title' => 'text', 'fields' => [
            'text' => $text('Mondat (3–5 szó, egy szó csak egyszer)', ['hint' => 'A cica alszik.']),
        ]],
        'betuiro' => ['title' => 'glyph', 'fields' => [
            'glyph' => $text('Betű', ['max' => 1, 'hint' => 'A', 'speak' => false]),
            'name' => $text('Neve', ['hint' => 'nagy A']),
        ]],
        'ido' => ['title' => 'time', 'fields' => [
            'time' => $text('Idő (ó:pp, negyedórás pontossággal)', ['max' => 5, 'hint' => '3:30', 'speak' => false]),
        ]],
        'szokirako' => ['title' => 'word', 'fields' => [
            'word' => $text('Szó (kisbetűvel)', ['max' => 12]),
            'emoji' => $emoji(),
        ]],
        'olvasd' => ['title' => 'word', 'fields' => [
            'word' => $text('Szó', ['speak' => false]),
            'syllables' => ['type' => 'list', 'label' => 'Szótagok', 'separator' => '|', 'hint' => 'ku | tya'],
            'emoji' => $emoji(),
        ]],
        'mese' => ['title' => 'title', 'fields' => [
            'title' => $text('Mese címe'),
            'text' => $text('A mese (4–7 rövid mondat)', ['max' => 600]),
            'emoji' => $emoji('Kép (1–3 emoji)'),
            'q1' => $text('1. kérdés', ['max' => 160]),
            'o1' => ['type' => 'pairs', 'label' => '1. válaszok: az ELSŐ a helyes', 'min' => 3, 'hint' => 'soronként: emoji válasz'],
            'q2' => $text('2. kérdés', ['max' => 160]),
            'o2' => ['type' => 'pairs', 'label' => '2. válaszok: az ELSŐ a helyes', 'min' => 3, 'hint' => 'soronként: emoji válasz'],
            'q3' => $text('3. kérdés', ['max' => 160]),
            'o3' => ['type' => 'pairs', 'label' => '3. válaszok: az ELSŐ a helyes', 'min' => 3, 'hint' => 'soronként: emoji válasz'],
        ]],
        'melyik' => ['title' => 'good', 'fields' => [
            'good' => $text('Helyes mondat'),
            'bad' => $text('Hibás mondat'),
            'emoji' => $emoji('Kép (1–3 emoji)'),
        ]],
        'ceruza' => ['title' => 'path', 'fields' => [
            'path' => ['type' => 'select', 'label' => 'Útvonal', 'options' => [
                'wave' => 'Hullám', 'loops' => 'Hurkok', 'zigzag' => 'Cikcakk', 'arches' => 'Ívek',
                'steps' => 'Lépcső', 'hills' => 'Dombok',
            ]],
        ]],
        'kirako' => ['title' => 'name', 'fields' => [
            'name' => $text('Mi van a képen?'),
            'emoji' => $emoji(),
            // a fairy-tale figure: "Kész a kép: Hófehérke!", with a picture beside it and its own scene
            'kind' => ['type' => 'select', 'label' => 'Fajta', 'required' => false, 'options' => ['thing' => 'Tárgy, állat (Ez egy …)', 'tale' => 'Mesealak (Kész a kép: …)']],
            'prop' => ['type' => 'emoji', 'label' => 'Kép mellette (pl. alma)', 'required' => false],
            'scene' => ['type' => 'select', 'label' => 'Háttér', 'required' => false, 'options' => [
                'meadow' => 'Rét', 'beach' => 'Tengerpart', 'castle' => 'Kastély', 'underwater' => 'Víz alatt',
                'snow' => 'Havas táj', 'forest' => 'Varázserdő', 'sky' => 'Éjszakai ég',
            ]],
        ]],
        'parkereso' => ['title' => 'word', 'fields' => ['word' => $text('Szó'), 'emoji' => $emoji()]],
        'rimparok' => ['title' => 'word', 'fields' => [
            'word' => $text('Szó'),
            'emoji' => $emoji(),
            'rhyme' => $text('Rím (a szó vége)', ['max' => 6, 'hint' => 'ó, éz, ál']),
        ]],
        'arnyek' => ['title' => 'name', 'fields' => ['name' => $text('Név'), 'emoji' => $emoji()]],
        'rimelo' => ['title' => 'word', 'fields' => [
            'word' => $text('Szó'),
            'emoji' => $emoji(),
            'rhyme' => $text('Rím (a szó vége)', ['max' => 6, 'hint' => 'ó, éz, ál']),
        ]],
        'valogato' => ['title' => 'label', 'fields' => [
            'key' => $text('Azonosító', ['max' => 20, 'hint' => 'allat', 'speak' => false]),
            'label' => $text('Kosár neve (többes szám)', ['hint' => 'Állatok']),
            'singular' => $text('Egyes szám', ['hint' => 'állat']),
            'icon' => $emoji('Kosár képe'),
            'items' => ['type' => 'pairs', 'label' => 'Képek a kosárba', 'min' => 4, 'hint' => 'soronként: emoji név'],
        ]],
        'kulonbseg' => ['title' => 'name', 'fields' => [
            'name' => $text('Mi van a képen?'),
            'emoji' => $emoji(),
            // the top level swaps in a look-alike from the same group
            'group' => ['type' => 'select', 'label' => 'Csoport', 'options' => [
                'allat' => 'Állat', 'gyumolcs' => 'Gyümölcs', 'zoldseg' => 'Zöldség', 'etel' => 'Étel', 'jarmu' => 'Jármű',
                'ruha' => 'Ruha', 'jatek' => 'Játék', 'hangszer' => 'Hangszer', 'virag' => 'Virág', 'egyeb' => 'Egyéb',
            ]],
        ]],
        'nagysag' => ['title' => 'name', 'fields' => ['name' => $text('Mi van a képen?'), 'emoji' => $emoji()]],
        'erzelmek' => ['title' => 'name', 'fields' => [
            'name' => $text('Érzés (Melyik arc …?)', ['hint' => 'szomorú']),
            'emoji' => $emoji('Arc (emoji)'),
            'faces' => ['type' => 'emoji_list', 'label' => 'További arcok ugyanerre', 'required' => false],
            'situations' => ['type' => 'pairs', 'label' => 'Helyzetek (Hogy érzi most magát?)', 'required' => false, 'hint' => 'soronként: kép mondat'],
            'close' => ['type' => 'list', 'label' => 'Hasonló érzések (helyzetnél nem kínáljuk fel)', 'separator' => ',', 'required' => false, 'hint' => 'meglepett, vidám'],
        ]],
        'mitunt' => ['title' => 'name', 'fields' => ['name' => $text('Mi van a képen?'), 'emoji' => $emoji()]],
        'tortenet' => ['title' => 'title', 'fields' => [
            'title' => $text('Történet címe', ['speak' => false]),
            'steps' => ['type' => 'pairs', 'label' => 'Képek sorrendben', 'min' => 3, 'hint' => 'soronként: emoji mi történik'],
            'story' => $text('Elmesélve (a végén hangzik el)', ['max' => 300]),
        ]],
        'korus' => ['title' => 'name', 'fields' => ['name' => $text('Állat neve'), 'emoji' => $emoji()]],
        'utasitas' => ['title' => 'name', 'fields' => [
            'name' => $text('Mi van a képen?'),
            'emoji' => $emoji(),
            // "Koppints az összes állatra!" / "…mindenre, ami nem állat"
            'group' => ['type' => 'select', 'label' => 'Csoport', 'options' => [
                'allat' => 'Állat', 'gyumolcs' => 'Gyümölcs', 'zoldseg' => 'Zöldség', 'etel' => 'Étel', 'jarmu' => 'Jármű',
                'ruha' => 'Ruha', 'jatek' => 'Játék', 'hangszer' => 'Hangszer', 'virag' => 'Virág',
            ]],
            'onto' => $text('Koppints a …', ['max' => 40, 'hint' => 'kutyára, kenyérre']),
        ]],
    ],
];
