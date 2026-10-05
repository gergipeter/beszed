<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Relational vocabulary. Scene layouts live in the client's SceneView.vue.
 * $level (1–100, tier(level, 3)): tier 1 → up/down only, 2 options · tier 2 → + front/back, 3 options ·
 * tier 3 → + left/right and "between" (harder — no fixed visual cue), 3 options.
 */
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

    /** Relations unlocked by tier: up/down first, then front/back, then left/right and "between" (harder — no fixed visual cue). */
    private const RELATIONS_BY_TIER = [
        1 => ['folott', 'alatt'],
        2 => ['folott', 'alatt', 'mogott', 'elott'],
        3 => ['folott', 'alatt', 'mogott', 'elott', 'jobb', 'bal', 'kozott'],
    ];

    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        $keys = self::RELATIONS_BY_TIER[$tier];
        $optionCount = $tier === 1 ? 2 : 3;
        $last = null;

        return $this->cycle($items, $count)->map(function ($it) use ($keys, $optionCount, &$last) {
            $obj = $it->payload;
            $t = $last = $this->pickNot($keys, $last);

            $others = [];
            foreach (collect($keys)->shuffle() as $k) {
                if (count($others) === $optionCount - 1) {
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
                'layout' => $opts->count() > 2 ? 'three' : 'two',
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
