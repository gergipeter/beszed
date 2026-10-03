<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Colours and shapes: "Melyik a piros?" with three to pick from, all of one kind.
 * $level: 1 → colours, 2 → shapes, 3 → both, in turn.
 */
class SzinekRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $kinds = $items->groupBy(fn ($i) => $i->payload['kind']);
        $rounds = [];
        $last = null;

        for ($r = 0; $r < $count; $r++) {
            $kind = match ($level) {
                1 => 'color',
                2 => 'shape',
                default => $r % 2 ? 'shape' : 'color',
            };
            $pool = $kinds[$kind] ?? $items;
            $target = $this->weightedShuffle($pool->reject(fn ($i) => $i->id === $last))->first() ?? $pool->first();
            $last = $target->id;
            $opts = $pool->reject(fn ($i) => $i->id === $target->id)->shuffle()->take(2)->push($target)->shuffle()->values();
            $name = $target->payload['name'];

            $rounds[] = $this->round('choice', "Melyik {$this->art($name)} $name?", [
                'layout' => 'three',
                'options' => $opts->map(fn ($o) => ['id' => (string) $o->id, 'emoji' => $o->payload['emoji']])->all(),
                'answer' => (string) $target->id,
                'onCorrect' => "Igen! Ez {$this->art($name)} $name!",
                'onWrong' => $opts->mapWithKeys(fn ($o) => [
                    (string) $o->id => "Ez {$this->art($o->payload['name'])} {$o->payload['name']}. Keresd {$this->art($name)} $name!",
                ])->all(),
            ], $target->id);
        }

        return $rounds;
    }
}
