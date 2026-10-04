<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Pontról pontra (dot to dot): a shape's outline becomes numbered dots; the
 * child joins them in order and the picture pops out at the end. The level is
 * the number of dots (6, 10, 15); at the top level every other round is the
 * Hungarian ABC instead of numbers (a two-letter letter such as "cs" is one dot).
 *
 * The board is a square, coordinates 0–1. Dots are kept at least MIN_GAP apart,
 * so on a phone (a board of about 350 px) every dot owns a tap area of 44 px
 * and the labels inside the dots never touch.
 */
class PontozoRounds extends RoundFactory
{
    /** level → number of dots */
    public const DOTS = [1 => 6, 2 => 10, 3 => 15];

    /** Smallest distance between two dots, as a share of the board. */
    public const MIN_GAP = 0.125;

    /** The shape fills the board within this margin. */
    public const MARGIN = 0.07;

    /** The Hungarian alphabet, in order (digraphs and the trigraph are single letters). */
    public const ABC = ['a', 'á', 'b', 'c', 'cs', 'd', 'dz', 'dzs', 'e', 'é', 'f', 'g', 'gy', 'h', 'i', 'í', 'j', 'k', 'l', 'ly', 'm', 'n', 'ny', 'o', 'ó', 'ö', 'ő', 'p', 'r', 's', 'sz', 't', 'ty', 'u', 'ú', 'ü', 'ű', 'v', 'z', 'zs'];

    /** How the letters are said (letter names, written as they sound, so TTS reads them right). */
    private const LETTER_NAMES = [
        'a' => 'a', 'á' => 'á', 'b' => 'bé', 'c' => 'cé', 'cs' => 'csé', 'd' => 'dé', 'dz' => 'dzé', 'dzs' => 'dzsé',
        'e' => 'e', 'é' => 'é', 'f' => 'ef', 'g' => 'gé', 'gy' => 'gyé', 'h' => 'há', 'i' => 'i', 'í' => 'í',
        'j' => 'jé', 'k' => 'ká', 'l' => 'el', 'ly' => 'ellipszilon', 'm' => 'em', 'n' => 'en', 'ny' => 'eny',
        'o' => 'o', 'ó' => 'ó', 'ö' => 'ö', 'ő' => 'ő', 'p' => 'pé', 'r' => 'er', 's' => 'es', 'sz' => 'esz',
        't' => 'té', 'ty' => 'tyé', 'u' => 'u', 'ú' => 'ú', 'ü' => 'ü', 'ű' => 'ű', 'v' => 'vé', 'z' => 'zé', 'zs' => 'zsé',
    ];

    /** Counting words, said as each dot is joined ("kettő", not "két"). */
    private const COUNT = [1 => 'egy', 'kettő', 'három', 'négy', 'öt', 'hat', 'hét', 'nyolc', 'kilenc', 'tíz', 'tizenegy', 'tizenkettő', 'tizenhárom', 'tizennégy', 'tizenöt', 'tizenhat', 'tizenhét', 'tizennyolc', 'tizenkilenc', 'húsz'];

    /** The first $n letters of the ABC. */
    public static function abc(int $n): array
    {
        return array_slice(self::ABC, 0, $n);
    }

    public function build(Collection $items, int $level, int $count): array
    {
        $level = max(1, min(3, $level));
        $n = self::DOTS[$level];
        // a shape made for more dots than the level gives would lose its look
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $level)->whenEmpty(fn () => $items);
        $this->favorLevel($pool, $level);

        $rounds = [];
        foreach ($this->cycle($pool, $count)->values() as $r => $item) {
            $letters = $level >= 3 && $r % 2 === 1;
            $rounds[] = $this->shapeRound($item, $n, $r, $letters);
        }

