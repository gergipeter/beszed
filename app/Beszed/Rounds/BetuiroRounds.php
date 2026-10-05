<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Letter writing with a finger: the dotted letter, its strokes numbered. Each content item is one letter
 * (the strokes live in the client, engines/letter/glyphs.js). The adaptive level (1–100) only biases which
 * letters come up, via favorLevel()'s 1–3 content-level tiering: straight capitals first, then round
 * capitals, then small letters, each favoured more as the level climbs through its third of the range.
 */
class BetuiroRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $rounds = [];

        foreach ($this->cycle($items->values(), $count)->values() as $r => $letter) {
            $glyph = $letter->payload['glyph'];
            $name = $letter->payload['name'];
            $prompt = $r === 0
                ? "Írd le ujjal a $name betűt! Kövesd a pöttyöket a számok sorrendjében!"
                : "Írd le a $name betűt!";

            $rounds[] = $this->round('letter', $prompt, [
                'glyph' => $glyph,
                'name' => $name,
                'onCorrect' => "Szép munka! Ez a $name betű!",
            ], $letter->id);
        }

        return $rounds;
    }
}
