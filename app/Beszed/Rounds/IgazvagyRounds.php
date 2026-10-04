<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * True or silly: Csillám says a sentence ("A hal a fán fészkel.") and the child taps 👍 Igaz or
 * 🤪 Butaság. After a silly one she says it right ("A hal a vízben úszik."). A session is about
 * half true, half silly, never more than three of one kind in a row, so guessing doesn't pay.
 * $level: 1 → obvious facts with a picture · 2 → everyday logic and "nem" · 3 → all/none, order,
 * seasons, cause and effect.
 */
class IgazvagyRounds extends RoundFactory
{
    public const OPTIONS = [
        ['id' => 'igaz', 'emoji' => '👍', 'label' => 'Igaz'],
        ['id' => 'butasag', 'emoji' => '🤪', 'label' => 'Butaság'],
    ];

    private const RIGHT_TRUE = ['Így van, ez igaz!', 'Bizony, ez igaz!', 'Úgy van, igaz!'];

    private const RIGHT_SILLY = ['Így van, ez butaság!', 'Bizony, butaság!', 'Jól figyeltél, ez butaság!'];

    /** No picture of its own: a speech bubble the child can tap to hear the sentence again. */
    private const NO_PICTURE = '💬';

    public function build(Collection $items, int $level, int $count): array
    {
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $level)->values();
        $pool = $pool->count() >= $count ? $pool : $items;
        $this->favorLevel($pool, $level);

        $true = $this->weightedShuffle($pool->filter(fn ($i) => $i->payload['truth'] === 'igaz'))->values();
        $silly = $this->weightedShuffle($pool->filter(fn ($i) => $i->payload['truth'] === 'butasag'))->values();
        $half = intdiv($count, 2) + ($count % 2 && random_int(0, 1) ? 1 : 0);
        $nSilly = min($count - min($half, $true->count()), $silly->count());
        $picked = $true->take($count - $nSilly)->concat($silly->take($nSilly));

        $rounds = [];
        foreach ($this->mix($picked)->values() as $r => $item) {
            $p = $item->payload;
            $silly = $p['truth'] === 'butasag';
            $ask = $r === 0 ? 'Igaz ez, vagy butaság?' : 'Igaz vagy butaság?';
            $parts = $r === 0 ? ['Figyelj jól, mit mondok!', $p['text'], $ask] : [$p['text'], $ask];

            $rounds[] = $this->round('choice', "{$p['text']} $ask", [
                'stimulus' => ['emoji' => ($p['emoji'] ?? null) ?: self::NO_PICTURE, 'say' => $p['text']],
                'layout' => 'two',
                'options' => self::OPTIONS,
                'answer' => $p['truth'],
                'onCorrect' => $silly
                    ? self::RIGHT_SILLY[array_rand(self::RIGHT_SILLY)].' '.$p['fix']
                    : self::RIGHT_TRUE[array_rand(self::RIGHT_TRUE)],
                'onWrong' => $silly ? "Hoppá, ez butaság! {$p['fix']}" : "Pedig ez igaz! Figyeld csak: {$p['text']}",
            ], $item->id, $parts);
        }

        return $rounds;
    }

    /** Random order with at most three of a kind (true or silly) in a row. */
    private function mix(Collection $items): Collection
    {
        for ($try = 0; $try < 50; $try++) {
            $order = $items->shuffle()->values();
            $run = 0;
            $ok = true;
            foreach ($order as $i => $item) {
                $run = $i > 0 && $order[$i - 1]->payload['truth'] === $item->payload['truth'] ? $run + 1 : 1;
                if ($run > 3) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $order;
            }
        }

        return $items->shuffle();
    }

    /** ContentRules: a silly sentence comes with its right version, a true one doesn't need one. */
    public static function check(array $p): array
    {
        $fix = trim($p['fix'] ?? '');

        return match (true) {
            str_contains($p['text'].$fix, '…') => ['text' => 'Ne legyen benne „…” (a gépi hang kiolvasná).'],
            ! preg_match('/[.!]$/u', trim($p['text'])) => ['text' => 'A mondat végén pont vagy felkiáltójel legyen.'],
            $p['truth'] === 'butasag' && $fix === '' => ['fix' => 'Butaságnál írd meg, hogyan helyes.'],
            $p['truth'] === 'butasag' && mb_strtolower($fix) === mb_strtolower(trim($p['text'])) => ['fix' => 'A helyes változat nem lehet ugyanaz, mint a butaság.'],
            $p['truth'] === 'butasag' && ! preg_match('/[.!]$/u', $fix) => ['fix' => 'A mondat végén pont vagy felkiáltójel legyen.'],
            $p['truth'] === 'igaz' && $fix !== '' => ['fix' => 'Igaz mondathoz nem kell javítás.'],
            default => [],
        };
    }
}