        return $rounds;
    }

    private function shapeRound(object $item, int $n, int $r, bool $letters): array
    {
        $p = $item->payload;
        $points = self::dots(self::parse($p['points']), $n);
        $labels = $letters ? self::abc(count($points)) : array_map('strval', range(1, count($points)));

        $dots = [];
        foreach ($points as $i => [$x, $y]) {
            $label = $labels[$i];
            $said = $letters ? self::LETTER_NAMES[$label] : self::COUNT[$i + 1];
            if ($i === 0) {
                $hint = $letters ? 'Az a betűnél kezdd! Keresd meg az a betűt!' : 'Az egyesnél kezdd! Keresd meg az egyest!';
            } else {
                $prev = $letters ? self::LETTER_NAMES[$labels[$i - 1]] : self::COUNT[$i];
                $hint = $letters
                    ? $this->ucfirst($this->art($prev))." $prev után melyik betű jön?"
                    : $this->ucfirst($this->art($prev))." $prev után melyik jön?";
            }
            $dots[] = ['x' => round($x, 4), 'y' => round($y, 4), 'label' => $label, 'say' => $this->ucfirst($said).'!', 'hint' => $hint];
        }

        $prompt = match (true) {
            $letters => 'Most betűk vannak a pöttyökön! Kösd össze őket ábécérendben: a, á, bé, cé, és így tovább!',
            $r === 0 => 'Kösd össze a pöttyöket sorban, az egyestől kezdve! Koppints rájuk, vagy húzd végig az ujjad! Mi bújik elő?',
            default => $this->pickNot(['Kösd össze a pöttyöket! Vajon mi lesz belőle?', 'Egy, kettő, három! Kösd össze a pöttyöket sorban!', 'Mi rejtőzik a pöttyök között? Kösd össze őket!'], null),
        };

        return $this->round('dots', $prompt, [
            'dots' => $dots,
            // the outline was drawn after this emoji (🌙 is a crescent): never swapped for a pictogram
            'emoji' => KeresdRounds::keepEmoji($p['emoji']),
            'name' => $p['name'],
            // the youngest get the next dot shown with a gentle pulse
            'pulse' => $n <= 6,
            'onCorrect' => $this->pickNot(["Nézd, egy {$p['name']} lett belőle!", "Hurrá, előbújt egy {$p['name']}!", "Ügyes! Egy {$p['name']} rejtőzött a pöttyökben!"], null),
        ], $item->id);
    }

    /**
     * Content rules (ContentRules / the editor): every point is "x,y" with
     * both between 0 and 1, and the outline isn't a line or a dot.
     *
     * @return array<string, string>
     */
    public static function check(array $p): array
    {
        $raw = is_array($p['points'] ?? null) ? $p['points'] : explode(';', (string) ($p['points'] ?? ''));
        foreach ($raw as $pair) {
            if (! preg_match('/^\s*(0(\.\d+)?|1(\.0+)?)\s*,\s*(0(\.\d+)?|1(\.0+)?)\s*$/', $pair)) {
                return ['points' => "„{$pair}”: x,y alakban kell, mindkettő 0 és 1 között (pl. 0.5,0.1)."];
            }
        }
        $pts = self::parse($raw);
        $xs = array_column($pts, 0);
        $ys = array_column($pts, 1);
        if (count(array_unique(array_map(fn ($q) => "$q[0],$q[1]", $pts))) !== count($pts)) {
            return ['points' => 'Egy pont kétszer szerepel.'];
        }
        if (max($xs) - min($xs) < 0.3 || max($ys) - min($ys) < 0.3) {
            return ['points' => 'A kép túl lapos vagy túl kicsi: töltse ki a négyzet nagyobb részét.'];
        }

        return [];
    }

    /** "0.5,0.1;0.6,0.4" or a list of "x,y" → list of [x, y]. */
    public static function parse(array|string $points): array
    {
        $list = is_array($points) ? $points : explode(';', $points);

        return array_values(array_map(function ($pair) {
            [$x, $y] = array_map('floatval', explode(',', trim($pair)));

            return [$x, $y];
        }, array_filter($list, fn ($s) => trim($s) !== '')));
    }

    /**
     * The outline (closed, in drawing order) as $n dots, scaled to fill the
     * board: the sharpest corners are kept (a star keeps its tips), the rest of
     * the dots are spread evenly along the outline, and no two dots come closer
     * than MIN_GAP — a corner that would crowd another is given up instead.
     *
     * @param  list<array{0: float, 1: float}>  $outline
     * @return list<array{0: float, 1: float}>
     */
    public static function dots(array $outline, int $n): array
    {
        $pts = self::fit($outline);
        $m = count($pts);
        $arc = [0.0];
        for ($i = 1; $i <= $m; $i++) {
            $arc[$i] = $arc[$i - 1] + self::dist($pts[$i - 1], $pts[$i % $m]);
        }
        $perimeter = $arc[$m];

        $order = self::importance($pts, $arc, $perimeter);

        // fewer and fewer corners until every dot has room
        $best = null;
        $bestGap = -1;
        for ($keep = min(count($order), $n - 1); $keep >= 0; $keep--) {
            $corners = [0];
            foreach ($order as $i) {
                if (count($corners) > $keep) {
                    break;
                }
                if (collect($corners)->every(fn ($c) => self::dist($pts[$c], $pts[$i]) >= self::MIN_GAP)) {
                    $corners[] = $i;
                }
            }
            sort($corners);
            $dots = self::spread($pts, $arc, $perimeter, $corners, $n);
            $gap = self::minGap($dots);
            if ($gap >= self::MIN_GAP) {
                return $dots;
            }
            if ($gap > $bestGap) {
                [$best, $bestGap] = [$dots, $gap];
            }
        }

        return $best;
    }

    /**
     * Corners from the most to the least telling. A corner's sharpness is the
     * turn of the outline measured a little way (SCALE) before and after it, so
     * a pointed tip drawn with many close points still counts as one sharp
     * corner, and a gentle curve does not. Only the sharpest point of each
     * stretch counts; corner 0, where dot 1 sits, is never on the list.
     *
     * @return list<int>
     */
    private static function importance(array $pts, array $arc, float $perimeter): array
    {
        $m = count($pts);
        $scale = min(0.09, $perimeter / 12);
        $turn = [];
        for ($i = 0; $i < $m; $i++) {
            $a = self::at($pts, $arc, fmod($arc[$i] - $scale + $perimeter, $perimeter));
            $c = self::at($pts, $arc, fmod($arc[$i] + $scale, $perimeter));
            $b = $pts[$i];
            $d1 = atan2($b[1] - $a[1], $b[0] - $a[0]);
            $d2 = atan2($c[1] - $b[1], $c[0] - $b[0]);
            $turn[$i] = abs(atan2(sin($d2 - $d1), cos($d2 - $d1)));
        }
        $near = fn ($i, $j) => min(abs($arc[$i] - $arc[$j]), $perimeter - abs($arc[$i] - $arc[$j])) < $scale;
        $order = array_values(array_filter(array_keys($pts), fn ($i) => $i !== 0 && $turn[$i] > deg2rad(25)
            && collect(array_keys($pts))->every(fn ($j) => $j === $i || ! $near($i, $j) || $turn[$j] < $turn[$i] || ($turn[$j] == $turn[$i] && $j > $i))));
        usort($order, fn ($a, $b) => $turn[$b] <=> $turn[$a]);

        return $order;
    }

    /** The smallest distance between any two dots. */
    public static function minGap(array $dots): float
    {
        $min = INF;
        foreach ($dots as $i => $a) {
            foreach (array_slice($dots, $i + 1) as $b) {
                $min = min($min, self::dist($a, $b));
            }
        }

        return $min;
    }

    /** Corner dots, plus the remaining dots shared out to the longest stretches between them. */
    private static function spread(array $pts, array $arc, float $perimeter, array $corners, int $n): array
    {
        $k = count($corners);
        $gaps = [];
        foreach ($corners as $j => $c) {
            $from = $arc[$c];
            $to = $j + 1 < $k ? $arc[$corners[$j + 1]] : $perimeter;
            $gaps[$j] = ['from' => $from, 'len' => $to - $from, 'extra' => 0];
        }
        for ($left = $n - $k; $left > 0; $left--) {
            $j = collect($gaps)->sortByDesc(fn ($g) => $g['len'] / ($g['extra'] + 1))->keys()->first();
            $gaps[$j]['extra']++;
        }

        $out = [];
        foreach ($gaps as $j => $g) {
            $out[] = $pts[$corners[$j]];
            for ($e = 1; $e <= $g['extra']; $e++) {
                $out[] = self::at($pts, $arc, $g['from'] + $g['len'] * $e / ($g['extra'] + 1));
            }
        }

        return $out;
    }

    /** The point at arc length $s along the closed outline. */
    private static function at(array $pts, array $arc, float $s): array
    {
        $m = count($pts);
        for ($i = 0; $i < $m; $i++) {
            if ($s <= $arc[$i + 1] || $i === $m - 1) {
                $len = $arc[$i + 1] - $arc[$i];
                $t = $len > 0 ? ($s - $arc[$i]) / $len : 0;
                [$a, $b] = [$pts[$i], $pts[($i + 1) % $m]];

                return [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t];
            }
        }

        return $pts[0];
    }

    /** Scales and centres the outline into the board, keeping its proportions. */
    private static function fit(array $pts): array
    {
        $xs = array_column($pts, 0);
        $ys = array_column($pts, 1);
        $w = max($xs) - min($xs);
        $h = max($ys) - min($ys);
        $scale = (1 - 2 * self::MARGIN) / max($w, $h, 1e-6);
        $dx = (1 - $w * $scale) / 2 - min($xs) * $scale;
        $dy = (1 - $h * $scale) / 2 - min($ys) * $scale;

        return array_map(fn ($p) => [$p[0] * $scale + $dx, $p[1] * $scale + $dy], $pts);
    }

    private static function dist(array $a, array $b): float
    {
        return hypot($a[0] - $b[0], $a[1] - $b[1]);
    }
}
