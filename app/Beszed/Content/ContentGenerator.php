<?php

namespace App\Beszed\Content;

/**
 * Builds the seed content of the games from the Hungarian word bank
 * (database/lexicon/hu.json): syllables, first sounds, rhymes, categories and
 * sentences are worked out from each word, and every item has to pass
 * ContentRules. Hand-written rows in the seed files are kept; generated rows
 * carry "gen": true and are rebuilt on every run. Okoska and Válogató
 * categories are merged with the word bank instead (a category can't be split
 * over two rows); the pictures the word bank added are listed in "gen_emojis"
 * so the next run replaces exactly those.
 */
final class ContentGenerator
{
    /** Games built from the word bank. Other games' files are left alone. */
    public const GAMES = ['zs', 'szotag', 'kezdo', 'hol', 'szamol', 'okoska', 'papagaj', 'melyik', 'kirako', 'parkereso', 'arnyek', 'rimelo', 'valogato'];

    /** Word bank category → Okoska's "odd one out" group (singular, as in "A többi mind …"). */
    private const OKOSKA = [
        'animal' => 'állat', 'fruit' => 'gyümölcs', 'vegetable' => 'zöldség', 'vehicle' => 'jármű',
        'clothing' => 'ruha', 'instrument' => 'hangszer', 'tool' => 'szerszám', 'flower' => 'virág',
        'sweet' => 'édesség', 'ball' => 'labda', 'school' => 'iskolaszer', 'building' => 'épület',
        'kitchen' => 'konyhai eszköz',
    ];

    private const OKOSKA_LEVEL = ['iskolaszer' => 2, 'épület' => 2, 'konyhai eszköz' => 3];

    /** Word bank category → Válogató basket; new baskets get these labels. */
    private const VALOGATO = [
        'animal' => 'allat', 'fruit' => 'etel', 'vegetable' => 'etel', 'food' => 'etel', 'vehicle' => 'jarmu',
        'clothing' => 'ruha', 'toy' => 'jatek', 'instrument' => 'hangszer', 'tool' => 'szerszam', 'weather' => 'ido',
        'kitchen' => 'konyha', 'body' => 'test', 'building' => 'epulet', 'school' => 'iskola', 'flower' => 'virag',
    ];

    private const NEW_BASKETS = [
        'iskola' => ['label' => 'Iskolaszerek', 'singular' => 'iskolaszer', 'icon' => '📚'],
        'virag' => ['label' => 'Virágok', 'singular' => 'virág', 'icon' => '🪴'],
    ];

    /** Számolós puts things in a basket: nothing you don't count one by one, nothing too big or odd for it. */
    private const NOT_IN_BASKET = ['tej', 'vaj', 'méz', 'só', 'rizs', 'leves', 'tészta', 'hús', 'puding', 'saláta',
        'tévé', 'számítógép', 'rádió', 'telefon', 'sátor', 'kerék', 'horgony', 'fényképezőgép', 'iránytű', 'kosár',
        'mikrofon', 'fejhallgató', 'esernyő', 'tű', 'vízcsepp', 'hold', 'hegy', 'fa', 'fenyőfa', 'pálma', 'kaktusz',
        'vulkán', 'sziget', 'bolygó', 'földgömb', 'tűz', 'hullám', 'fészek', 'csúszda', 'kirakó', 'ajándék', 'robot',
        'buborék', 'szánkó', 'gördeszka', 'korcsolya', 'síléc', 'hátizsák', 'könyv', 'füzet', 'gyertya', 'homokóra',
        'zászló', 'kupa', 'óra', 'doboz', 'boríték', 'zseblámpa', 'nagyító'];

    private const SZAMOL_CATEGORIES = ['fruit', 'vegetable', 'food', 'toy', 'school', 'flower', 'thing', 'nature'];

    private const HOL_CATEGORIES = ['animal', 'toy', 'fruit', 'thing', 'school'];

    private const ARNYEK_CATEGORIES = ['animal', 'vehicle', 'tool', 'instrument', 'clothing', 'kitchen'];

