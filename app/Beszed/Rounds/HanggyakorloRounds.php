<?php

namespace App\Beszed\Rounds;

use App\Beszed\Content\Hungarian;
use Illuminate\Support\Collection;

/**
 * Hanggyakorló: an articulation drill by target sound (like Articulation Station). The child picks a sound
 * (or "vegyesen": all of them mixed), sees a picture and says its name; they may record themselves and hear
 * their own voice back (only on the device), then judge with a parent: "Jól mondtam!" or "Még gyakorlom".
 *
 * $level spans 1–100 (RoundFactory::tier(), 3 bands): tier 1 (levels 1–33) → the sound at the start of a
 * word, Csillám says it first; tier 2 (34–66) → in the middle or at the end; tier 3 (67–100) → naming
 * without a model ("Mi ez? Mondd ki!", Csillám says the word only after the judging), a short sentence
 * full of the sound every few rounds. Within tier 3 a phrase comes a little less often and only half the
 * non-phrase rounds are naming near level 67; by level 100 a phrase returns every third round and every
 * other round is naming, as the old level 3 always did.
 */
class HanggyakorloRounds extends RoundFactory
{
    /** The sounds to practise = the game's categories (config/beszed.php). */
    public const SOUNDS = ['r', 'l', 's', 'sz', 'z', 'zs', 'c', 'cs', 'k', 'g', 'gy', 'ty'];

    /**
     * The sounds a child tends to say instead of the target: a practice word must not have them too
     * (an "s" word with "sz" in it, an "r" word with "l"), or the drill would blur the very contrast it trains.
     */
    public const LOOKALIKES = [
        'r' => ['l'], 'l' => ['r'],
        's' => ['sz', 'zs', 'cs'], 'sz' => ['s', 'z', 'c'], 'z' => ['zs', 'sz'], 'zs' => ['z', 's'],
        'c' => ['cs', 'sz'], 'cs' => ['c', 's'],
        'k' => ['g'], 'g' => ['k'],
        'gy' => ['ty', 'd'], 'ty' => ['gy'],
    ];

    /** Doubled spellings of a sound (asszony, hattyú): the same sound, said long. */
    private const LONG = ['ssz' => 'sz', 'ccs' => 'cs', 'ggy' => 'gy', 'tty' => 'ty', 'zzs' => 'zs', 'lly' => 'ly', 'nny' => 'ny', 'ddz' => 'dz', 'ddzs' => 'dzs'];

    /** Every this-many rounds (from the 2nd) the 3rd level brings a sentence instead of a picture to name. */
    private const PHRASE_EVERY = 3;

    private const REPEAT = ['Mondd utánam: %s!', 'Most ezt mondd: %s!', 'Te jössz: %s!', 'Mondd szépen: %s!', 'Figyelj: %s. Most te!'];

    private const NAME = ['Mi ez? Mondd ki!', 'Mi van a képen? Mondd ki hangosan!', 'Hogy hívják ezt? Mondd ki!'];

    private const PRAISE = ['Ügyes vagy!', 'Szép volt!', 'Nagyon szépen mondtad!', 'Szuper!', 'Így kell!', 'Bravó!'];

    private const RETRY = ['Semmi baj, gyakoroljunk!', 'Próbáljuk együtt!', 'Még egyszer, lassan!'];

    /** The word's sounds, digraph-aware: "hattyú" → h, a, ty, ú; "szőlő" → sz, ő, l, ő. @return list<string> */
    public static function sounds(string $text): array
    {
        return array_map(fn ($l) => self::LONG[$l['letter']] ?? $l['letter'], Hungarian::letters($text));
    }

    /**
     * Syllables to say slowly; a long consonant between two vowels is cut in two, as Hungarian
     * hyphenates it (hattyú → haty-tyú, asszony → asz-szony): each half is the whole sound.
     *
     * @return list<string>|null
     */
    public static function syllables(string $word): ?array
    {
        $plain = Hungarian::syllables($word);
        if ($plain !== null) {
            return $plain;
        }
        // "tty" → "ty": cut the shortened word; a syllable that starts with the shortened sound gives it to the one before too
        $short = preg_replace('/(?:s(?=sz)|c(?=cs)|g(?=gy)|t(?=ty)|z(?=zs)|l(?=ly)|n(?=ny))/u', '', $word);
        $parts = Hungarian::syllables($short);
        if ($parts === null) {
            return null;
        }
        $out = [];
        $at = 0;
        foreach ($parts as $part) {
            $n = mb_strlen($part);
            if ($out && mb_substr($word, $at, $n) !== $part && mb_substr($word, $at + 1, $n) === $part) {
                $out[count($out) - 1] .= mb_substr($part, 0, 2);
                $at++;
            }
            $out[] = $part;
            $at += $n;
        }

        return $at === mb_strlen($word) ? $out : null;
    }

