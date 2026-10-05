<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Grammaticality judgment: Hungarian vs English-shaped word order. The content's own 1–3 level
 * (sentence complexity) is favoured by tier() across the full 1–100 adaptive level.
 */
class MelyikRounds extends RoundFactory
{
    private const SPEAKERS = [['Brumi', '🐻'], ['Nyuszi', '🐰']];

    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);

        return $this->cycle($items, $count)->map(function ($it) {
            ['good' => $good, 'bad' => $bad, 'emoji' => $emoji] = $it->payload;
            $lines = random_int(0, 1) ? [$good, $bad] : [$bad, $good];
            [$s0, $s1] = self::SPEAKERS;

            return $this->round('choice',
                "Figyeld! {$s0[0]} azt mondja: {$lines[0]} {$s1[0]} azt mondja: {$lines[1]} Melyik mondta szépen?", [
                    'variant' => 'speakers',
                    'stimulus' => ['emoji' => $emoji],
                    'layout' => 'two',
                    'options' => collect(self::SPEAKERS)->map(fn ($s, $i) => [
                        'id' => (string) $i, 'emoji' => $s[1], 'label' => $lines[$i], 'say' => "{$s[0]}: {$lines[$i]}",
                    ])->all(),
                    'answer' => $lines[0] === $good ? '0' : '1',
                    'onCorrect' => "Igen! Így mondjuk szépen: {$good}",
                    'onWrong' => "Nem egészen. Magyarul így mondjuk: {$good}",
                ], $it->id);
        })->values()->all();
    }
}
