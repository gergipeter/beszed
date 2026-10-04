<?php

namespace App\Beszed\Content;

/**
 * Checks one content item against its game's schema (config/beszed_content.php)
 * and the Hungarian rules the game depends on. Used by the content editor and by
 * the tests that validate the seed JSON, so bad content can't reach a child.
 */
final class ContentRules
{
    private const VOWELS = '/[aáeéiíoóöőuúüű]/iu';

    /** Digraphs and the trigraph, longest first: "szék" starts with "sz", not "s". */
    private const MULTI = ['dzs', 'cs', 'dz', 'gy', 'ly', 'ny', 'sz', 'ty', 'zs'];

    /** @return array<string, string> field => Hungarian error message (empty = valid) */
    public static function check(string $game, array $payload): array
    {
        $schema = config("beszed_content.schemas.$game");
        if (! $schema) {
            return ['game' => 'Ismeretlen játék.'];
        }

        $errors = [];
        foreach ($schema['fields'] as $field => $spec) {
            if (($spec['when'] ?? null) && ($payload[$spec['when'][0]] ?? null) !== $spec['when'][1]) {
                continue; // field only applies to another variant (e.g. Okoska category)
            }
            $error = self::checkField($spec, $payload[$field] ?? null);
            if ($error) {
                $errors[$field] = $error;
            }
        }

        return $errors ?: self::checkGame($game, $payload);
    }

    public static function vowelCount(string $word): int
    {
        return preg_match_all(self::VOWELS, $word);
    }

    /** First sound, digraph-aware ("gyöngy" → "gy", "dzsem" → "dzs"). */
    public static function firstSound(string $word): string
    {
        $w = mb_strtolower($word);
        foreach (self::MULTI as $multi) {
            if (str_starts_with($w, $multi)) {
                return $multi;
            }
        }

        return mb_substr($w, 0, 1);
    }

    /** Does the word contain an "s" sound that isn't part of "sz", "zs" or "cs" (or their long forms)? */
    public static function hasPlainS(string $word): bool
    {
        return str_contains(str_replace(['ssz', 'zzs', 'ccs', 'sz', 'zs', 'cs'], '', mb_strtolower($word)), 's');
    }

    private static function checkField(array $spec, mixed $value): ?string
    {
        $required = $spec['required'] ?? true;
        if ($value === null || $value === '' || $value === []) {
            return $required ? 'Kötelező.' : null;
        }

        return match ($spec['type']) {
            'text' => is_string($value) && mb_strlen($value) <= ($spec['max'] ?? 160) ? null : 'Szöveget adj meg (legfeljebb '.($spec['max'] ?? 160).' karakter).',
            'emoji' => self::isPicture($value) ? null : 'Adj meg egy emojit vagy egy piktogramot (arasaac:szám).',
            'select' => in_array($value, array_keys($spec['options']), true) ? null : 'Válassz a listából.',
            'list' => self::stringList($value, $spec['min'] ?? 1) ? null : 'Legalább '.($spec['min'] ?? 1).' elem kell.',
            'emoji_list' => self::stringList($value, $spec['min'] ?? 1) && collect($value)->every(fn ($e) => self::isPicture($e)) ? null : 'Legalább '.($spec['min'] ?? 1).' kép kell (emoji vagy arasaac:szám).',
            'int' => is_int($value) && $value >= ($spec['range'][0] ?? PHP_INT_MIN) && $value <= ($spec['range'][1] ?? PHP_INT_MAX)
                ? null : 'Egész számot adj meg ('.($spec['range'][0] ?? '').'–'.($spec['range'][1] ?? '').').',
            'int_list' => is_array($value) && array_is_list($value) && count($value) >= ($spec['min'] ?? 1)
                && collect($value)->every(fn ($n) => is_int($n) && $n >= ($spec['range'][0] ?? PHP_INT_MIN) && $n <= ($spec['range'][1] ?? PHP_INT_MAX))
                ? null : 'Legalább '.($spec['min'] ?? 1).' szám kell ('.($spec['range'][0] ?? '').'–'.($spec['range'][1] ?? '').').',
            'pairs' => is_array($value) && count($value) >= ($spec['min'] ?? 1)
                && collect($value)->every(fn ($p) => is_array($p) && count($p) === 2 && is_string($p[1]) && $p[1] !== '' && self::isPicture($p[0]))
                ? null : 'Legalább '.($spec['min'] ?? 1).' „emoji név” pár kell.',
            default => 'Ismeretlen mezőtípus.',
        };
    }

