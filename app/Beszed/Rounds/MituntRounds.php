<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * "Mi tűnt el?" (Kim's game): look at a row of pictures, a cloud hides them,
 * one is gone when they come back; pick it from a few choices.
 * $level = pictures to remember (3–6).
 */
class MituntRounds extends RoundFactory
{
    /** Looking time: a base plus a little per picture. */
    private const LOOK_MS = 1500;

    private const LOOK_PER_PICTURE_MS = 900;

    public function build(Collection $items, int $level, int $count): array
    {
        $n = max(3, min($level, $items->count() - 4));
        $choices = $n <= 4 ? 3 : 4;
        $picture = fn ($i) => ['id' => (string) $i->id, 'emoji' => $i->payload['emoji'], 'label' => $i->payload['name']];
        $rounds = [];

        foreach ($this->cycle($items, $count)->values() as $r => $missing) {
            $rest = $items->reject(fn ($i) => $i->id === $missing->id)->shuffle();
            $shown = $rest->take($n - 1)->push($missing)->shuffle()->values();
            // Choices: the missing picture and some that weren't there at all.
            $options = $rest->slice($n - 1)->take($choices - 1)->push($missing)->shuffle()->values();

            $prompt = $r === 0
                ? 'Jegyezd meg jól a képeket! Utána az egyik eltűnik, és kitalálod, melyik.'
                : 'Jegyezd meg jól a képeket!';

            $rounds[] = $this->round('vanish', $prompt, [
                'items' => $shown->map($picture)->all(),
                'missing' => (string) $missing->id,
                'options' => $options->map($picture)->all(),
                'lookMs' => self::LOOK_MS + self::LOOK_PER_PICTURE_MS * $n,
                'question' => 'Hopp, eltűnt valami! Melyik volt az?',
                'onCorrect' => 'Igen! '.$this->ucfirst($this->art($missing->payload['name']))." {$missing->payload['name']} tűnt el.",
                'onWrong' => 'Ez nem volt a képek között. Mi hiányzik?',
            ], $missing->id);
        }

        return $rounds;
    }
}
