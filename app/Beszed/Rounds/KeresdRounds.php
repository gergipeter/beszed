<?php

namespace App\Beszed\Rounds;

use App\Beszed\Content\Hungarian;
use Illuminate\Support\Collection;

/**
 * Keresd meg! (hidden pictures / I spy): a busy picture over a themed
 * backdrop; the child finds every target and taps it. The scene is laid out
 * here — positions and sizes in % of a square board, rotations in degrees —
 * from a seed, so the same round always looks the same.
 *
 * Level 1: 3 big targets among about 12 pictures, nothing turned or covered.
 * Level 2: 5 targets among about 25, turned, some of them partly covered.
 * Level 3: a spoken clue instead of a picture: a colour ("négy piros dolgot")
 * or a first sound ("aminek a neve sz hanggal kezdődik").
 *
 * Targets are never more than COVER_MAX covered, and every picture is at
 * least MIN_SIZE wide: on a phone (a board of about 340 px) that is a tap area
 * of 44 px or more (the engine also widens small ones to a 48 px circle).
 */
class KeresdRounds extends RoundFactory
{
    /** level → targets, pictures in all, size range (% of the board), turn (±deg), targets partly covered */
    public const LEVELS = [
        1 => ['targets' => 3, 'total' => 12, 'size' => [19, 22], 'turn' => 0, 'cover' => 0],
        2 => ['targets' => 5, 'total' => 25, 'size' => [13, 16], 'turn' => 35, 'cover' => 2],
        3 => ['targets' => 4, 'total' => 20, 'size' => [14, 17], 'turn' => 15, 'cover' => 1],
    ];

    public const MIN_SIZE = 13;

    /** Share of a target's area that pictures above it may hide. */
    public const COVER_MAX = 0.35;

    /** How far a deliberately covering picture sits from the target's centre (share of the two radii). */
    private const COVER_DEPTH = 0.7;

    /** Colour clues: a swatch in the tray (display only). */
    private const SWATCH = [
        'piros' => '#e53935', 'sárga' => '#fdd835', 'zöld' => '#43a047', 'kék' => '#1e88e5', 'narancssárga' => '#fb8c00',
        'fehér' => '#ffffff', 'lila' => '#8e24aa', 'barna' => '#8d6e63', 'fekete' => '#212121', 'rózsaszín' => '#f48fb1',
    ];