    /** Generated grammar pairs are formulaic; the hand-written ones should stay a good share. */
    private const MELYIK_MAX = 100;

    /** @var list<array{w: string, e?: string, p?: int, c: string, f: int, acc?: string, sub?: string, src?: string}> */
    private array $words;

    /** @var array<string, list<array>> game => rows currently in its seed file */
    private array $current;

    /** @var array<string, list<string>> rows skipped because ContentRules refused them */
    public array $rejected = [];

    /** @var ?array<string, int> memoized categoryRanks() */
    private ?array $categoryRanks = null;

    public function __construct(array $lexicon, array $current)
    {
        $this->words = $lexicon;
        $this->current = $current;
    }

    public static function fromFiles(): self
    {
        $read = fn (string $path) => json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $current = [];
        foreach (self::GAMES as $game) {
            $current[$game] = $read(database_path("seeders/data/beszed/$game.json"));
        }

        return new self($read(database_path('lexicon/hu.json')), $current);
    }

    /** @return array<string, list<array>> game => rows for its seed file */
    public function build(): array
    {
        $out = [];
        foreach (self::GAMES as $game) {
            $out[$game] = match ($game) {
                'okoska' => $this->okoska(),
                'valogato' => $this->valogato(),
                default => $this->words($game),
            };
        }

        return $out;
    }

    /**
     * Papagáj, Párkereső and Kirakó each draw from the whole common-word pool with
     * no level filter (their Rounds classes never call favorLevel()), so every word
     * is in play every session: left alone, all three would show nearly the same
     * ~750 words and feel like reshuffles of each other, especially at a new
     * child's very first plays. Split the pool between them instead, evenly within
     * each lexicon category (so Kirakó's picture themes - animals, vehicles... -
     * all stay populated) and stable across regenerations (by word, not by run).
     */
    private const SPLIT_POOL = ['papagaj', 'parkereso', 'kirako'];

    /** @return array<string, int> word => its index among same-category words, for the round-robin split below */
    private function categoryRanks(): array
    {
        if ($this->categoryRanks !== null) {
            return $this->categoryRanks;
        }
        $ranks = [];
        $seen = [];
        foreach ($this->words as $w) {
            if ($w['f'] > 2) {
                continue;
            }
            $seen[$w['c']] ??= 0;
            $ranks[$w['w']] = $seen[$w['c']]++;
        }

        return $this->categoryRanks = $ranks;
    }

    /** Whether $word is $game's share of the round-robin split (see SPLIT_POOL doc). */
    private function inSplit(string $game, string $word): bool
    {
        $slot = array_search($game, self::SPLIT_POOL, true);

        return $this->categoryRanks()[$word] % count(self::SPLIT_POOL) === $slot;
    }

    /** Games with one row per word: hand-written rows first, then new words (none twice, no picture twice). */
    private function words(string $game): array
    {
        $kept = array_values(array_filter($this->current[$game], fn ($row) => empty($row['gen'])));
        $title = config("beszed_content.schemas.$game.title");
        $seenTitle = [];
        $seenEmoji = [];
        foreach ($kept as $row) {
            $seenTitle[mb_strtolower(json_encode($row['payload'][$title] ?? null, JSON_UNESCAPED_UNICODE))] = true;
            foreach ($this->emojisOf($row['payload']) as $e) {
                $seenEmoji[$e] = true;
            }
        }

        $rows = $kept;
        $max = $game === 'melyik' ? self::MELYIK_MAX : PHP_INT_MAX;
        foreach ($this->candidates($game, $kept) as [$level, $payload]) {
            if (count($rows) - count($kept) >= $max) {
                break;
            }
            $key = mb_strtolower(json_encode($payload[$title] ?? null, JSON_UNESCAPED_UNICODE));
            $emojis = $this->emojisOf($payload);
            if (isset($seenTitle[$key]) || array_filter($emojis, fn ($e) => isset($seenEmoji[$e]))) {
                continue;
            }
            if ($errors = ContentRules::check($game, $payload)) {
                $this->rejected[$game][] = json_encode($payload, JSON_UNESCAPED_UNICODE).' → '.implode(' ', $errors);

                continue;
            }
            $seenTitle[$key] = true;
            foreach ($emojis as $e) {
                $seenEmoji[$e] = true;
            }
            $rows[] = ['level' => $level, 'payload' => $payload, 'gen' => true];
        }

        return $rows;
    }

