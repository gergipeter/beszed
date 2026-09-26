<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** Categorisation: put each picture in the right basket. Each content item is one category. */
class ValogatoRounds extends RoundFactory
{
    /** level → pictures per round (half per basket) */
    public const PICTURES = [1 => 4, 2 => 6, 3 => 8];

    public function build(Collection $items, int $level, int $count): array
    {
        $per = intdiv(self::PICTURES[$level] ?? self::PICTURES[1], 2);
        $rounds = [];
        $lastPair = null;

        for ($r = 0; $r < $count && $items->count() >= 2; $r++) {
            do {
                [$a, $b] = $items->shuffle()->take(2)->values()->all();
                $pair = collect([$a->id, $b->id])->sort()->implode('-');
            } while ($items->count() > 2 && $pair === $lastPair);
            $lastPair = $pair;

            $pictures = collect([[$a, $b], [$b, $a]])->flatMap(fn ($cats) => collect($cats[0]->payload['items'])
                ->shuffle()->take($per)
                ->map(fn ($p) => [$p[0], $p[1], $cats[0], $cats[1]]))
                ->shuffle()->values()
                ->map(fn ($p, $i) => [
                    'id' => "p$i",
                    'emoji' => $p[0],
                    'label' => $p[1],
                    'bin' => $p[2]->payload['key'],
                    'wrong' => $this->ucfirst($this->art($p[1]))." {$p[1]} nem {$p[3]->payload['singular']}. Próbáld a másik kosarat!",
                ]);

            $bins = collect([$a, $b])->map(fn ($c) => [
                'id' => $c->payload['key'], 'emoji' => $c->payload['icon'], 'label' => $c->payload['label'],
            ]);

            $rounds[] = $this->round('sort',
                "Válogassuk szét! {$a->payload['label']} vagy ".mb_strtolower($b->payload['label']).'? Koppints a jó kosárra!', [
                    'bins' => $bins->all(),
                    'items' => $pictures->all(),
                    'onCorrect' => 'Ügyes! Minden a helyére került!',
                ], $a->id);
        }

        return $rounds;
    }
}
