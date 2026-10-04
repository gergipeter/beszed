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

        // The twenty games from the competitor research (docs/competitor-games.md).
        'hanggyakorlo' => ['title' => 'word', 'fields' => [
            'word' => $text('Szó (vagy rövid mondat)'),
            'emoji' => $emoji('Kép (1–3 emoji)'),
            'sound' => ['type' => 'select', 'label' => 'Hang', 'options' => [
                'r' => 'r', 'l' => 'l', 's' => 's', 'sz' => 'sz', 'z' => 'z', 'zs' => 'zs',
                'c' => 'c', 'cs' => 'cs', 'k' => 'k', 'g' => 'g', 'gy' => 'gy', 'ty' => 'ty',
            ]],
            'pos' => ['type' => 'select', 'label' => 'Hol van a hang?', 'options' => [
                'start' => 'A szó elején', 'middle' => 'A szó közepén', 'end' => 'A szó végén', 'phrase' => 'Mondatban, többször',
            ]],
            // the 3rd level asks "Mi ez?" without saying the word first: only for pictures every child names with this word
            'naming' => ['type' => 'select', 'label' => 'Megnevezheti a képről?', 'required' => false, 'options' => [
                'yes' => 'Igen, a képről magától kimondja', 'no' => 'Nem, csak utánmondásra (más szót is mondhatna rá)',
            ]],
        ]],
        // Fújóka: the scene shows the blow; the level decides how long (FujokaRounds). A scene suits only some kinds
        // of blowing (FujokaRounds::SCENES: candles go out puff by puff, a feather floats on a gentle blow).
        'fujoka' => ['title' => 'name', 'fields' => [
            'name' => $text('Neve', ['max' => 40, 'speak' => false, 'hint' => 'Maci szülinapja']),
            'story' => $text('Csillám így kezdi', ['max' => 140, 'hint' => 'Maci szülinapja van! Három gyertya ég a tortáján.']),
            'scene' => ['type' => 'select', 'label' => 'Kép', 'options' => [
                'candles' => 'Gyertyák a tortán', 'dandelion' => 'Pitypang', 'boat' => 'Vitorlás', 'pinwheel' => 'Szélforgó',
                'bubbles' => 'Buborékok', 'feather' => 'Toll',
            ]],
            'kind' => ['type' => 'select', 'label' => 'Fújás', 'options' => [
                'puffs' => 'Rövid fújások (1. szint)', 'long' => 'Egy hosszú fújás (2. szint)',
                'gentle' => 'Gyenge, egyenletes fújás (3. szint)', 'alternate' => 'Hol gyengén, hol erősen (3. szint)',
            ]],
            'count' => ['type' => 'int', 'label' => 'Hány rövid fújás', 'range' => [2, 5], 'when' => ['kind', 'puffs']],
            'steps' => ['type' => 'int', 'label' => 'Hány váltás (gyengén, erősen…)', 'range' => [2, 6], 'when' => ['kind', 'alternate']],
            'friend' => ['type' => 'emoji', 'label' => 'Ki van ott? (emoji, nem kötelező)', 'required' => false],
            'cheer' => $text('A végén még ezt mondja (nem kötelező)', ['max' => 80, 'required' => false, 'hint' => 'Boldog szülinapot, Maci!']),
        ]],
        // Hangrepülő: the child holds `model` (written as it sounds, so the voice can say it); pitch rows need a voiced sound.
        'hangrepulo' => ['title' => 'helper', 'fields' => [
            'sound' => ['type' => 'select', 'label' => 'Hang', 'options' => [
                'a' => 'a', 'á' => 'á', 'é' => 'é', 'í' => 'í', 'ó' => 'ó', 'ö' => 'ö', 'ú' => 'ú',
                'm' => 'm', 'z' => 'z', 'r' => 'r', 'sz' => 'sz', 's' => 's', 'f' => 'f',
            ]],
            'shown' => $text('Így látszik a képernyőn', ['max' => 8, 'speak' => false, 'hint' => 'ááá, sssz, mmm']),
            // the voice spells out anything without a vowel ("sss" → "es es es"): a consonant is asked for with a verb
            'model' => $text('Csillám így kéri (csak magánhangzós szavak)', ['max' => 60, 'hint' => 'Mondd hosszan: ááá  vagy  Sziszegj hosszan']),
            'helper' => $text('Mihez hasonlít?', ['max' => 90, 'hint' => 'mint amikor a doktor néni megnézi a torkodat']),
            'emoji' => $emoji(),
            'kind' => ['type' => 'select', 'label' => 'Fajta', 'options' => ['sustain' => 'Kitartott hang (1–2. szint)', 'pitch' => 'Magas és mély hang (3. szint)']],
            'flyer' => ['type' => 'select', 'label' => 'Mi repül?', 'options' => ['rocket' => 'Rakéta', 'bee' => 'Méhecske', 'balloon' => 'Léggömb']],
        ]],
        'hangvonat' => ['title' => 'word', 'fields' => [
            'word' => $text('Szó'),
            'emoji' => $emoji(),
            // only sounds that can be held long (HangvonatRounds::SOUNDS)
            'sound' => ['type' => 'select', 'label' => 'Hang', 'options' => [
                's' => 's', 'sz' => 'sz', 'z' => 'z', 'zs' => 'zs', 'm' => 'm', 'n' => 'n', 'l' => 'l', 'r' => 'r', 'f' => 'f', 'v' => 'v', 'j' => 'j',
            ]],
            'pos' => ['type' => 'select', 'label' => 'Hol van?', 'options' => ['start' => 'Elején', 'middle' => 'Közepén', 'end' => 'Végén']],
        ]],
        'csigabeszed' => ['title' => 'word', 'fields' => [
            'word' => $text('Szó'),
            'emoji' => $emoji(),
            // the word's syllables: each is said on its own (a lone sound like "sss" would be spelled out by the voice)
            'pieces' => ['type' => 'list', 'label' => 'Szótagok', 'separator' => '|', 'min' => 2, 'hint' => 'ci | ca'],
        ]],
        'szoragaszto' => ['title' => 'word', 'fields' => [
            'word' => $text('Összetett szó', ['hint' => 'hóember']),
            'emoji' => $emoji(),
            'a' => $text('Első tag', ['hint' => 'hó']),
            'emojiA' => $emoji('Első tag képe'),
            'b' => $text('Második tag', ['hint' => 'ember']),
            'emojiB' => $emoji('Második tag képe'),
            // "Mi marad a hóemberből, ha elvesszük a havat?": the forms can't be made by a rule (hó → havat)
            'from' => $text('-ból/-ből alak', ['hint' => 'hóemberből']),
            'accA' => $text('Első tag -t alakja', ['hint' => 'havat']),
            'accB' => $text('Második tag -t alakja', ['hint' => 'embert']),
        ]],
        'talalos' => ['title' => 'answer', 'fields' => [
            'answer' => $text('Megfejtés'),
            'emoji' => $emoji(),
            'clues' => ['type' => 'list', 'label' => 'Nyomok', 'separator' => '|', 'min' => 2, 'hint' => 'Hosszú a füle. | Répát eszik.'],
            // the wrong pictures come from the same group, so the clues (not the group) give the answer away
            'group' => ['type' => 'select', 'label' => 'Csoport', 'options' => [
                'allat' => 'Állat', 'gyumolcs' => 'Gyümölcs', 'zoldseg' => 'Zöldség', 'etel' => 'Étel', 'jarmu' => 'Jármű',
                'ruha' => 'Ruha', 'jatek' => 'Játék', 'hangszer' => 'Hangszer', 'termeszet' => 'Természet', 'targy' => 'Tárgy',
            ]],
            // answers of the same group that fit some clues too (busz – villamos): never offered beside it
            'close' => ['type' => 'list', 'label' => 'Hasonló megfejtések (ezeket nem kínáljuk mellé)', 'separator' => ',', 'required' => false, 'hint' => 'villamos, vonat'],
        ]],
        'igek' => ['title' => 'verb', 'fields' => [
            'verb' => $text('Ige (ő …)', ['hint' => 'fut']),
            'emoji' => $emoji(),
            // pictures of one group are shown together; animal voices (ugat, nyávog) only with each other
            'group' => ['type' => 'select', 'label' => 'Csoport', 'options' => ['mozgas' => 'Mozgás, sport', 'arc' => 'Arc, érzés', 'kez' => 'Kézzel, munka', 'allat' => 'Állathang']],
            'question' => $text('Kérdés', ['hint' => 'Ki fut?']),
            'who' => $text('Ki van a képen? (névelővel, ha nem „ő”)', ['required' => false, 'max' => 40, 'hint' => 'a kutya']),
            // level 3 "Én is …": only verbs whose forms were checked; ikes verbs: eszem, eszel, eszünk, esznek
            'forms' => ['type' => 'list', 'label' => 'Én, te, mi, ők alak (3. szinthez)', 'separator' => ',', 'required' => false, 'hint' => 'futok, futsz, futunk, futnak'],
            'close' => ['type' => 'list', 'label' => 'Ezekkel az igékkel ne kerüljön egy körbe (hasonló kép)', 'separator' => ',', 'required' => false, 'hint' => 'fürdik'],
        ]],
        'igazvagy' => ['title' => 'text', 'fields' => [
            'text' => $text('Mondat'),
            'emoji' => ['type' => 'emoji', 'label' => 'Kép (ha van)', 'required' => false],
            'truth' => ['type' => 'select', 'label' => 'Igaz?', 'options' => ['igaz' => 'Igaz', 'butasag' => 'Butaság']],
            // said after a silly sentence: "Így van, butaság! A hal a vízben úszik."
            'fix' => $text('Helyesen (butaságnál)', ['required' => false, 'hint' => 'A hal a vízben úszik.']),
        ]],
        'hangutanzo' => ['title' => 'name', 'fields' => [
            'name' => $text('Ki? Mi?', ['hint' => 'béka']),
            'emoji' => $emoji(),
            'sound' => $text('Mit mond?', ['max' => 30, 'hint' => 'brekeke']),
            // person: "aki tüsszent" (no article) or a name like "Mikulás"; thing: "Mi szól így?"
            'kind' => ['type' => 'select', 'label' => 'Fajta', 'options' => ['animal' => 'Állat', 'person' => 'Ember (aki …)', 'thing' => 'Tárgy, jármű']],
            'close' => ['type' => 'list', 'label' => 'Ezekkel ne kerüljön egy körbe (hasonló hang vagy kép)', 'separator' => ',', 'required' => false, 'hint' => 'szamár'],
        ]],
        'testreszek' => ['title' => 'question', 'fields' => [
            'kind' => ['type' => 'select', 'label' => 'Fajta', 'options' => ['name' => 'Melyik a …?', 'function' => 'Mivel …?', 'care' => 'Mi kell hozzá?']],
            'question' => $text('Kérdés', ['hint' => 'Mivel hallunk?']),
            // the right answer: a body part, or for care rows the thing (zokni) or the body part (Hova húzzuk a zoknit?)
            'name' => $text('A jó válasz neve', ['hint' => 'fül']),
            'emoji' => $emoji('A jó válasz képe'),
            'part' => ['type' => 'emoji', 'label' => 'Kép a kérdés fölé (ha kell)', 'required' => false],
            'say' => $text('Csillám mondja a jó válasz után', ['hint' => 'Igen, a fülünkkel hallunk! Fogd meg a füledet!']),
            'close' => ['type' => 'emoji_list', 'label' => 'Képek, amelyek szintén jók lennének (ezeket nem kínáljuk)', 'required' => false],
        ]],
        'illik' => ['title' => 'question', 'fields' => [
            'kind' => ['type' => 'select', 'label' => 'Fajta', 'options' => ['pair' => 'Mi illik hozzá?', 'function' => 'Mivel …?', 'category' => 'Hogy hívjuk őket együtt?']],
            'question' => $text('Kérdés', ['hint' => 'Mi illik a zoknihoz?  ·  Alma, banán, körte. Hogy hívjuk őket együtt?']),
            'emoji' => ['type' => 'emoji', 'label' => 'Kép a kérdéshez (csoportnál 3 emoji)', 'required' => false],
            'answer' => $text('Jó válasz', ['hint' => 'cipő  ·  gyümölcsök']),
            'answerEmoji' => ['type' => 'emoji', 'label' => 'Jó válasz képe (párnál, eszköznél)', 'required' => false],
            // pair / function: the wrong pictures to choose from — none of them may also fit
            'wrong' => ['type' => 'emoji_list', 'label' => 'Rossz válaszok képei (párnál, eszköznél, legalább 3)', 'required' => false],
            'say' => $text('Csillám mondja a jó válasz után', ['required' => false, 'hint' => 'Igen! A zoknira cipőt húzunk.']),
            // category: other group names that would also be right (never offered)
            'close' => ['type' => 'list', 'label' => 'Csoportnevek, amelyek szintén jók lennének', 'separator' => ',', 'required' => false, 'hint' => 'játékok'],
        ]],
        'mondoka' => ['title' => 'title', 'fields' => [
            'title' => $text('Címe (első sora)', ['speak' => false]),
            'lines' => ['type' => 'list', 'label' => 'Sorok (a hagyományos szöveg betűhíven)', 'separator' => '|', 'min' => 2],
            'gap' => $text('A hiányzó szó (ahogy a szövegben áll)', ['max' => 40, 'hint' => 'vajat']),
            'name' => $text('A kép neve', ['max' => 40, 'hint' => 'vaj']),
            'emoji' => $emoji('A hiányzó szó képe'),
            // the longest rhymes (level 3) are asked twice in a row, with another word left out
            'gap2' => $text('Második hiányzó szó (3. szint)', ['required' => false, 'max' => 40, 'hint' => 'házad']),
            'name2' => $text('Második kép neve', ['required' => false, 'max' => 40, 'hint' => 'ház']),
            'emoji2' => ['type' => 'emoji', 'label' => 'Második hiányzó szó képe', 'required' => false],
            'distractors' => ['type' => 'pairs', 'label' => 'További rossz válaszok (ha kell)', 'required' => false, 'hint' => 'soronként: emoji név'],
        ]],
        // Szelektív gyűjtés plays like Válogató (ValogatoRounds): each item is one bin.
        'szelektiv' => ['title' => 'label', 'fields' => [
            'key' => $text('Azonosító', ['max' => 20, 'hint' => 'papir', 'speak' => false]),
            // said in "Válogassuk szét! Papír a kék kukába vagy üveg a zöld konténerbe?"
            'label' => $text('Kuka neve, színnel', ['hint' => 'Papír a kék kukába']),
            // said in "A banánhéj nem papír."
            'singular' => $text('„A banánhéj nem …” alakja', ['hint' => 'papír']),
            'icon' => $emoji('Kuka színe (színes négyzet: 🟦 🟨 🟩 🟫)'),
            // Twemoji has few unmistakable glass and plastic packaging pictures, so a bin may hold as few as two
            'items' => ['type' => 'pairs', 'label' => 'Képek a kukába', 'min' => 2, 'hint' => 'soronként: emoji név (banánhéj, konzervdoboz)'],
        ]],
        'kapdel' => ['title' => 'rule', 'fields' => [
            // level 1 = visual (one picture vs one other), 2 = category, 3 = sound (each picture says its word)
            'rule' => $text('Szabály (Csillám mondja)', ['hint' => 'Kapd el a katicákat! A méhecskéket ne!']),
            'kind' => ['type' => 'select', 'label' => 'Fajta', 'options' => ['visual' => 'Egy fajta kép', 'category' => 'Egy csoport', 'sound' => 'Hang a szóban']],
            'targets' => ['type' => 'pairs', 'label' => 'Ezeket kell elkapni', 'min' => 1, 'hint' => 'soronként: emoji név (alanyesetben: katica)'],
            'others' => ['type' => 'pairs', 'label' => 'Ezeket nem', 'min' => 1, 'hint' => 'soronként: emoji név'],
            // "A répa nem gyümölcs." / "Pedig az alma is gyümölcs."
            'singular' => $text('A csoport neve egyes számban', ['hint' => 'gyümölcs', 'when' => ['kind', 'category']]),
            // the rule turns round mid-stream at level 3 ("Most fordítva!"): what Csillám says then
            'reverse' => $text('Fordított szabály (nem kötelező)', ['hint' => 'Most fordítva! Most a zöldségeket kapd el, a gyümölcsöket ne!', 'required' => false]),
            // named by its letter in what Csillám says ("Ebben nincs r hang."): TTS spells "rrr" out as "er er er"
            'sound' => ['type' => 'select', 'label' => 'Hang', 'options' => ['r' => 'r', 'l' => 'l', 'm' => 'm', 'f' => 'f', 's' => 's', 'sz' => 'sz', 'z' => 'z'], 'when' => ['kind', 'sound']],
        ]],
        // Keresd meg!: "find" = one kind of picture (Keress meg három katicát!, levels 1–2);
        // "clue" = a spoken rule, a colour or a first sound (level 3), everything with its name
        'keresd' => ['title' => 'name', 'fields' => [
            'name' => $text('Kép témája', ['hint' => 'Kert']),
            'backdrop' => ['type' => 'select', 'label' => 'Háttér', 'options' => ['garden' => 'Kert', 'sea' => 'Tenger', 'kitchen' => 'Konyha', 'sky' => 'Égbolt', 'forest' => 'Erdő', 'room' => 'Gyerekszoba', 'night' => 'Éjszakai ég', 'snow' => 'Havas táj']],
            'kind' => ['type' => 'select', 'label' => 'Mit keres a gyerek?', 'options' => ['find' => 'Egyfajta képet (Keress meg három katicát!)', 'clue' => 'Szabályra: szín vagy kezdőhang (3. szint)']],
            'targets' => ['type' => 'pairs', 'label' => 'Amit keresni kell', 'min' => 3, 'hint' => 'soronként: emoji név – egyfajta képnél tárgyesetben (katicát), szabálynál alapalakban (szék)'],
            'fillers' => ['type' => 'emoji_list', 'label' => 'Ami még a képen van', 'min' => 5, 'when' => ['kind', 'find']],
            'others' => ['type' => 'pairs', 'label' => 'Ami nem illik a szabályra', 'min' => 8, 'hint' => 'soronként: emoji név (alapalakban: banán)', 'when' => ['kind', 'clue']],
            'clue' => $text('Keress meg négy …!', ['hint' => 'piros dolgot / olyan dolgot, aminek a neve sz hanggal kezdődik', 'when' => ['kind', 'clue']]),
            'nope' => $text('Ha mást koppint: „A banán …”', ['hint' => 'nem piros / neve nem sz hanggal kezdődik', 'when' => ['kind', 'clue']]),
            'sound' => $text('Kezdőhang (ha arra keres)', ['max' => 3, 'hint' => 'sz', 'required' => false, 'speak' => false, 'when' => ['kind', 'clue']]),
        ]],
        // a theme for the maze (made by LabirintusRounds): who walks, what it looks for, and the sentences
        'labirintus' => ['title' => 'name', 'fields' => [
            'name' => $text('Ki keres mit?', ['hint' => 'Egérke és a sajt', 'speak' => false]),
            'hero' => $emoji('Aki megy'),
            'goal' => $emoji('Amit keres'),
            'task' => $text('Csillám kérése (ragokkal együtt)', ['hint' => 'Vezesd el az egérkét a sajthoz!']),
            'says' => $text('Amikor odaér', ['hint' => 'Megvan a sajt! Köszönöm!']),
        ]],
        // a theme for the robot's grid (made by RobotRounds): its goal, the obstacles, and the sentences
        'robot' => ['title' => 'name', 'fields' => [
            'name' => $text('Mit keres a robot?', ['hint' => 'Elem', 'speak' => false]),
            'goal' => $emoji('Cél'),
            'obstacle' => $emoji('Akadály'),
            'task' => $text('Csillám kérése (ragokkal együtt)', ['hint' => 'Vezesd el a robotot az elemhez!']),
            'bump' => $text('Ha nekimegy az akadálynak', ['hint' => 'Bumm! Ott egy kő van.']),
            'says' => $text('Amikor odaér', ['hint' => 'Hurrá, feltöltődtem!']),
        ]],
        'pontozo' => ['title' => 'name', 'fields' => [
            'name' => $text('Mi lesz a kép?', ['hint' => 'csillag']),
            'emoji' => $emoji(),
            // the outline in drawing order (dot 1 sits on the first point); the app scales it and picks 6/10/15 dots
            'points' => ['type' => 'list', 'label' => 'A körvonal pontjai sorban (x,y 0–1 között, pontosvesszővel)', 'separator' => ';', 'min' => 4, 'hint' => '0.5,0.04; 0.94,0.44; 0.94,0.96; 0.06,0.96', 'speak' => false],
        ]],
        'szinezo' => ['title' => 'name', 'fields' => [
            // the ids of the drawn pictures and their regions: App\Beszed\Rounds\SzinezoRounds::PICTURES
            'picture' => ['type' => 'select', 'label' => 'Kép (a rajzkönyvtárból)', 'options' => [
                'haz' => 'Ház', 'hal' => 'Hal', 'auto' => 'Autó', 'fa' => 'Almafa', 'hajo' => 'Vitorlás', 'sarkany' => 'Sárkány',
                'raketa' => 'Rakéta', 'pillango' => 'Pillangó', 'katica' => 'Katica', 'vonat' => 'Vonat', 'gomba' => 'Gomba',
                'hoember' => 'Hóember', 'bohoc' => 'Bohóc', 'fagyi' => 'Fagyi',
            ]],
            'name' => $text('Neve', ['hint' => 'Ház']),
            'steps' => ['type' => 'list', 'label' => 'Lépések (rész=szín), a legfontosabb elöl', 'separator' => ',', 'min' => 4,
                'hint' => 'teto=piros, ajto=barna, ablak_bal=kek · színek: piros, narancs, sarga, zold, kek, lila, rozsaszin, barna, szurke, fekete'],
        ]],
    ],
];