    /** An emoji (or a few, for a scene), an ARASAAC pictogram: "arasaac:2462", or an uploaded image: "upload:123". */
    public static function isPicture(mixed $value): bool
    {
        return is_string($value) && (
            (mb_strlen($value) <= 24 && preg_match('/\p{Extended_Pictographic}/u', $value))
            || (config('beszed_content.pictograms') && preg_match('/^arasaac:\d{1,6}$/', $value))
            || preg_match('/^upload:\d+$/', $value)
        );
    }

    private static function stringList(mixed $value, int $min): bool
    {
        return is_array($value) && array_is_list($value) && count($value) >= $min
            && collect($value)->every(fn ($s) => is_string($s) && trim($s) !== '');
    }

    /** Rules between fields, specific to each game. */
    private static function checkGame(string $game, array $p): array
    {
        $lower = fn ($s) => mb_strtolower(trim($s));

        return match ($game) {
            'zs' => match (true) {
                $p['sound'] === 'zs' && ! str_contains($lower($p['word']), 'zs') => ['word' => 'A szóban nincs „zs”.'],
                $p['sound'] === 's' && str_contains($lower($p['word']), 'zs') => ['word' => 'A szóban „zs” van, nem „s”.'],
                $p['sound'] === 's' && ! self::hasPlainS($p['word']) => ['word' => 'A szóban nincs önálló „s” (az „sz” és a „zs” nem számít).'],
                default => [],
            },
            'szotag' => match (true) {
                $lower(implode('', $p['syllables'])) !== $lower($p['word']) => ['syllables' => 'A szótagok együtt nem adják ki a szót.'],
                count($p['syllables']) !== self::vowelCount($p['word']) => ['syllables' => 'Ahány magánhangzó, annyi szótag: '.self::vowelCount($p['word']).' kell.'],
                default => [],
            },
            'kezdo' => self::firstSound($p['word']) === $lower($p['sound']) ? [] : ['sound' => 'A szó nem ezzel a hanggal kezdődik (kezdőhang: „'.self::firstSound($p['word']).'”).'],
            'rimelo', 'rimparok' => str_ends_with($lower($p['word']), $lower($p['rhyme'])) ? [] : ['rhyme' => 'A szó nem erre végződik.'],
            'mondd' => $lower(implode(' ', $p['chunks'])) === $lower($p['text']) ? [] : ['chunks' => 'A darabok együtt nem adják ki a mondatot.'],
            'betuk' => self::firstSound($p['word']) === $lower($p['letter']) ? [] : ['letter' => 'A szó nem ezzel a betűvel kezdődik (kezdőhang: „'.self::firstSound($p['word']).'”).'],
            'tobbes' => match (true) {
                ! str_ends_with($lower($p['pl']), 'k') => ['pl' => 'A többes szám -k végű.'],
                mb_substr($lower($p['pl']), 0, 1) !== mb_substr($lower($p['sg']), 0, 1) => ['pl' => 'A többes szám ugyanazzal a betűvel kezdődik, mint az egyes.'],
                default => [],
            },
            'ellentet' => match (true) {
                $lower($p['a']) === $lower($p['b']) => ['b' => 'A két szó nem lehet ugyanaz.'],
                $p['emojiA'] === $p['emojiB'] => ['emojiB' => 'A két szóhoz két különböző kép kell.'],
                default => [],
            },
            'napirend', 'keszul' => count(array_unique(array_column($p['steps'], 0))) === count($p['steps']) ? [] : ['steps' => 'Egy képet csak egyszer használj.'],
            // a picture in one bin only once (SzelektivTest also keeps each picture to one bin)
            'szelektiv' => count(array_unique(array_column($p['items'], 0))) === count($p['items']) ? [] : ['items' => 'Egy képet csak egyszer használj.'],
            // Kapd el!: targets and others never overlap; a listening rule's words really have (or lack) the sound
            'kapdel' => \App\Beszed\Rounds\KapdelRounds::check($p),
            'elohely', 'szobak' => count(array_unique(array_column($p['items'], 0))) === count($p['items']) ? [] : ['items' => 'Egy képet csak egyszer használj.'],
            'mondat' => match (true) {
                count(explode(' ', trim($p['text']))) < 3 || count(explode(' ', trim($p['text']))) > 5 => ['text' => 'A mondat 3–5 szóból álljon.'],
                count(array_unique(array_map($lower, explode(' ', trim($p['text']), 9)))) !== count(explode(' ', trim($p['text']), 9)) => ['text' => 'Egy szó csak egyszer szerepelhet (különben nem lehet sorrendbe rakni).'],
                default => [],
            },
            'ido' => preg_match('/^([1-9]|1[0-2]):(00|15|30|45)$/', trim($p['time'])) ? [] : ['time' => 'Az idő ó:pp alakú legyen, 1–12 óra, a perc 00, 15, 30 vagy 45.'],
            // the strokes of a letter are drawn by the app (engines/letter/glyphs.js): only those can be written
            'betuiro' => str_contains('IMLTHEFANKVZOCDUPBRSGJliotcunmadbpesáéíóöőúüűÁÉÍÓÖŐÚÜŰ', $p['glyph']) ? [] : ['glyph' => 'Ehhez a betűhöz még nincs rajzolt útvonal.'],
            'szokirako' => preg_match('/^[a-záéíóöőúüű]{2,12}$/u', $p['word']) && $lower($p['word']) === $p['word'] ? [] : ['word' => 'Csak kisbetűs magyar betűk, 2–12 betű.'],
            'olvasd' => $lower(implode('', $p['syllables'])) === $lower($p['word']) ? [] : ['syllables' => 'A szótagok együtt nem adják ki a szót.'],
            'mese' => count(array_unique(array_map(fn ($k) => $p["o$k"][0][0] ?? '', [1, 2, 3]))) >= 1
                && collect([1, 2, 3])->every(fn ($k) => count(array_unique(array_column($p["o$k"], 0))) === count($p["o$k"]))
                ? [] : ['o1' => 'Egy kérdés válaszaihoz különböző képek kellenek.'],
            'melyik' => $lower($p['good']) !== $lower($p['bad']) ? [] : ['bad' => 'A két mondat ugyanaz.'],
            'szajtorna' => count(array_unique(array_column($p['moves'], 1))) === count($p['moves']) ? [] : ['moves' => 'Minden mozdulat más legyen.'],
            // a pair of the same word, or of two words with the same picture, can't be told apart by ear or by eye
            'ikerhangok' => match (true) {
                $lower($p['wordA']) === $lower($p['wordB']) => ['wordB' => 'A két szó nem lehet ugyanaz.'],
                $p['emojiA'] === $p['emojiB'] => ['emojiB' => 'A két szóhoz két különböző kép kell.'],
                default => [],
            },
            // "Koppints a kutyára": the -ra/-re form of the name (a final a/e lengthens: kutya → kutyára)
            'utasitas' => preg_match('/(ra|re)$/u', $lower($p['onto'])) && mb_substr(Hungarian::fold($p['onto']), 0, 2) === mb_substr(Hungarian::fold($p['name']), 0, 2)
                ? [] : ['onto' => 'A szó -ra/-re végű alakja kell (pl. kutya → kutyára, kenyér → kenyérre).'],
            // two identical pictures would make the order ambiguous
            'tortenet' => count(array_unique(array_column($p['steps'], 0))) === count($p['steps']) ? [] : ['steps' => 'Egy képet csak egyszer használj a történetben.'],
            // Same start, ignoring vowel length: ló → lovat, kéz → kezet, kő → követ.
            'szamol', 'merleg', 'osztozas', 'szamok' => str_ends_with($lower($p['accusative']), 't') && mb_substr(Hungarian::fold($p['accusative']), 0, 2) === mb_substr(Hungarian::fold($p['name']), 0, 2)
                ? [] : ['accusative' => 'A tárgyrag -t végű alakja kell (pl. alma → almát).'],
            // the hero and its goal (the robot's goal and the obstacles) must look different on the board
            'labirintus' => $p['hero'] === $p['goal'] ? ['goal' => 'Aki megy és amit keres, két különböző kép legyen.'] : [],
            'robot' => match (true) {
                $p['goal'] === $p['obstacle'] => ['obstacle' => 'A cél és az akadály két különböző kép legyen.'],
                in_array(\App\Beszed\Rounds\RobotRounds::HERO, [$p['goal'], $p['obstacle']], true) => ['goal' => 'A robot képe nem lehet cél vagy akadály.'],
                default => [],
            },
            // the sound really is where the item says (digraph-aware), and no look-alike sound blurs the drill
            'hanggyakorlo' => \App\Beszed\Rounds\HanggyakorloRounds::check($p),
            // a scene can only show some kinds of blowing (candles go out puff by puff, a feather floats on a soft blow)
            'fujoka' => in_array($p['scene'], \App\Beszed\Rounds\FujokaRounds::SCENES[$p['kind']] ?? [], true)
                ? [] : ['scene' => 'Ez a kép ehhez a fújáshoz nem illik (lehet: '.implode(', ', \App\Beszed\Rounds\FujokaRounds::SCENES[$p['kind']] ?? []).').'],
            // the model is the sound held long, as the voice can say it; high and low need a voiced sound
            'hangrepulo' => match (true) {
                ! \App\Beszed\Rounds\HangrepuloRounds::modelFits($p['sound'], $p['shown']) => ['shown' => 'A hangot hosszan, ahogy hangzik: ááá, mmm, sssz (legalább három betű).'],
                ! \App\Beszed\Rounds\HangrepuloRounds::speakable($p['sound'], $p['shown'], $p['model']) => ['model' => 'Minden szóban legyen magánhangzó, és ne legyen benne „…” (a gépi hang betűzné). Magánhangzónál mondja is ki: „Mondd hosszan: ááá”; mássalhangzót igével kérj: „Sziszegj hosszan”.'],
                $p['kind'] === 'pitch' && ! in_array($p['sound'], \App\Beszed\Rounds\HangrepuloRounds::VOICED, true) => ['sound' => 'Magas és mély hangot csak zöngés hanggal lehet (á, ú, m, z…), sz-szel, s-sel, f-fel nem.'],
                default => [],
            },
            // Keresd meg!: no picture both wanted and not wanted; a sound clue really fits (digraph-aware)
            'keresd' => \App\Beszed\Rounds\KeresdRounds::check($p),
            // Pontról pontra: an outline of points between 0 and 1
            'pontozo' => \App\Beszed\Rounds\PontozoRounds::check($p),
            // Színező: a picture of the client's library, its own regions, known paint colours
            'szinezo' => \App\Beszed\Rounds\SzinezoRounds::contentErrors($p),
            // Találós kérdések: no clue says the answer itself
            'talalos' => \App\Beszed\Rounds\TalalosRounds::check($p),
            // Igaz vagy butaság?: a silly sentence comes with its right version
            'igazvagy' => \App\Beszed\Rounds\IgazvagyRounds::check($p),
            // Mondókázz!: the missing word really stands in the rhyme
            'mondoka' => \App\Beszed\Rounds\MondokaRounds::check($p),
            // Mozgó szavak: the én/te/mi/ők forms look like forms of this verb
            'igek' => \App\Beszed\Rounds\IgekRounds::check($p),
            // Ki mondja?: an "aki …" name is a person; nothing is close to itself
            'hangutanzo' => \App\Beszed\Rounds\HangutanzoRounds::check($p),
            // Testrészek: a picture that also fits is not the answer itself
            'testreszek' => \App\Beszed\Rounds\TestreszekRounds::check($p),
            // Mi illik hozzá?: a pair or a tool has its picture and 3+ wrong ones; a group shows 3–4 pictures
            'illik' => \App\Beszed\Rounds\IllikRounds::check($p),
            // Hangvonat: the sound is in the word exactly once, where pos says, and sounds as written (digraph-aware)
            'hangvonat' => \App\Beszed\Rounds\HangvonatRounds::check($p),
            // Csigabeszéd: the pieces are the word's syllables, in order
            'csigabeszed' => match (true) {
                $lower(implode('', $p['pieces'])) !== $lower($p['word']) => ['pieces' => 'A darabok együtt nem adják ki a szót.'],
                Hungarian::syllables(trim($p['word'])) !== array_map('trim', $p['pieces']) => ['pieces' => 'A darabok a szó szótagjai legyenek: '.implode(' | ', Hungarian::syllables(trim($p['word'])) ?? ['?']).'.'],
                default => [],
            },
            // Szóragasztó: the two parts glued give the word; the inflected forms belong to their words
            'szoragaszto' => match (true) {
                $lower($p['a'].$p['b']) !== $lower($p['word']) => ['b' => 'A két tag összeragasztva nem adja ki a szót.'],
                count(array_unique([$p['emoji'], $p['emojiA'], $p['emojiB']])) < 3 => ['emojiB' => 'A szóhoz és a két tagjához három különböző kép kell.'],
                ! preg_match('/(ból|ből)$/u', $lower($p['from'])) || ! str_starts_with(Hungarian::fold($p['from']), Hungarian::fold($p['word'])) => ['from' => 'A szó -ból/-ből alakja kell (hóember → hóemberből).'],
                ! str_ends_with($lower($p['accA']), 't') || mb_substr($lower($p['accA']), 0, 1) !== mb_substr($lower($p['a']), 0, 1) => ['accA' => 'Az első tag -t végű alakja kell (hó → havat).'],
                ! str_ends_with($lower($p['accB']), 't') || mb_substr($lower($p['accB']), 0, 1) !== mb_substr($lower($p['b']), 0, 1) => ['accB' => 'A második tag -t végű alakja kell (ember → embert).'],
                default => [],
            },
            default => [],
        };
    }
}