    /** @return iterable<array{0: int, 1: array}> [level, payload] per usable word */
    private function candidates(string $game, array $kept): iterable
    {
        $common = array_values(array_filter($this->words, fn ($w) => $w['f'] <= 2));

        foreach ($common as $i => $w) {
            $word = $w['w'];
            // Words added with an ARASAAC pictogram: fine for the sound and memory games;
            // the category, counting and grammar games keep to the hand-checked words.
            $imported = ($w['src'] ?? null) === 'arasaac';
            $syllables = Hungarian::syllables($word);
            $count = ContentRules::vowelCount($word);

            switch ($game) {
                case 'zs':
                    // Only words with exactly one of the two sounds (and no "dzs", a different sound).
                    $lower = mb_strtolower($word);
                    $zs = str_contains($lower, 'zs');
                    if (! str_contains($lower, 'dzs') && $zs !== ContentRules::hasPlainS($word)) {
                        $length = $count <= 2 ? 1 : ($count === 3 ? 2 : 3);
                        yield [min(3, $length + $w['f'] - 1), ['word' => $word, 'emoji' => $this->picture($w), 'sound' => $zs ? 'zs' : 's']];
                    }
                    break;

                case 'szotag':
                    if ($syllables) {
                        yield [min(3, count($syllables)), ['word' => $word, 'emoji' => $this->picture($w), 'syllables' => $syllables]];
                    }
                    break;

                case 'kezdo':
                    $sound = ContentRules::firstSound($word);
                    yield [$this->soundLevel($sound), ['sound' => $sound, 'word' => $word, 'emoji' => $this->picture($w)]];
                    break;

                case 'hol':
                    if ($w['f'] === 1 && ! $imported && in_array($w['c'], self::HOL_CATEGORIES, true)) {
                        yield [1, ['name' => $word, 'emoji' => $this->picture($w)]];
                    }
                    break;

                case 'szamol':
                    if (isset($w['acc']) && in_array($w['c'], self::SZAMOL_CATEGORIES, true) && ! in_array($word, self::NOT_IN_BASKET, true)) {
                        yield [1, ['name' => $word, 'accusative' => $w['acc'], 'emoji' => $this->picture($w)]];
                    }
                    break;

                case 'melyik':
                    if (isset($w['acc']) && $w['f'] === 1 && ($sentence = $this->sentence($w, $i))) {
                        yield $sentence;
                    }
                    break;

                case 'papagaj':
                    if ($this->inSplit($game, $word)) {
                        yield [1, ['word' => $word, 'emoji' => $this->picture($w)]];
                    }
                    break;

                case 'parkereso':
                    if ($this->inSplit($game, $word)) {
                        yield [1, ['emoji' => $this->picture($w), 'word' => $word]];
                    }
                    break;

                case 'kirako':
                    if ($this->inSplit($game, $word)) {
                        yield [1, ['emoji' => $this->picture($w), 'name' => $word]];
                    }
                    break;

                case 'arnyek':
                    if (in_array($w['c'], self::ARNYEK_CATEGORIES, true)) {
                        yield [$w['c'] === 'animal' ? 1 : 2, ['emoji' => $this->picture($w), 'name' => $word]];
                    }
                    break;

                case 'rimelo':
                    yield from $this->rhymes($common, $kept);

                    return;
            }
        }
    }

