<?php

namespace App\Beszed\Rounds;

use App\Beszed\Content\Hungarian;
use Illuminate\Support\Collection;

/**
 * Kapd el! (go/no-go): pictures float up in bubbles one at a time; the child catches the ones the rule asks for
 * and lets the others go. The server sends the whole stream (order, timing, lane, which are targets) and every
 * sentence Csillám may say; the catch engine only plays it and counts mistakes.
 *
 * Content rows are rules: tier 1 (levels 1-33) `visual` (one picture against one other, slowly), tier 2
 * (34-66) `category` (a whole group, faster), tier 3 (67-100) `sound` (each picture says its word as it
 * appears; catch the words with the sound in them). In tier 3 one round of the session is a category rule
 * that turns round mid-stream ("Most fordítva!"). Within every tier, pace (gap between bubbles, rise time,
 * how many targets/others stream by, lane count) creeps up continuously with $level, so a child isn't stuck
 * on one flat speed for 33 levels.
 */
class KapdelRounds extends RoundFactory
{
    /** Pace per kind, at the EASIEST level of its tier: targets to catch, targets and others in the stream, ms between bubbles, ms a bubble rises, lanes. */
    public const PACE = [
        'visual' => ['need' => 5, 'targets' => 8, 'others' => 4, 'gap' => 1800, 'rise' => 6500, 'lanes' => [0.2, 0.5, 0.8]],
        'category' => ['need' => 6, 'targets' => 9, 'others' => 6, 'gap' => 1300, 'rise' => 5200, 'lanes' => [0.14, 0.38, 0.62, 0.86]],
        // each word is said as its bubble appears: the gap leaves time to say it before the next one
        'sound' => ['need' => 5, 'targets' => 7, 'others' => 5, 'gap' => 2600, 'rise' => 6500, 'lanes' => [0.2, 0.5, 0.8]],
        // per half of the stream; the rule turns round in between
        'switch' => ['need' => 7, 'targets' => 5, 'others' => 3, 'gap' => 1400, 'rise' => 5500, 'lanes' => [0.14, 0.38, 0.62, 0.86]],
    ];

    /** Pace per kind, at the HARDEST level of its tier: more to catch, faster, tighter. */
    public const PACE_MAX = [
        'visual' => ['need' => 6, 'targets' => 10, 'others' => 6, 'gap' => 1300, 'rise' => 5000, 'lanes' => [0.14, 0.38, 0.62, 0.86]],
        'category' => ['need' => 8, 'targets' => 12, 'others' => 9, 'gap' => 950, 'rise' => 3900, 'lanes' => [0.1, 0.3, 0.5, 0.7, 0.9]],
        'sound' => ['need' => 7, 'targets' => 10, 'others' => 8, 'gap' => 2200, 'rise' => 5200, 'lanes' => [0.14, 0.38, 0.62, 0.86]],
        'switch' => ['need' => 9, 'targets' => 7, 'others' => 5, 'gap' => 1050, 'rise' => 4200, 'lanes' => [0.1, 0.3, 0.5, 0.7, 0.9]],
    ];

    /** Mistakes (wrong catches + targets let go) up to [0] → graded 1, up to [1] → 2, more → 3. */
    public const GRADE = [1, 3];

    /** Minimum gap the listening stream keeps, whatever the jitter: the time to say one word. */
    public const SAY_GAP_MS = 2400;

    private const KIND_OF_TIER = [1 => 'visual', 2 => 'category', 3 => 'sound'];

    /** Sounds that are easy to mix up by ear: a listening row with one of them has none of the others. */
    private const SIBILANTS = ['s', 'sz', 'z', 'zs', 'c', 'cs', 'dz', 'dzs'];

    private const PRAISE = ['Ügyes! Pont a jókat kaptad el!', 'Szuper figyelés!', 'Ez az! Ügyes kis fogó vagy!', 'Nagyszerű! Ez remek fogás volt!'];

    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        $kind = self::KIND_OF_TIER[$tier];
        $pool = $items->filter(fn ($i) => ($i->payload['kind'] ?? null) === $kind)->values();
        $pool = $pool->isNotEmpty() ? $pool : $items;
        $switchable = $items->filter(fn ($i) => ($i->payload['kind'] ?? null) === 'category' && ! empty($i->payload['reverse']))->values();

