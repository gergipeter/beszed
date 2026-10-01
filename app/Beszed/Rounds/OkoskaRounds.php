<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** Pattern continuation + odd one out. */
class OkoskaRounds extends RoundFactory
{
    /** Pattern kinds by how hard they are to continue: two alternating, a doubled beat, then three-part sequences. */
    private const PATTERNS_BY_LEVEL = [1 => ['AB'], 2 => ['AB', 'AAB', 'ABB'], 3 => ['AB', 'AAB', 'ABB', 'ABC']];

    public function build(Collection $items, int $level, int $count): array
    {
        $symbols = $items->first(fn ($i) => $i->payload['kind'] === 'symbols')?->payload['emojis'] ?? [];
        $cats = $items->filter(fn ($i) => $i->payload['kind'] === 'category')->values();
        $this->favorLevel($cats, $level);
        $types = self::PATTERNS_BY_LEVEL[$level] ?? self::PATTERNS_BY_LEVEL[3];
        $rounds = [];

        for ($r = 0; $r < $count; $r++) {
            $pattern = count($symbols) >= 3 && ($cats->count() < 2 || $r % 2 === 0);
            $rounds[] = $pattern ? $this->pattern($symbols, $types) : $this->oddOneOut($cats);
        }

        return $rounds;
    }

    private function pattern(array $symbols, array $types): array
    {
        $type = $types[array_rand($types)];
        $syms = collect($symbols)->shuffle()->take(3)->values()->all();
        $unit = array_map(fn ($c) => $syms[ord($c) - 65], str_split($type));
        $len = count($unit);
        $shown = $len * 2 + random_int(0, $len - 1);
        $answer = $unit[$shown % $len];
        $sequence = array_map(fn ($i) => $unit[$i % $len], range(0, $shown - 1));

        $pool = collect($unit)->reject(fn ($s) => $s === $answer)->unique()
            ->merge(collect($symbols)->shuffle()->reject(fn ($s) => $s === $answer))
            ->unique()->values();
        $opts = collect([$answer, $pool[0], $pool[1]])->shuffle()->values();

        return $this->round('choice', 'Mi jön ezután? Nézd meg a sort, és válaszd ki!', [
            'sequence' => $sequence,
            'layout' => 'three',
            'options' => $opts->map(fn ($e, $i) => ['id' => (string) $i, 'emoji' => $e])->all(),
            'answer' => (string) $opts->search($answer),
            'onWrong' => 'Nézd meg újra a sort az elejétől!',
        ]);
    }

    private function oddOneOut(Collection $cats): array
    {
        $c = $this->weightedShuffle($cats)->first();
        $o = $cats->reject(fn ($x) => $x->id === $c->id)->random();
        $odd = collect($o->payload['emojis'])->random();
        $opts = collect($c->payload['emojis'])->shuffle()->take(3)->push($odd)->shuffle()->values();

        return $this->round('choice', 'Melyik nem illik a többi közé?', [
            'layout' => 'four',
            'options' => $opts->map(fn ($e, $i) => ['id' => (string) $i, 'emoji' => $e])->all(),
            'answer' => (string) $opts->search($odd),
            'onCorrect' => "Ügyes! A többi mind {$c->payload['category']}.",
        ], $c->id);
    }
}