    /**
     * Words that rhyme with at least one other word (their ending as the rhyme).
     * Compounds are left out: "kosárlabda" with "labda" isn't a rhyme, it's the same word.
     */
    private function rhymes(array $common, array $kept): iterable
    {
        $groups = [];
        $taken = [];
        foreach ($kept as $row) {
            $groups[$row['payload']['rhyme']] = ($groups[$row['payload']['rhyme']] ?? 0) + 1;
            $taken[mb_strtolower($row['payload']['word'])] = $taken[$row['payload']['emoji']] = true;
        }
        // Compound of any known word: the word bank's and the hand-written rhymes' (mosógép / gép).
        $all = [...array_map(fn ($w) => mb_strtolower($w['w']), $this->words), ...array_map(fn ($row) => mb_strtolower($row['payload']['word']), $kept)];
        $isCompound = fn (string $word) => (bool) array_filter($all, fn ($other) => $other !== $word && mb_strlen($other) >= 2 && str_ends_with($word, $other));

        $candidates = [];
        foreach ($common as $w) {
            $lower = mb_strtolower($w['w']);
            $rhyme = Hungarian::rhyme($w['w']);
            if ($rhyme === null || mb_strlen($rhyme) > 6 || isset($taken[$lower]) || isset($taken[$this->picture($w)]) || $isCompound($lower)) {
                continue;
            }
            $taken[$lower] = $taken[$this->picture($w)] = true;
            $groups[$rhyme] = ($groups[$rhyme] ?? 0) + 1;
            $candidates[] = [$w, $rhyme];
        }
        foreach ($candidates as [$w, $rhyme]) {
            if ($groups[$rhyme] >= 2) {
                yield [ContentRules::vowelCount($w['w']) === 1 ? 1 : 2, ['word' => $w['w'], 'emoji' => $this->picture($w), 'rhyme' => $rhyme]];
            }
        }
    }

    /** A right and a wrong sentence around the word's accusative (Látok egy almát / Látok egy alma). */
    private function sentence(array $w, int $i): ?array
    {
        [$word, $acc, $the] = [$w['w'], $w['acc'], Hungarian::article($w['w'])];

        return match ($w['c']) {
            'animal', 'person' => [1, ['good' => "Látok egy $acc.", 'bad' => "Látok egy $word.", 'emoji' => $this->picture($w)]],
            'fruit', 'vegetable', 'food' => [1, ['good' => "Szeretem $the $acc.", 'bad' => "Szeretem $the $word.", 'emoji' => $this->picture($w)]],
            'building', 'nature', 'weather', 'flower' => [2, ['good' => "Látom $the $acc.", 'bad' => "Látom $the $word.", 'emoji' => $this->picture($w)]],
            'toy', 'school', 'thing', 'clothing', 'kitchen', 'tool', 'instrument', 'vehicle', 'house' => $i % 2
                ? [2, ['good' => "Hol van $the $word?", 'bad' => "Hol van $the $acc?", 'emoji' => $this->picture($w)]]
                : [2, ['good' => "Megkeresem $the $acc.", 'bad' => "Megkeresem $the $word.", 'emoji' => $this->picture($w)]],
            default => null,
        };
    }

    private function soundLevel(string $sound): int
    {
        return match (true) {
            in_array($sound, ['m', 'h', 'l', 'f', 's', 'a', 'e', 'o', 'u', 'v', 'n', 'j', 'á', 'é', 'i', 'í', 'ó', 'ö', 'ő', 'ú', 'ü', 'ű'], true) => 1,
            in_array($sound, ['sz', 'cs', 'zs', 'gy', 'ny', 'ty', 'ly', 'c', 'dz', 'dzs'], true) => 3,
            default => 2,
        };
    }

