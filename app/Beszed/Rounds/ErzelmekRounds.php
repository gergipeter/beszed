<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Emotions: "Melyik arc szomorú?" (find the face), alternating with a little
 * situation ("Elszállt Nyuszi lufija.") where the child picks how someone feels.
 * Each content item is one feeling with a few faces and situations; `close`
 * names feelings too near to it to be a fair wrong choice in a situation
 * (a surprise can also be a fright).
 */
class ErzelmekRounds extends RoundFactory
{
    private const CHOICES = 3;

    public function build(Collection $items, int $level, int $count): array
    {
        $face = fn ($i) => collect($i->payload['faces'] ?? [])->push($i->payload['emoji'])->unique()->random();
        $rounds = [];

        foreach ($this->cycle($items, $count)->values() as $r => $feel) {
            $name = $feel->payload['name'];
            $situations = $feel->payload['situations'] ?? [];
            $situation = $r % 2 === 1 && $situations ? $situations[array_rand($situations)] : null;

            $close = fn ($i) => $situation && (
                in_array($i->payload['name'], $feel->payload['close'] ?? [], true)
                || in_array($name, $i->payload['close'] ?? [], true)
            );
            $others = $items->reject(fn ($i) => $i->id === $feel->id || $close($i))->shuffle()->take(self::CHOICES - 1);
            $options = $others->push($feel)->shuffle()->values()
                ->map(fn ($i) => ['id' => (string) $i->id, 'emoji' => $face($i), 'name' => $i->payload['name']]);

            $data = [
                'layout' => 'three',
                'options' => $options->map(fn ($o) => ['id' => $o['id'], 'emoji' => $o['emoji']])->all(),
                'answer' => (string) $feel->id,
                'onCorrect' => $situation ? "Igen! Ilyenkor az ember $name." : "Igen! Ez a $name arc.",
                'onWrong' => $options->mapWithKeys(fn ($o) => [$o['id'] => $situation
                    ? "Ez a {$o['name']} arc. Gondold végig még egyszer!"
                    : "Ez a {$o['name']} arc. Keresd a $name arcot!"])->all(),
            ];
            if ($situation) {
                $data['stimulus'] = ['emoji' => $situation[0], 'say' => $situation[1]];
            }

            $rounds[] = $this->round('choice', $situation ? "{$situation[1]} Hogy érzi most magát?" : "Melyik arc $name?", $data, $feel->id);
        }

        return $rounds;
    }
}
