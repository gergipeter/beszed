<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Listening choice: Csillám says a word, the child taps the matching picture
 * among distractors. $level drives two things: favorLevel() biases the word
 * pool towards the content's own (1-3) level as $level climbs its tiers, and
 * the number of options on screen scales continuously from 2 up to 4 across
 * the full 1-100 range, so later levels also have more distractors to tell
 * apart, not just harder words.
 */
class HallgasdRounds extends RoundFactory
{
    private const MIN_OPTIONS = 2;

    private const MAX_OPTIONS = 4;

    public function build(Collection $items, int $level, int $count): array
    {
        if ($items->count() < 2) {
            return [];
        }

        $wanted = $this->scaleInt($level, self::MIN_OPTIONS, self::MAX_OPTIONS);
        $optionCount = min($items->count(), $wanted);
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
