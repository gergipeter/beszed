<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

class HallgasdRounds extends RoundFactory
{
    private const OPTIONS = 3;

    public function build(Collection $items, int $level, int $count): array
    {
        if ($items->count() < 2) {
            return [];
        }

        $optionCount = min($items->count(), self::OPTIONS);
        $layout = [2 => 'two', 3 => 'three', 4 => 'four'][$optionCount];
        $this->favorLevel($items, $level);

        return $this->cycle($items, $count)->map(function ($target) use ($items, $optionCount, $layout) {
            $w = $target->payload['word'];
            $distractors = $items->reject(fn ($i) => $i->id === $target->id)->shuffle()->take($optionCount - 1);
            $opts = collect([$target])->merge($distractors)->shuffle()->values();

            return $this->round('choice', $w, [
                'layout' => $layout,
                'options' => $opts->map(fn ($o) => [
                    'id' => (string) $o->id, 'emoji' => $o->payload['emoji'], 'label' => $o->payload['word'],
                ])->all(),
                'answer' => (string) $target->id,
                'onCorrect' => "Igen! Ez {$this->art($w)} {$w}!",
                'onWrong' => "Ez nem {$this->art($w)} {$w}. Figyelj még egyszer!",
            ], $target->id, [$w]);
        })->values()->all();
    }
}