    /** Counting out loud as the targets are found. */
    private const COUNT = [1 => 'Egy!', 'Kettő!', 'Három!', 'Négy!', 'Öt!', 'Hat!'];

    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(3, $level));
        // a clue needs reading the picture by meaning: only the top level; below it, one kind of picture
        $kind = $level >= 3 ? 'clue' : 'find';
        $pool = $items->filter(fn ($i) => ($i->payload['kind'] ?? 'find') === $kind)->whenEmpty(fn () => $items);
        $this->favorLevel($pool, $level);

        $rounds = [];
        $lastTarget = null;
        foreach ($this->cycle($pool, $count)->values() as $r => $item) {
            $seed = random_int(1, 2_000_000_000);
            $round = ($item->payload['kind'] ?? 'find') === 'clue'
                ? $this->clueRound($item, $level, $r, $seed)
                : $this->findRound($item, $level, $r, $seed, $lastTarget);
            $lastTarget = $round['data']['tray']['emoji'] ?? null;
            $rounds[] = $round;
        }

        return $rounds;
    }

    /** "Keress meg három katicát!": one kind of picture, the theme's other kinds as decoys. */
    private function findRound(object $item, int $level, int $r, int $seed, ?string $last): array
    {
        $p = $item->payload;
        $spec = self::LEVELS[$level];
        $n = $spec['targets'];
        $groups = $p['targets'];
        $choices = array_values(array_filter($groups, fn ($g) => $g[0] !== $last)) ?: $groups;
        [$emoji, $acc] = $choices[array_rand($choices)];

        // decoys: the theme's other kinds (once each at level 1, twice at 2), then the scenery
        $decoys = [];
        foreach ($groups as $g) {
            if ($g[0] !== $emoji) {
                array_push($decoys, ...array_fill(0, $level >= 2 ? 2 : 1, $g[0]));
            }
        }
        shuffle($decoys);
        $others = array_slice($decoys, 0, intdiv($spec['total'] - $n, 2));
        $fillers = $p['fillers'];
        shuffle($fillers);
        for ($i = 0; count($others) < $spec['total'] - $n; $i++) {
            $others[] = $fillers[$i % count($fillers)];
        }

        $num = self::NUM[$n];
        $pieces = array_merge(
            array_fill(0, $n, ['emoji' => $emoji, 'target' => true]),
            array_map(fn ($e) => ['emoji' => $e, 'target' => false], $others),
        );

        $prompt = $r === 0
            ? "Nézd, milyen sok minden van a képen! Keress meg $num $acc, és koppints rájuk!"
            : $this->pickNot(["Keress meg $num $acc!", "Most keress meg $num $acc a képen!", "Hol vannak? Keress meg $num $acc!"], null);

        return $this->round('hidden', $prompt, [
            'backdrop' => $p['backdrop'],
            'seed' => $seed,
            'items' => self::layout($pieces, $level, $seed),
            'count' => $n,
            'tray' => ['emoji' => $emoji],
            'counts' => array_slice(array_values(self::COUNT), 0, $n),
            'wrong' => $this->pickNot(["Ez nem az. Keress $acc!", "Hoppá! Mi most $acc keresünk.", "Ez nem az. Nézd meg jól, hol van még?"], null),
            'onCorrect' => $this->pickNot(["Megtaláltad mind a $num $acc!", "Ügyes vagy! Mind a $num $acc megtaláltad!", "Szuper! Megvan mind a $num."], null),
        ], $item->id);
    }

    /** "Keress meg négy piros dolgot!": things that fit the clue, and things that don't. */
    private function clueRound(object $item, int $level, int $r, int $seed): array
    {
        $p = $item->payload;
        $spec = self::LEVELS[3];
        $targets = $p['targets'];
        shuffle($targets);
        $targets = array_slice($targets, 0, min($spec['targets'], count($targets)));
        $n = count($targets);
        $others = $p['others'];
        shuffle($others);

        // A colour clue was written for the emoji's own colours: the session must not swap in a pictogram
        // drawn in another colour (green grapes!). The emoji-presentation selector keeps the string from
        // matching the pictogram map and draws the same Twemoji image.
        $keep = isset($p['sound']) ? fn ($e) => $e : fn ($e) => self::keepEmoji($e);
        $pieces = array_map(fn ($t) => ['emoji' => $keep($t[0]), 'target' => true, 'say' => $this->ucfirst($t[1]).'!'], $targets);
        for ($i = 0; count($pieces) < $spec['total']; $i++) {
            [$emoji, $name] = $others[$i % count($others)];
            $pieces[] = ['emoji' => $keep($emoji), 'target' => false, 'say' => $this->ucfirst($this->art($name))." $name {$p['nope']}."];
        }

        $num = self::NUM[$n];
        $prompt = $r === 0
            ? "Most figyelj jól! Keress meg $num {$p['clue']}, és koppints rájuk!"
            : "Keress meg $num {$p['clue']}!";
        $names = array_map(fn ($t) => $t[1], $targets);
        $list = implode(', ', array_slice($names, 0, -1)).' és '.end($names);
        $colour = self::SWATCH[strtok($p['clue'], ' ')] ?? null;

        return $this->round('hidden', $prompt, [
            'backdrop' => $p['backdrop'],
            'seed' => $seed,
            'items' => self::layout($pieces, $level, $seed),
            'count' => $n,
            'tray' => array_filter(['swatch' => $colour, 'sound' => $p['sound'] ?? null]),
            'onCorrect' => "Ügyes! Mind megvan: $list.",
        ], $item->id);
    }

    /**
     * Places the pictures on a square board (deterministic for a seed):
     * targets first, each where it is farthest from the others (best of many
     * tries), then the rest. At level 2 a few pictures are then laid partly
     * over targets. Returned in drawing order, with x/y = centre, size = width
     * (all in % of the board), rotate in degrees.
     *
     * @param  list<array{emoji: string, target: bool, say?: string}>  $pieces
     * @return list<array{id: string, emoji: string, target: bool, x: float, y: float, size: float, rotate: int, say?: string}>
     */
    public static function layout(array $pieces, int $level, int $seed): array
    {
        $spec = self::LEVELS[$level] ?? self::LEVELS[1];
        $state = $seed & 0x7fffffff;
        $rand = function () use (&$state): float {
            $state = ($state * 1103515245 + 12345) & 0x7fffffff;

            return $state / 0x7fffffff;
        };
        $between = fn (float $a, float $b) => $a + ($b - $a) * $rand();

        // targets first, so they are spread across the whole picture
        usort($pieces, fn ($a, $b) => $b['target'] <=> $a['target']);
        $placed = [];
        foreach ($pieces as $k => $piece) {
            $size = round($between(...$spec['size']), 1);
            $r = $size / 2;
            $best = null;
            $bestScore = -INF;
            for ($try = 0; $try < 60; $try++) {
                $x = $between($r, 100 - $r);
                $y = $between($r, 100 - $r);
                $score = INF;
                foreach ($placed as $q) {
                    $score = min($score, hypot($x - $q['x'], $y - $q['y']) - $r - $q['size'] / 2);
                }
                if ($score > $bestScore) {
                    [$best, $bestScore] = [[$x, $y], $score];
                }
            }
            $placed[] = $piece + [
                'id' => "p$k",
                'x' => round($best[0], 1),
                'y' => round($best[1], 1),
                'size' => $size,
                'rotate' => $spec['turn'] ? (int) round($between(-$spec['turn'], $spec['turn'])) : 0,
            ];
        }

        $targets = array_keys(array_filter($placed, fn ($q) => $q['target']));
        $rest = array_keys(array_filter($placed, fn ($q) => ! $q['target']));

        // a few pictures laid partly over targets (peeking out from behind)
        $covers = [];
        foreach (array_slice($targets, 0, $spec['cover']) as $j => $t) {
            $c = $rest[count($rest) - 1 - $j] ?? null;
            if ($c === null) {
                break;
            }
            $angle = $rand() * 2 * M_PI;
            $reach = ($placed[$t]['size'] + $placed[$c]['size']) / 2 * self::COVER_DEPTH;
            $rc = $placed[$c]['size'] / 2;
            $placed[$c]['x'] = round(min(100 - $rc, max($rc, $placed[$t]['x'] + cos($angle) * $reach)), 1);
            $placed[$c]['y'] = round(min(100 - $rc, max($rc, $placed[$t]['y'] + sin($angle) * $reach)), 1);
            $covers[] = $c;
        }

        // drawing order: the scenery, then the targets, then what covers them
        $plain = array_values(array_diff($rest, $covers));
        $orderSeed = $rand();
        usort($plain, fn ($a, $b) => crc32("$orderSeed-$a") <=> crc32("$orderSeed-$b"));
        $order = [...$plain, ...$targets, ...$covers];

        $out = array_map(fn ($i) => $placed[$i], $order);
        // a target hidden too much (a clamp at the edge can push a cover deeper) goes on top after all
        $hidden = array_keys(array_filter($out, fn ($q, $i) => $q['target'] && self::covered($out, $i) > self::COVER_MAX, ARRAY_FILTER_USE_BOTH));

        return [...array_values(array_diff_key($out, array_flip($hidden))), ...array_map(fn ($i) => $out[$i], $hidden)];
    }

    /** Share of picture $i's area hidden by the pictures drawn after it (circles; overlaps add up, so it's an upper bound). */
    public static function covered(array $items, int $i): float
    {
        $a = $items[$i];
        $r = $a['size'] / 2;
        $hidden = 0.0;
        foreach (array_slice($items, $i + 1) as $b) {
            $hidden += self::lens($r, $b['size'] / 2, hypot($a['x'] - $b['x'], $a['y'] - $b['y']));
        }

        return min(1.0, $hidden / (M_PI * $r * $r));
    }

    /** Area where two circles overlap. */
    private static function lens(float $r1, float $r2, float $d): float
    {
        if ($d >= $r1 + $r2) {
            return 0.0;
        }
        if ($d <= abs($r1 - $r2)) {
            return M_PI * min($r1, $r2) ** 2;
        }
        $a = $r1 * $r1 * acos(($d * $d + $r1 * $r1 - $r2 * $r2) / (2 * $d * $r1));
        $b = $r2 * $r2 * acos(($d * $d + $r2 * $r2 - $r1 * $r1) / (2 * $d * $r2));
        $c = 0.5 * sqrt((-$d + $r1 + $r2) * ($d + $r1 - $r2) * ($d - $r1 + $r2) * ($d + $r1 + $r2));

        return $a + $b - $c;
    }

    /** First sounds a child easily mixes up with each one: none of those among the pictures that don't fit. */
    private const LOOKALIKE = [
        's' => ['sz', 'zs', 'cs'], 'sz' => ['s', 'z', 'c'], 'z' => ['zs', 'sz'], 'zs' => ['z', 's'], 'c' => ['cs', 'sz'], 'cs' => ['c', 's'],
        'n' => ['ny'], 'ny' => ['n'], 'l' => ['ly'], 't' => ['ty'], 'ty' => ['t'], 'g' => ['gy'], 'gy' => ['g', 'd'], 'd' => ['gy', 'dz', 'dzs'],
        'b' => ['p'], 'p' => ['b'], 'f' => ['v'], 'v' => ['f'], 'k' => ['g'],
    ];

    /**
     * Content rules (ContentRules / the editor).
     *
     * @return array<string, string>
     */
    public static function check(array $p): array
    {
        $targets = $p['targets'] ?? [];
        $emojis = array_column($targets, 0);
        if (count(array_unique($emojis)) !== count($emojis)) {
            return ['targets' => 'Egy képet csak egyszer adj meg.'];
        }
        if (($p['kind'] ?? 'find') === 'find') {
            foreach ($targets as [, $acc]) {
                if (! str_ends_with(mb_strtolower(trim($acc)), 't')) {
                    return ['targets' => "„{$acc}”: tárgyesetben kell (Keress meg három katicát!)."];
                }
            }
            if (array_intersect($p['fillers'] ?? [], $emojis)) {
                return ['fillers' => 'Ami keresni való, az ne legyen a díszletek között is.'];
            }

            return [];
        }

        $others = $p['others'] ?? [];
        if (count($targets) < self::LEVELS[3]['targets']) {
            return ['targets' => 'Legalább '.self::LEVELS[3]['targets'].' kép kell, ami illik a szabályra.'];
        }
        if (array_intersect(array_column($others, 0), $emojis)) {
            return ['others' => 'Egy kép nem lehet egyszerre jó is és rossz is.'];
        }
        if (! str_starts_with(trim($p['nope'] ?? ''), 'nem ') && ! str_contains($p['nope'] ?? '', ' nem ')) {
            return ['nope' => 'Tagadás kell: „nem piros”, „neve nem sz hanggal kezdődik”.'];
        }
        if (str_contains(($p['clue'] ?? '').($p['nope'] ?? ''), '…')) {
            return ['clue' => 'Ne legyen benne „…” (a gépi hang kiolvasná).'];
        }
        $sound = mb_strtolower(trim($p['sound'] ?? ''));
        if ($sound === '') {
            return [];
        }
        if (! str_contains($p['clue'], " $sound hang") || ! str_contains($p['nope'], " $sound hang")) {
            return ['clue' => "A szabályban szerepeljen a hang: „… $sound hanggal kezdődik”."];
        }
        foreach ($targets as [, $name]) {
            if (self::firstSound($name) !== $sound) {
                return ['targets' => "„{$name}” nem „{$sound}” hanggal kezdődik (hanem „".self::firstSound($name).'”).'];
            }
        }
        foreach ($others as [, $name]) {
            $first = self::firstSound($name);
            if ($first === $sound || in_array($first, self::LOOKALIKE[$sound] ?? [], true)) {
                return ['others' => "„{$name}” kezdőhangja („{$first}”) túl közel van a keresetthez."];
            }
        }

        return [];
    }

    /**
     * The emoji, kept as an emoji (never swapped for a pictogram): two emoji-presentation selectors, so it
     * can't equal a mapped key (one alone could: "☁️"). The app's image names drop the selectors.
     */
    public static function keepEmoji(string $emoji): string
    {
        return str_contains($emoji, "\u{200D}") ? $emoji : str_replace("\u{FE0F}", '', $emoji)."\u{FE0F}\u{FE0F}";
    }

    /** The first sound of a word, digraph-aware ("szék" → "sz", "nyúl" → "ny"). */
    public static function firstSound(string $word): string
    {
        return Hungarian::letters($word)[0]['letter'] ?? '';
    }
}
