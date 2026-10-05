<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Emotions: "Melyik arc szomorú?" (find the face), alternating with a little
 * situation ("Elszállt Nyuszi lufija.") where the child picks how someone feels
 * — reasoning from a situation to a feeling is harder than just finding the
 * named face, so its share of rounds grows with level: about a third of
 * rounds at level 1, half at the old level-3 band (67) and up. Each content
 * item is one feeling with a few faces and situations; `close` names feelings
 * too near to it to be a fair wrong choice in a situation (a surprise can
 * also be a fright). favorLevel() biases content choice towards the child's
 * own content-level tier, as in every other game.
 */
class ErzelmekRounds extends RoundFactory
{
    private const CHOICES = 3;

    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $situationShare = $this->scale($level, 1 / 3, 1 / 2);
        $face = fn ($i) => collect($i->payload['faces'] ?? [])->push($i->payload['emoji'])->unique()->random();
        $rounds = [];

        foreach ($this->cycle($items, $count)->values() as $r => $feel) {
            $name = $feel->payload['name'];
            $situations = $feel->payload['situations'] ?? [];
            $situation = (mt_rand() / mt_getrandmax()) < $situationShare && $situations ? $situations[array_rand($situations)] : null;

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
