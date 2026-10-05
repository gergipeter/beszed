<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Testrészek (body parts). The content's own 1–3 level is a tier of the
 * 1–100 adaptive level (tier()): 1–33 → "Melyik a fül?" → three body parts,
 * then "Mutasd meg a füledet!". 34–66 → the inner parts and what each part
 * does ("Mivel hallunk?", four pictures). 67–100 → caring for the body —
 * "Mit húzunk a lábunkra…?" (zokni among other things) and "Hova húzzuk a
 * gyűrűt?" (the finger among body parts) — with the body part shown above.
 * favorLevel() biases content choice within the same three tiers.
 *
 * Wrong pictures come from the same kind (body parts or things); a row's
 * `close` lists pictures that would also be right, and they are never offered.
 */
class TestreszekRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $tier = $this->tier($level, 3);
        $own = $items->filter(fn ($i) => (int) $i->level === $tier)->values();
        $pool = $own->count() >= 6 ? $own : $items->filter(fn ($i) => (int) $i->level <= $tier)->values();
        if ($pool->isEmpty()) {
            $pool = $items->values();
        }
        [$body, $things] = self::pictures($items);
        $n = $tier === 2 ? 4 : 3;
        $rounds = [];

        foreach ($this->cycle($pool, $count)->values() as $item) {
            $p = $item->payload;
            $names = isset($body[$p['emoji']]) ? $body : $things;
            $wrong = collect($names)
                ->except([$p['emoji'], ...($p['close'] ?? [])])
                ->reject(fn ($name) => $name === $p['name'])
                ->keys()->shuffle()->take($n - 1);
            $options = $wrong->push($p['emoji'])->shuffle()->values();

            $data = [
                'layout' => $n === 4 ? 'four' : 'three',
                'options' => $options->map(fn ($e) => ['id' => $e === $p['emoji'] ? (string) $item->id : "x$e", 'emoji' => $e])->all(),
                'answer' => (string) $item->id,
                'onCorrect' => $p['say'],
                'onWrong' => $wrong->mapWithKeys(fn ($e) => ["x$e" => "Ez {$this->art($names[$e])} {$names[$e]}. {$p['question']}"])->all(),
            ];
            if (isset($p['part'])) {
                $data['stimulus'] = ['emoji' => $p['part']];
            }
            $rounds[] = $this->round('choice', $p['question'], $data, $item->id);
        }

        return $rounds;
    }

    /**
     * Picture => name, for body parts (the answers of name and function rows) and for the things of care rows.
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    public static function pictures(Collection $items): array
    {
        $body = $items->filter(fn ($i) => $i->payload['kind'] !== 'care')
            ->mapWithKeys(fn ($i) => [$i->payload['emoji'] => $i->payload['name']])->all();
        $things = $items->filter(fn ($i) => $i->payload['kind'] === 'care' && ! isset($body[$i->payload['emoji']]))
            ->mapWithKeys(fn ($i) => [$i->payload['emoji'] => $i->payload['name']])->all();

        return [$body, $things];
    }

    /**
     * Content rule: the answer is not among the pictures that "also fit", the picture above differs from it.
     *
     * @return array<string, string>
     */
    public static function check(array $p): array
    {
        return match (true) {
            in_array($p['emoji'], $p['close'] ?? [], true) => ['close' => 'A jó válasz képe nem lehet a „szintén jó” képek között.'],
            ($p['part'] ?? null) === $p['emoji'] => ['part' => 'A kérdés fölötti kép ne a jó válasz legyen.'],
            ! str_ends_with(trim($p['question']), '?') => ['question' => 'Kérdőjellel végződjön.'],
            default => [],
        };
    }
}
