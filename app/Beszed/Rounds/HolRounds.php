<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** Relational vocabulary. Scene layouts live in the client's SceneView.vue. */
class HolRounds extends RoundFactory
{
    public const RELATIONS = [
        'folott' => 'a doboz fölött',
        'alatt' => 'a doboz alatt',
        'jobb' => 'a doboz jobb oldalán',
        'bal' => 'a doboz bal oldalán',
        'mogott' => 'a doboz mögött',
        'elott' => 'a doboz előtt',
        'kozott' => 'a két doboz között',
    ];

    /** Pairs that look too similar to be shown side by side. */
    private const CONFLICT = ['folott' => 'mogott', 'mogott' => 'folott', 'alatt' => 'elott', 'elott' => 'alatt'];

    public function build(Collection $items, int $level, int $count): array
    {
        $keys = array_keys(self::RELATIONS);
        $last = null;

        return $this->cycle($items, $count)->map(function ($it) use ($keys, &$last) {
            $obj = $it->payload;
            $t = $last = $this->pickNot($keys, $last);

            $others = [];
            foreach (collect($keys)->shuffle() as $k) {
                if (count($others) === 2) {
                    break;
                }
                if ($k === $t || (self::CONFLICT[$t] ?? null) === $k) {
                    continue;
                }
                if (collect($others)->contains(fn ($o) => (self::CONFLICT[$o] ?? null) === $k)) {
                    continue;
                }
                $others[] = $k;
            }
            $opts = collect([$t, ...$others])->shuffle()->values();
            $a = $this->art($obj['name']);

            return $this->round('choice', "Melyik képen van {$a} {$obj['name']} ".self::RELATIONS[$t].'?', [
                'layout' => 'three',
                'options' => $opts->map(fn ($k) => ['id' => $k, 'scene' => $k, 'emoji' => $obj['emoji']])->all(),
                'answer' => $t,
                'onCorrect' => 'Igen! '.$this->ucfirst($a)." {$obj['name']} ".self::RELATIONS[$t].' van!',
                'onWrong' => $opts->mapWithKeys(fn ($k) => [
                    $k => "Ezen a képen {$a} {$obj['name']} ".self::RELATIONS[$k].' van. Keresd tovább!',
                ])->all(),
            ], $it->id);
        })->values()->all();
    }
}