    /**
     * Content rule (ContentRules): the sound really occurs where `pos` says — a word, with the sound first
     * (start), or not first but inside (middle) or last (end); a sentence (phrase) with it at least three
     * times — and none of the sound's look-alikes occurs anywhere.
     *
     * @return array<string, string>
     */
    public static function check(array $p): array
    {
        $sound = $p['sound'];
        $text = trim($p['word']);
        $sounds = self::sounds($text);
        $quote = "„{$sound}”";

        if ($p['pos'] === 'phrase') {
            if (! str_contains($text, ' ')) {
                return ['word' => 'Mondatban gyakoroláshoz legalább két szó kell.'];
            }
            if (count(array_keys($sounds, $sound, true)) < 3) {
                return ['word' => "A mondatban legalább háromszor legyen $quote hang."];
            }
        } else {
            if (! preg_match('/^\p{Ll}+$/u', $text)) {
                return ['word' => 'Egyetlen szó kell, kisbetűvel (mondatot a „Mondatban, többször” beállítással adj meg).'];
            }
            $inside = array_slice($sounds, 1, -1);
            $error = match ($p['pos']) {
                'start' => $sounds[0] !== $sound ? "A szó nem $quote hanggal kezdődik." : null,
                'middle' => ! in_array($sound, $inside, true) ? "A szó közepén nincs $quote hang." : null,
                'end' => end($sounds) !== $sound ? "A szó nem $quote hangra végződik." : null,
                default => null,
            };
            if ($error === null && $p['pos'] !== 'start' && $sounds[0] === $sound) {
                $error = "A szó $quote hanggal kezdődik: azt a szó eleje gyakorolja.";
            }
            if ($error) {
                return ['pos' => $error];
            }
        }

        $also = array_values(array_intersect(self::LOOKALIKES[$sound] ?? [], $sounds));

        return $also ? ['word' => "Ebben „{$also[0]}” hang is van, ami könnyen összekeveredik a $quote hanggal. Válassz másikat!"] : [];
    }

    public function build(Collection $items, int $level, int $count): array
    {
        $sound = in_array($this->options['category'] ?? null, self::SOUNDS, true) ? $this->options['category'] : null;
        $pool = $sound ? $items->filter(fn ($i) => $i->payload['sound'] === $sound)->values() : $items->values();
        if ($pool->isEmpty()) {
            $pool = $items->values();
        }
        $words = $pool->filter(fn ($i) => $i->payload['pos'] !== 'phrase')->values();
        $words = $words->isEmpty() ? $pool : $words;
        $where = fn (array $pos) => $words->filter(fn ($i) => in_array($i->payload['pos'], $pos, true))->values();

        // what each round is: [item, mode]
        $tier = $this->tier($level, 3);
        if ($tier >= 3) {
            // within tier 3 (levels 67–100): how often a phrase comes up (one every PHRASE_EVERY+1 rounds
            // near the tier's start, down to the old PHRASE_EVERY by level 100) and how much of the rest
            // is named instead of modelled (half near the tier's start, all of it — as the old level 3
            // always did — by level 100).
            $within = $this->withinTier($level, 3);
            $phraseEvery = max(2, (int) round((self::PHRASE_EVERY + 1) - $within));
            $phraseCount = intdiv($count + 1, $phraseEvery);
            $phrases = $this->pick($pool->filter(fn ($i) => $i->payload['pos'] === 'phrase')->values(), $phraseCount);
            $named = $words->filter(fn ($i) => ($i->payload['naming'] ?? 'no') === 'yes')->values();
            $namingShare = 0.5 + $within * 0.5;
            $names = $this->pick($named->isEmpty() ? $words : $named, $count - $phrases->count());
            $plan = [];
            for ($r = 0; $r < $count; $r++) {
                $phrase = $r % $phraseEvery === 1 && $phrases->isNotEmpty();
                $item = $phrase ? $phrases->shift() : ($names->shift() ?? $phrases->shift());
                $name = ! $phrase && $item->payload['pos'] !== 'phrase' && ! $named->isEmpty() && (mt_rand() / mt_getrandmax()) < $namingShare;
                $plan[] = [$item, $phrase || $item->payload['pos'] === 'phrase' || $named->isEmpty() ? 'repeat' : ($name ? 'name' : 'repeat')];
            }
        } else {
            $band = $where($tier === 1 ? ['start'] : ['middle', 'end']);
            $plan = $this->pick($band->isEmpty() ? $words : $band, $count)->map(fn ($i) => [$i, 'repeat'])->all();
        }

        $rounds = [];
        $last = ['repeat' => null, 'name' => null, 'praise' => null];
        $seen = ['word' => false, 'phrase' => false, 'name' => false];
        foreach ($plan as [$item, $mode]) {
            $rounds[] = $this->say($item, $mode, $seen, $last);
        }

        return $rounds;
    }