        $rows = $this->cycle($pool, $count)->values();
        $rounds = [];
        foreach ($rows as $r => $row) {
            // tier 3: the third round is a rule that turns round
            if ($tier === 3 && $r === 2 && $count >= 3 && $switchable->isNotEmpty()) {
                $rounds[] = $this->switchRound($this->weightedShuffle($switchable)->first(), $level);

                continue;
            }
            $rounds[] = $this->ruleRound($row, $r, $level);
        }

        return $rounds;
    }

    /**
     * Pace for $kind at $level: PACE at the easiest level of its tier, PACE_MAX at the
     * hardest, interpolated continuously within the tier so difficulty doesn't jump in
     * three flat steps.
     */
    private function pace(string $kind, int $level): array
    {
        $tiers = ['visual' => 1, 'category' => 2, 'sound' => 3, 'switch' => 3][$kind];
        $bandSize = 100 / 3;
        $within = max(0, min(100, $level - ($tiers - 1) * $bandSize));
        $from = self::PACE[$kind];
        $to = self::PACE_MAX[$kind];

        return [
            'need' => $this->scaleInt((int) round($within), $from['need'], $to['need'], (int) $bandSize),
            'targets' => $this->scaleInt((int) round($within), $from['targets'], $to['targets'], (int) $bandSize),
            'others' => $this->scaleInt((int) round($within), $from['others'], $to['others'], (int) $bandSize),
            'gap' => $this->scaleInt((int) round($within), $from['gap'], $to['gap'], (int) $bandSize),
            'rise' => $this->scaleInt((int) round($within), $from['rise'], $to['rise'], (int) $bandSize),
            'lanes' => $within >= $bandSize / 2 ? $to['lanes'] : $from['lanes'],
        ];
    }

    /** True when the word has the sound (digraph-aware: "szék" has sz, not s; "asszony" has a long sz). */
    public static function hasSound(string $word, string $sound): bool
    {
        foreach (Hungarian::letters($word) as $l) {
            if (self::base($l['letter']) === $sound) {
                return true;
            }
        }

        return false;
    }

    /** "ssz" → "sz", "ccs" → "cs", "ddzs" → "dzs": a long letter is the same sound. */
    private static function base(string $letter): string
    {
        $n = mb_strlen($letter);
        if ($n >= 3 && mb_substr($letter, 0, 1) === mb_substr($letter, 1, 1)) {
            return mb_substr($letter, 1);
        }

        return $letter;
    }

    /**
     * ContentRules: the rule row is playable and teaches nothing wrong.
     *
     * @return array<string, string>
     */
    public static function check(array $p): array
    {
        $lower = fn ($s) => mb_strtolower(trim($s));
        $targets = $p['targets'];
        $others = $p['others'];
        $emojis = [...array_column($targets, 0), ...array_column($others, 0)];
        $names = array_map($lower, [...array_column($targets, 1), ...array_column($others, 1)]);
        if (count(array_unique($emojis)) !== count($emojis) || count(array_unique($names)) !== count($names)) {
            return ['others' => 'Egy kép vagy szó csak egyszer szerepeljen (és ne legyen egyszerre elkapandó és nem).'];
        }

        return match ($p['kind']) {
            'visual' => count($targets) === 1 && count($others) === 1 ? []
                : ['targets' => 'Az első szinten egy fajta képet kell elkapni egy másik közül: egy kép ide, egy oda.'],
            'category' => match (true) {
                count($targets) < 5 => ['targets' => 'Legalább 5 kép kell a csoportból.'],
                count($others) < 4 => ['others' => 'Legalább 4 kép kell, ami nem a csoportba tartozik.'],
                default => [],
            },
            'sound' => self::checkSound($p),
            default => [],
        };
    }

    private static function checkSound(array $p): array
    {
        $sound = $p['sound'];
        $targets = array_column($p['targets'], 1);
        $others = array_column($p['others'], 1);
        if (count($targets) < self::PACE['sound']['targets'] || count($others) < self::PACE['sound']['others']) {
            return ['targets' => 'Legalább '.self::PACE['sound']['targets'].' szó kell a hanggal, és '.self::PACE['sound']['others'].' nélküle.'];
        }
        if (! str_contains($p['rule'], " $sound hang")) {
            return ['rule' => "A szabály nevezze meg a hangot a betűjével („amiben $sound hangot hallasz”)."];
        }
        foreach ($targets as $word) {
            if (! self::hasSound($word, $sound)) {
                return ['targets' => "A „{$word}” szóban nincs „{$sound}” hang."];
            }
        }
        foreach ($others as $word) {
            if (self::hasSound($word, $sound)) {
                return ['others' => "A „{$word}” szóban van „{$sound}” hang, azt el kellene kapni."];
            }
        }
        // s, sz, z… are hard to tell apart by ear in a quick game: none of the look-alikes anywhere in the row
        if (in_array($sound, self::SIBILANTS, true)) {
            foreach ([...$targets, ...$others] as $word) {
                foreach (array_diff(self::SIBILANTS, [$sound]) as $alike) {
                    if (self::hasSound($word, $alike)) {
                        return ['targets' => "A „{$word}” szóban „{$alike}” is van: ez könnyen összekeverhető a „{$sound}” hanggal."];
                    }
                }
            }
        }

        return [];
    }

    private function ruleRound(object $row, int $r, int $level): array
    {
        $p = $row->payload;
        $kind = $p['kind'];
        $pace = $this->pace($kind, $level);
        $hear = $kind === 'sound';
        // a listening row says each word as its bubble appears: never ask for more than the row actually has, or a word would be said twice
        if ($hear) {
            $pace['targets'] = min($pace['targets'], count($p['targets']));
            $pace['others'] = min($pace['others'], count($p['others']));
        }
        $stream = $this->stream($this->picks($p['targets'], $pace['targets'], $kind === 'visual'), $this->picks($p['others'], $pace['others'], $kind === 'visual'), $pace, $hear, 500);
        $stream = array_map(fn ($b) => $b + ['why' => $this->why($p, $b)], $stream);

        $goal = match ($kind) {
            'visual' => ['emojis' => [$p['targets'][0][0]], 'avoid' => [$p['others'][0][0]]],
            'category' => ['emojis' => collect($p['targets'])->shuffle()->take(3)->pluck(0)->values()->all(), 'avoid' => []],
            default => ['emojis' => ['👂'], 'text' => $p['sound'], 'avoid' => []],
        };

        $prompt = match (true) {
            $r === 0 && $hear => "Buborékok szállnak fel, és mindegyik megmondja a nevét. {$p['rule']}",
            $r === 0 => "Buborékok szállnak fel. {$p['rule']} Koppints rájuk, mielőtt elszállnak!",
            default => $this->pickNot(['Figyelj!', 'Jönnek a buborékok!', 'Most ez a szabály:'], null)." {$p['rule']}",
        };

        return $this->round('catch', $prompt, [
            'mode' => $hear ? 'hear' : 'see',
            'goal' => $goal,
            'need' => $pace['need'],
            'stream' => $stream,
            'grade' => self::GRADE,
            'again' => 'Még egyszer jönnek! Figyelj jól! '.$p['rule'],
            'onCorrect' => $this->pickNot(self::PRAISE, null),
            'onEnd' => 'Szép volt! Legközelebb még többet elkapsz!',
        ], $row->id);
    }

    /** A category rule that turns round halfway: first the group, then everything else. */
    private function switchRound(object $row, int $level): array
    {
        $p = $row->payload;
        $pace = $this->pace('switch', $level);
        $first = $this->stream($this->picks($p['targets'], $pace['targets'], false), $this->picks($p['others'], $pace['others'], false), $pace, false, 500);
        $switchAt = end($first)['at'] + (int) round($pace['rise'] * 0.6);
        // the second half: the others are the ones to catch now
        $second = $this->stream($this->picks($p['others'], $pace['targets'], false), $this->picks($p['targets'], $pace['others'], false), $pace, false, $switchAt + 700, end($first)['x']);
        $why = 'Hoppá! '.$p['reverse'];
        $stream = [
            ...array_map(fn ($b) => $b + ['why' => $this->why($p, $b)], $first),
            ...array_map(fn ($b, $i) => ['id' => 'c'.$i] + $b + ['why' => $why], $second, array_keys($second)),
        ];

        return $this->round('catch', "{$p['rule']} Figyelj, mert egyszer csak megfordul a szabály!", [
            'mode' => 'see',
            'goal' => ['emojis' => collect($p['targets'])->shuffle()->take(3)->pluck(0)->values()->all(), 'avoid' => []],
            'need' => $pace['need'],
            'stream' => $stream,
            'switch' => [
                'at' => $switchAt,
                'goal' => ['emojis' => collect($p['others'])->shuffle()->take(3)->pluck(0)->values()->all(), 'avoid' => []],
                'say' => $p['reverse'],
            ],
            'grade' => self::GRADE,
            'again' => 'Még egyszer jönnek! Figyelj jól! '.$p['rule'],
            'onCorrect' => 'Ügyes! Még a fordított szabályt is észrevetted!',
            'onEnd' => 'Szép volt! Legközelebb még többet elkapsz!',
        ], $row->id);
    }

    /** $n pictures from the list: a visual row repeats its one picture; otherwise distinct ones first, never the same twice in a row. */
    private function picks(array $list, int $n, bool $repeat): array
    {
        if ($repeat) {
            return array_fill(0, $n, $list[0]);
        }
        $out = [];
        while (count($out) < $n) {
            $batch = collect($list)->shuffle()->all();
            if ($out && count($batch) > 1 && end($out)[0] === $batch[0][0]) {
                $batch = [...array_slice($batch, 1), $batch[0]];
            }
            array_push($out, ...$batch);
        }

        return array_slice($out, 0, $n);
    }

    /**
     * The bubbles in order: the first is a target, at most three targets and two others in a row.
     *
     * @return list<array{id: string, emoji: string, name: string, target: bool, at: int, rise: int, x: float}>
     */
    private function stream(array $targets, array $others, array $pace, bool $hear, int $start, ?float $lane = null): array
    {
        $flags = array_merge(array_fill(0, count($targets), true), array_fill(0, count($others), false));
        for ($try = 0; $try < 200; $try++) {
            shuffle($flags);
            if ($flags[0] && ! preg_match('/TTTT|OOO/', implode('', array_map(fn ($f) => $f ? 'T' : 'O', $flags)))) {
                break;
            }
        }
        if (! $flags[0]) {
            $first = array_search(true, $flags, true);
            [$flags[0], $flags[$first]] = [true, false];
        }

        $out = [];
        $at = $start;
        foreach ($flags as $i => $isTarget) {
            [$emoji, $name] = $isTarget ? array_shift($targets) : array_shift($others);
            if ($i > 0) {
                $at += $hear ? max(self::SAY_GAP_MS, $pace['gap'] + mt_rand(0, 250)) : $pace['gap'] + mt_rand(-150, 150);
            }
            $lane = $this->pickNot($pace['lanes'], $lane);
            $out[] = [
                'id' => 'b'.$i,
                'emoji' => $emoji,
                'name' => $name,
                'target' => $isTarget,
                'at' => $at,
                'rise' => (int) round($pace['rise'] * mt_rand(92, 108) / 100),
                'x' => $lane,
            ];
        }

        return $out;
    }

    /** What Csillám says the first time this bubble is a mistake: a wrong one caught, or a target let go. */
    private function why(array $p, array $b): string
    {
        $name = $b['name'];
        $the = $this->art($name).' '.$name;

        return match ([$p['kind'], $b['target']]) {
            ['visual', false] => "Ez {$name}, nem {$p['targets'][0][1]}. Azt hagyd elszállni!",
            ['visual', true] => "Hoppá, elszállt egy {$name}! {$p['rule']}",
            ['category', false] => $this->ucfirst($the)." nem {$p['singular']}. Azt hagyd elszállni!",
            ['category', true] => "Hoppá, elszállt {$the}! Pedig {$the} is {$p['singular']}.",
            ['sound', false] => "Figyeld: {$name}. Ebben nincs {$p['sound']} hang!",
            default => "Hoppá, elszállt! Figyeld: {$name}. Ebben van {$p['sound']} hang!",
        };
    }
}
