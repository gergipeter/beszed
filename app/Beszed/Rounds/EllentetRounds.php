<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Opposites: "Ez nagy. Melyik a kicsi?" The pairs go both ways, so the child hears each word as the question.
 * The adaptive level (1–100) splits into three equal tiers (tier()): tier 1 picks from two options, tiers
 * 2–3 from three; the pairs themselves come from the content's own level (favorLevel()).
 */
class EllentetRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $n = $this->tier($level, 3) >= 2 ? 3 : 2;
        $rounds = [];

        foreach ($this->cycle($items->values(), $count)->values() as $pair) {
            $p = $pair->payload;
            [$from, $fromEmoji, $to, $toEmoji] = mt_rand(0, 1)
                ? [$p['a'], $p['emojiA'], $p['b'], $p['emojiB']]
                : [$p['b'], $p['emojiB'], $p['a'], $p['emojiA']];

            // wrong ones: words of other pairs
            $wrong = $items->reject(fn ($i) => $i->id === $pair->id)->shuffle()->take($n - 1)
                ->map(fn ($i) => mt_rand(0, 1) ? [$i->payload['a'], $i->payload['emojiA']] : [$i->payload['b'], $i->payload['emojiB']]);
            $opts = $wrong->push([$to, $toEmoji])->shuffle()->values();

            $rounds[] = $this->round('choice', "Ez $from. Melyik {$this->art($to)} $to?", [
                'stimulus' => ['emoji' => $fromEmoji, 'label' => $from, 'say' => $from, 'highlight' => true],
                'layout' => $n === 3 ? 'three' : 'two',
                'options' => $opts->map(fn ($o) => ['id' => $o[0], 'emoji' => $o[1], 'label' => $o[0]])->all(),
                'answer' => $to,
                'onCorrect' => "Igen! {$this->ucfirst($this->art($from))} $from és {$this->art($to)} $to egymás ellentétei!",
                'onWrong' => $opts->mapWithKeys(fn ($o) => [$o[0] => "Ez $o[0]. Keresd, ami $to!"])->all(),
            ], $pair->id);
        }

        return $rounds;
    }
}