    /** 0.0 at $tier's first level, 1.0 at its last (tier(), 3 equal bands on 1–100): position inside the band. */
    private function withinTier(int $level, int $tier): float
    {
        $size = 100 / 3;
        $first = (int) round(($tier - 1) * $size) + 1;
        $last = (int) round($tier * $size);

        return $last <= $first ? 0.0 : max(0.0, min(1.0, ($level - $first) / ($last - $first)));
    }

    /**
     * $n items: with one sound, whole weighted passes of the pool; with all sounds, one sound after the
     * other in random order, so a mixed session really mixes them.
     */
    private function pick(Collection $pool, int $n): Collection
    {
        if ($pool->isEmpty() || $n <= 0) {
            return collect();
        }
        $bySound = $pool->groupBy(fn ($i) => $i->payload['sound']);
        if ($bySound->count() === 1) {
            return $this->cycle($pool, $n);
        }

        $queues = $bySound->map(fn ($items) => $this->weightedShuffle($items->values())->all())->shuffle()->values()->all();
        $out = [];
        for ($k = 0; count($out) < $n; $k++) {
            foreach ($queues as $q) {
                if (count($out) >= $n) {
                    break;
                }
                $out[] = $q[$k % count($q)];
            }
        }

        return collect($out);
    }

    /** One round: the picture, what Csillám says when, and every feedback sentence. */
    private function say($item, string $mode, array &$seen, array &$last): array
    {
        $p = $item->payload;
        $text = trim($p['word']);
        $phrase = $p['pos'] === 'phrase';
        $spoken = $phrase ? $text : $this->ucfirst($text).'.';
        // said slowly, piece by piece: a word's syllables, a sentence's words
        $pieces = $phrase ? preg_split('/\s+/u', $text) : (self::syllables($text) ?? [$text]);
        $slow = count($pieces) > 1 ? [...$pieces, $spoken] : [$spoken];
        $praise = $last['praise'] = $this->pickNot(self::PRAISE, $last['praise']);

        if ($mode === 'name') {
            $prompt = $seen['name'] ? ($last['name'] = $this->pickNot(self::NAME, $last['name'])) : 'Most nem mondom előre. Nézd meg a képet: mi ez? Mondd ki hangosan!';
            $seen['name'] = true;
            $art = $this->art($text);
            $retry = ["Figyelj, ez $art $text.", ...(count($pieces) > 1 ? ['Lassan:', ...$pieces] : []), $spoken, 'Most te!'];
            $onCorrect = "$praise Ez $art $text: $text!";
        } else {
            $key = $phrase ? 'phrase' : 'word';
            $prompt = match (true) {
                $phrase && ! $seen['phrase'] => "Most egy mondatot mondok. Mondd utánam: $text",
                $phrase => "Mondd utánam: $text",
                ! $seen['word'] => "Nézd a képet! Mondd utánam: $text!",
                default => sprintf($last['repeat'] = $this->pickNot(self::REPEAT, $last['repeat']), $text),
            };
            $seen[$key] = true;
            $retry = [$this->pickNot(self::RETRY, null), ...(count($pieces) > 1 ? [$phrase ? 'Szavanként:' : 'Lassan:', ...$pieces, $phrase ? "Most egyben: $text" : $spoken] : [$spoken]), 'Most te!'];
            $onCorrect = $phrase ? "$praise Ez nehéz mondat volt!" : "$praise $spoken";
        }

        return $this->round('say', $prompt, [
            'mode' => $mode,
            'word' => $text,
            'emoji' => $p['emoji'],
            'sound' => config("beszed.games.hanggyakorlo.categories.{$p['sound']}.name", $p['sound']),
            'where' => match ($p['pos']) {
                'start' => 'a szó elején',
                'middle' => 'a szó közepén',
                'end' => 'a szó végén',
                default => 'mondatban',
            },
            // 🔊: the model again (naming: the question again, the word stays a secret)
            'model' => $mode === 'name' ? null : $spoken,
            'slow' => $slow,
            'retry' => $retry,
            'onCorrect' => $onCorrect,
            'onSkip' => 'Semmi baj, ezt majd legközelebb gyakoroljuk!',
            // "Tovább" shows after this many "Még gyakorlom"
            'skipAfter' => 3,
        ], $item->id);
    }
}