    /**
     * Okoska: each hand-written group gets the word bank's pictures of that group,
     * listed in "gen_emojis" so the next run can take them out again; groups the
     * word bank adds are whole generated rows.
     */
    private function okoska(): array
    {
        $rows = [];
        $groups = [];
        foreach ($this->current['okoska'] as $row) {
            if (! empty($row['gen'])) {
                continue;
            }
            if (($row['payload']['kind'] ?? null) !== 'category') {
                $rows[] = $row; // the pattern symbols

                continue;
            }
            $added = $row['gen_emojis'] ?? [];
            $row['payload']['emojis'] = array_values(array_diff($row['payload']['emojis'], $added));
            $groups[$row['payload']['category']] = ['level' => $row['level'], 'payload' => $row['payload'], 'gen_emojis' => []];
        }

        $taken = [];
        foreach ($groups as $group => $row) {
            foreach ($row['payload']['emojis'] as $e) {
                $taken[$e] = true;
            }
        }
        foreach ($this->words as $w) {
            $group = self::OKOSKA[$w['sub'] ?? ''] ?? self::OKOSKA[$w['c']] ?? null;
            if (! $group || ! isset($w['e']) || isset($taken[$w['e']]) || $w['f'] > 2) {
                continue;
            }
            $groups[$group] ??= ['level' => self::OKOSKA_LEVEL[$group] ?? 2, 'payload' => ['kind' => 'category', 'category' => $group, 'emojis' => []], 'gen' => true];
            $groups[$group]['payload']['emojis'][] = $w['e'];
            if (empty($groups[$group]['gen'])) {
                $groups[$group]['gen_emojis'][] = $w['e'];
            }
            $taken[$w['e']] = true;
        }

        foreach ($groups as $row) {
            if (count($row['payload']['emojis']) >= 4) {
                $rows[] = $this->withMarks($row);
            }
        }

        return $rows;
    }

    /**
     * Válogató: every basket gets the word bank's words of its kind (a picture or
     * word is only ever in one basket), marked the same way as in Okoska.
     */
    private function valogato(): array
    {
        $baskets = [];
        foreach ($this->current['valogato'] as $row) {
            if (! empty($row['gen'])) {
                continue;
            }
            $added = $row['gen_emojis'] ?? [];
            $row['payload']['items'] = array_values(array_filter($row['payload']['items'], fn ($item) => ! in_array($item[0], $added, true)));
            $baskets[$row['payload']['key']] = ['level' => $row['level'], 'payload' => $row['payload'], 'gen_emojis' => []];
        }

        $emojiIn = [];
        $wordIn = [];
        foreach ($baskets as $row) {
            foreach ($row['payload']['items'] as [$e, $name]) {
                $emojiIn[$e] = $wordIn[mb_strtolower($name)] = true;
            }
        }
        foreach ($this->words as $w) {
            $key = self::VALOGATO[$w['c']] ?? null;
            if (! $key || ! isset($w['e']) || $w['f'] > 2 || isset($emojiIn[$w['e']]) || isset($wordIn[mb_strtolower($w['w'])])) {
                continue;
            }
            $baskets[$key] ??= ['level' => 1, 'payload' => ['key' => $key] + self::NEW_BASKETS[$key] + ['items' => []], 'gen' => true];
            $baskets[$key]['payload']['items'][] = [$w['e'], $w['w']];
            if (empty($baskets[$key]['gen'])) {
                $baskets[$key]['gen_emojis'][] = $w['e'];
            }
            $emojiIn[$w['e']] = $wordIn[mb_strtolower($w['w'])] = true;
        }

        return array_values(array_map(fn ($row) => $this->withMarks($row), $baskets));
    }

    /** A merged row keeps "gen_emojis" only when the word bank added something; a generated one is "gen". */
    private function withMarks(array $row): array
    {
        $out = ['level' => $row['level'], 'payload' => $row['payload']];
        if (! empty($row['gen'])) {
            $out['gen'] = true;
        } elseif ($row['gen_emojis']) {
            $out['gen_emojis'] = $row['gen_emojis'];
        }

        return $out;
    }

    /** The picture a word shows in the content: its emoji, else its ARASAAC pictogram. */
    private function picture(array $w): string
    {
        return $w['e'] ?? "arasaac:{$w['p']}";
    }

    /** @return list<string> */
    private function emojisOf(array $payload): array
    {
        return array_values(array_filter([$payload['emoji'] ?? null]));
    }
}
