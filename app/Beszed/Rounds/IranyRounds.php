<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Left and right, up and down. Tier 1 (levels 1-33): tap the arrow that points that way.
 * Tier 2 (34-66): who stands leftmost, rightmost or in the middle of three animals in a row.
 * Tier 3 (67-100): who stands right next to an animal, on its left or right (looking at the
 * picture). The row the child reads grows from 3 to 4 animals within tier 3 as $level climbs,
 * so the task doesn't stay flat for the whole tier.
 */
class IranyRounds extends RoundFactory
{
    private const ARROWS = [
        'bal' => ['⬅️', 'balra'], 'jobb' => ['➡️', 'jobbra'], 'fel' => ['⬆️', 'felfelé'], 'le' => ['⬇️', 'lefelé'],
    ];

    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        $rowSize = $this->scaleInt($level, 3, 4);
        $last = null;

        return $this->cycle($items, $count)->map(function ($it) use ($tier, $rowSize, $items, &$last) {
            return match ($tier) {
                1 => $this->arrow($it, $last = $this->pickNot(array_keys(self::ARROWS), $last)),
                2 => $this->position($it, $items),
                default => $this->neighbour($it, $items, $rowSize),
            };
        })->values()->all();
    }

    private function arrow($it, string $key): array
    {
        [, $word] = self::ARROWS[$key];
        // a different order every time, so the child reads the arrow, not its place
        $opts = collect(array_keys(self::ARROWS))->shuffle()->values();

        return $this->round('choice', "Koppints arra a nyílra, amelyik {$word} mutat!", [
            'layout' => 'four',
            'options' => $opts->map(fn ($k) => ['id' => $k, 'emoji' => self::ARROWS[$k][0]])->all(),
            'answer' => $key,
            'onCorrect' => "Igen! Ez a nyíl mutat {$word}!",
            'onWrong' => 'Nézd meg jól, merre mutat a nyíl hegye!',
        ], $it->id);
    }

    /** $n different animals in a row, this item among them. @return list<array{name: string, emoji: string}> */
    private function row(Collection $pool, $it, int $n): array
    {
        return $pool->reject(fn ($i) => $i->id === $it->id || $i->payload['emoji'] === $it->payload['emoji'])
            ->unique(fn ($i) => $i->payload['emoji'])->shuffle()->take($n - 1)
            ->prepend($it)->shuffle()->values()->map(fn ($i) => $i->payload)->all();
    }

    private function position($it, Collection $pool): array
    {
        $row = $this->row($pool, $it, 3);
        [$idx, $word] = [[0, 'legbalra'], [2, 'legjobbra'], [1, 'középen']][random_int(0, 2)];
        $answer = $row[$idx];

        return $this->round('choice', "Ki áll {$word} a képen?", [
            'layout' => 'three',
            'sequence' => array_column($row, 'emoji'),
            'noMissing' => true,
            'options' => collect($row)->shuffle()->values()->map(fn ($a) => ['id' => $a['emoji'], 'emoji' => $a['emoji'], 'label' => $a['name']])->all(),
            'answer' => $answer['emoji'],
            'onCorrect' => 'Igen! '.$this->ucfirst($this->art($answer['name']))." {$answer['name']} áll {$word}!",
            'onWrong' => 'Nézd meg a sort: melyik oldaltól számolunk?',
        ], $it->id);
    }

    private function neighbour($it, Collection $pool, int $n = 4): array
    {
        $row = $this->row($pool, $it, $n);
        $last = $n - 1;
        $i = random_int(0, $last);
        $right = $i === 0 || ($i !== $last && random_int(0, 1) === 1);
        $anchor = $row[$i];
        $answer = $row[$right ? $i + 1 : $i - 1];
        $side = $right ? 'jobb' : 'bal';
        $a = $this->art($anchor['name']);
        $opts = collect($row)->reject(fn ($r) => $r['emoji'] === $answer['emoji'] || $r['emoji'] === $anchor['emoji'])->take(2)
            ->prepend($answer)->shuffle()->values();

        return $this->round('choice', "Nézd a képet! Ki áll közvetlenül {$a} {$anchor['name']} {$side} oldalán?", [
            'layout' => 'three',
            'sequence' => array_column($row, 'emoji'),
            'noMissing' => true,
            'options' => $opts->map(fn ($x) => ['id' => $x['emoji'], 'emoji' => $x['emoji'], 'label' => $x['name']])->all(),
            'answer' => $answer['emoji'],
            'onCorrect' => 'Igen! '.$this->ucfirst($this->art($answer['name']))." {$answer['name']} áll {$a} {$anchor['name']} {$side} oldalán!",
            'onWrong' => "Keresd meg először {$a} {$anchor['name']} a sorban, aztán nézd a szomszédját!",
        ], $it->id);
    }
}
