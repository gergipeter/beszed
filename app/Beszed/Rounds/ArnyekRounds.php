<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Visual discrimination: whose shadow is it? The client draws the stimulus as a silhouette.
 * $level (1–100) biases content choice towards the child's own content-level tier (favorLevel(), from the
 * content's own level field: distinct silhouettes → look-alikes) and scales the number of pictures to
 * choose among continuously from 3 (two distractors) at low levels to 4 (three distractors) from the
 * middle of the range up — one more shadow to tell apart, not a sudden jump.
 */
class ArnyekRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $distractors = $this->scaleInt($level, 2, 3);

        return $this->cycle($items, $count)->map(function ($it) use ($items, $distractors) {
            $others = $items->reject(fn ($o) => $o->id === $it->id)->shuffle()->take($distractors);
            $opts = $others->push($it)->shuffle()->values();
            $name = $it->payload['name'];

            return $this->round('choice', 'Kinek az árnyéka ez? Keresd meg a képek között!', [
                'stimulus' => ['emoji' => $it->payload['emoji'], 'silhouette' => true],
                'layout' => $opts->count() === 4 ? 'four' : 'three',
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
