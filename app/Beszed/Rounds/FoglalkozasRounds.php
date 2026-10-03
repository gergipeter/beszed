<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** Jobs: "Ki oltja a tüzet?" and the child taps the firefighter. $level = what to pick from: 1 → two, 2 → three, 3 → four. */
class FoglalkozasRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $n = min(4, max(2, $level + 1));
        $rounds = [];

        foreach ($this->cycle($items->values(), $count)->values() as $job) {
            $p = $job->payload;
            $opts = $items->reject(fn ($i) => $i->id === $job->id)->shuffle()->take($n - 1)->push($job)->shuffle()->values();

            $rounds[] = $this->round('choice', "Ki {$p['does']}?", [
                'layout' => ['two', 'three', 'four'][$n - 2],
                'options' => $opts->map(fn ($o) => ['id' => (string) $o->id, 'emoji' => $o->payload['emoji'], 'label' => $o->payload['job'], 'say' => $o->payload['job']])->all(),
                'answer' => (string) $job->id,
                'onCorrect' => "Igen! {$this->ucfirst($this->art($p['job']))} {$p['job']} {$p['does']}!",
                'onWrong' => $opts->mapWithKeys(fn ($o) => [
                    (string) $o->id => "{$this->ucfirst($this->art($o->payload['job']))} {$o->payload['job']} {$o->payload['does']}. Ki {$p['does']}?",
                ])->all(),
            ], $job->id);
        }

        return $rounds;
    }
}
