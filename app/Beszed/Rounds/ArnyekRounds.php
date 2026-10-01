<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** Visual discrimination: whose shadow is it? The client draws the stimulus as a silhouette. */
class ArnyekRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);

        return $this->cycle($items, $count)->map(function ($it) use ($items) {
            $others = $items->reject(fn ($o) => $o->id === $it->id)->shuffle()->take(2);
            $opts = $others->push($it)->shuffle()->values();
            $name = $it->payload['name'];

            return $this->round('choice', 'Kinek az árnyéka ez? Keresd meg a képek között!', [
                'stimulus' => ['emoji' => $it->payload['emoji'], 'silhouette' => true],
                'layout' => 'three',
                'options' => $opts->map(fn ($o) => [
                    'id' => (string) $o->id, 'emoji' => $o->payload['emoji'], 'label' => $o->payload['name'],
                ])->all(),
                'answer' => (string) $it->id,
                'onCorrect' => "Igen! Ez {$this->art($name)} {$name} árnyéka!",
                'onWrong' => $opts->mapWithKeys(fn ($o) => [
                    (string) $o->id => "Ez {$this->art($o->payload['name'])} {$o->payload['name']}. Nézd meg jobban az árnyékot!",
                ])->all(),
            ], $it->id);
        })->values()->all();
    }
}
