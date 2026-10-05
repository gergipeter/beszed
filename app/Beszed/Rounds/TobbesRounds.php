<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * One or many: "Hol vannak a kutyák?" / "Melyik a kutya?" with one picture against three. Hearing and
 * saying the plural right is Hungarian grammar (kutya → kutyák, ló → lovak). $level (1–100) maps onto the
 * content's own 1–3 level via favorLevel()/tier(): plain -k, then -ak/-ek/-ok/-ök, then the stems that
 * change, each a third of the band (1–33, 34–66, 67–100) rather than a single level each.
 */
class TobbesRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $rounds = [];

        foreach ($this->cycle($items->values(), $count)->values() as $r => $item) {
            ['sg' => $sg, 'pl' => $pl, 'emoji' => $emoji] = $item->payload;
            $many = $r % 2 === 0;
            $prompt = $many
                ? "Hol vannak {$this->art($pl)} $pl?"
                : "Melyik {$this->art($sg)} $sg?";
            $opts = collect([
                ['id' => 'one', 'emojis' => [$emoji]],
                ['id' => 'many', 'emojis' => [$emoji, $emoji, $emoji]],
            ])->shuffle()->values();

            $rounds[] = $this->round('choice', $prompt, [
                'layout' => 'two',
                'options' => $opts->all(),
                'answer' => $many ? 'many' : 'one',
                'onCorrect' => "Igen! Egy $sg. Sok $sg: $pl!",
                'onWrong' => $many
                    ? ['one' => "Ez csak egy $sg. Keresd, ahol több van: $pl!"]
                    : ['many' => "Ez sok $sg, vagyis $pl. Keresd az egyet!"],
            ], $item->id);
        }

        return $rounds;
    }
}
