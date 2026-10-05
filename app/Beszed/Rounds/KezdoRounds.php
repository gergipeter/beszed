<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** $level (1–100): word difficulty, favoured via favorLevel() against the content's own 1–3 level field. */
class KezdoRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $bySound = $items->groupBy(fn ($i) => $i->payload['sound']);
        $targets = $bySound->filter(fn ($g) => $g->count() >= 2)->keys()->all();
        $rounds = [];
        $last = null;

        for ($r = 0; $r < $count && count($targets) && $bySound->count() >= 3; $r++) {
            $sound = $last = $this->pickNot($targets, $last);
            [$ans, $ex] = $this->weightedShuffle($bySound[$sound])->take(2)->all();
            $others = $bySound->keys()->reject(fn ($s) => $s === $sound)->shuffle()->take(2)
                ->map(fn ($s) => $bySound[$s]->random());
            $opts = collect([$ans])->merge($others)->shuffle()->values();

            $exW = $ex->payload['word'];
            $names = $opts->map(fn ($o) => $o->payload['word'])->all();

            $rounds[] = $this->round('choice',
                "Melyik kezdődik úgy, mint {$this->art($exW)} {$exW}? {$names[0]}, {$names[1]}, vagy {$names[2]}?", [
                    'stimulus' => ['emoji' => $ex->payload['emoji'], 'label' => $exW, 'say' => $exW, 'highlight' => true],
                    'layout' => 'three',
                    'options' => $opts->map(fn ($o) => [
                        'id' => (string) $o->id, 'emoji' => $o->payload['emoji'], 'label' => $o->payload['word'],
                    ])->all(),
                    'answer' => (string) $ans->id,
                    'onCorrect' => "Igen! {$exW}, {$ans->payload['word']}. Ugyanúgy kezdődik!",
                    'onWrong' => $opts->mapWithKeys(fn ($o) => [
                        (string) $o->id => "{$o->payload['word']}. Nem ugyanúgy kezdődik. Próbáld újra!",
                    ])->all(),
                ], $ans->id);
        }

        return $rounds;
    }
}
