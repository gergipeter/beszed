<?php

namespace App\Beszed\Rounds;

use App\Beszed\Content\Hungarian;
use Illuminate\Support\Collection;

/**
 * Nursery rhymes with a missing word: Csillám says a traditional mondóka line by line, then again,
 * stopping right before a word ("Kapsz tejet,"), and the child taps that word's picture.
 * Afterwards she says the whole line. The content's own 1–3 level (rhyme length/difficulty) is
 * favoured by tier() across the full 1–100 adaptive level: low levels favour short rhymes, the
 * middle favours longer ones, the top the longest. The number of picture choices grows smoothly
 * from three to four (scaleInt()), and from level 67 on, a rhyme with a second gap (`gap2`) gives
 * two rounds in a row (the second one without saying the whole rhyme again).
 * The wrong pictures are other rhymes' gap words (and the item's own `distractors`), never a word
 * that is in this rhyme too, so only one picture can fill the gap.
 */
class MondokaRounds extends RoundFactory
{
    private const PRAISE = ['Ügyes vagy!', 'Így van!', 'Eltaláltad!', 'Szép volt!'];

    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $tier)->values();
        $pool = $pool->isEmpty() ? $items : $pool;
        $this->favorLevel($pool, $level);
        $choices = $this->scaleInt($level, 3, 4);
        $withGap2 = $tier >= 3;
        $pictures = self::pictures($items);
        $rounds = [];

        foreach ($this->cycle($pool, $count)->values() as $item) {
            if (count($rounds) >= $count) {
                break;
            }
            $p = $item->payload;
            $rounds[] = $this->gapRound($p, $item->id, $p['gap'], $p['name'], $p['emoji'], $pictures, $choices, count($rounds) === 0, false);
            // top tier: the same rhyme once more with its other gap, straight after
            if ($withGap2 && ! empty($p['gap2']) && count($rounds) < $count) {
                $rounds[] = $this->gapRound($p, $item->id, $p['gap2'], $p['name2'], $p['emoji2'], $pictures, $choices, false, true);
            }
        }

        return $rounds;
    }

    private function gapRound(array $p, int $id, string $gap, string $name, string $emoji, Collection $pictures, int $choices, bool $first, bool $again): array
    {
        $lines = array_values($p['lines']);
        [$lineIndex, $before] = self::locate($lines, $gap);
        // what Csillám says the second time: every line before the gap, then the gap's line up to it
        $lead = array_slice($lines, 0, $lineIndex);
        if (trim($before) !== '') {
            $lead[] = trim($before);
        }
        $ask = 'Melyik szó hiányzik?';
        // the caption only (never spoken: TTS would read "…" aloud)
        $text = trim(implode(' ', $lead)).' …?';

        $parts = match (true) {
            $again => ['Most egy másik szó hiányzik. Figyelj!', ...$lead, $ask],
            $first => ['Mondókázzunk! Hallgasd meg!', ...$lines, 'Most még egyszer, de egy szót kihagyok!', ...$lead, $ask],
            default => ['Hallgasd meg!', ...$lines, 'Most figyelj, melyik szó hiányzik!', ...$lead, $ask],
        };

        $inRhyme = Hungarian::fold(implode(' ', $lines));
        $wrong = $pictures
            ->reject(fn ($o) => $o['emoji'] === $emoji || str_contains($inRhyme, Hungarian::fold($o['name'])))
            ->shuffle()->take($choices - 1);
        $options = $wrong->push(['emoji' => $emoji, 'name' => $name])->shuffle()->values()
            ->map(fn ($o, $i) => ['id' => "o$i", 'emoji' => $o['emoji'], 'label' => $o['name']]);
        $answer = $options->firstWhere('emoji', $emoji)['id'];
        $full = rtrim($lines[$lineIndex], ' ,;:');

        return $this->round('choice', trim($text), [
            'stimulus' => ['emoji' => '👏', 'label' => $p['title'], 'say' => implode(' ', $lines)],
            'layout' => $choices === 4 ? 'four' : 'three',
            'options' => $options->all(),
            'answer' => $answer,
            'onCorrect' => self::PRAISE[array_rand(self::PRAISE)].' '.$this->ucfirst(preg_match('/[.!?]$/u', $full) ? $full : "$full!"),
            'onWrong' => $options->reject(fn ($o) => $o['id'] === $answer)->mapWithKeys(fn ($o) => [
                $o['id'] => 'Ez '.$this->art($o['label']).' '.$o['label'].', ez nem illik bele. Figyelj még egyszer! '.implode(' ', $lead),
            ])->all(),
        ], $id, $parts);
    }

    /** Every gap word's picture, once each: the wrong options come from here. */
    private static function pictures(Collection $items): Collection
    {
        return $items->flatMap(function ($i) {
            $p = $i->payload;
            $out = [['emoji' => $p['emoji'], 'name' => $p['name']]];
            if (! empty($p['gap2'])) {
                $out[] = ['emoji' => $p['emoji2'], 'name' => $p['name2']];
            }
            foreach ($p['distractors'] ?? [] as [$emoji, $name]) {
                $out[] = ['emoji' => $emoji, 'name' => $name];
            }

            return $out;
        })->unique('emoji')->values();
    }

    /** Where a word first stands as a whole word: [line index, the line's text before it] (null if nowhere). */
    public static function locate(array $lines, string $word): ?array
    {
        $pattern = '/(?<!\p{L})'.preg_quote(trim($word), '/').'(?!\p{L})/iu';
        foreach (array_values($lines) as $i => $line) {
            if (preg_match($pattern, $line, $m, PREG_OFFSET_CAPTURE)) {
                return [$i, substr($line, 0, $m[0][1])];
            }
        }

        return null;
    }

    /** ContentRules: each gap word stands in the rhyme, never as its very first word; a second gap needs its own picture. */
    public static function check(array $p): array
    {
        $lines = array_values($p['lines']);
        $start = mb_strtolower(implode(' ', array_map('trim', $lines)));
        $gaps = ['gap' => $p['gap']] + (empty($p['gap2']) ? [] : ['gap2' => $p['gap2']]);

        foreach ($gaps as $field => $gap) {
            $at = self::locate($lines, $gap);
            if (! $at) {
                return [$field => "„{$gap}” nincs benne a mondókában (pontosan úgy írd, ahogy a szövegben áll)."];
            }
            if ($at[0] === 0 && trim($at[1]) === '') {
                return [$field => 'A mondóka legelső szava nem maradhat ki: Csillám semmit sem mondana előtte.'];
            }
        }

        return match (true) {
            str_contains(implode(' ', $lines), '…') => ['lines' => 'Ne legyen benne „…” (a gépi hang kiolvasná).'],
            ! str_starts_with($start, mb_strtolower(rtrim(trim($p['title']), ' ,;:!?.'))) => ['title' => 'A cím a mondóka eleje legyen.'],
            ! empty($p['gap2']) && (empty($p['name2']) || empty($p['emoji2'])) => ['emoji2' => 'A második hiányzó szóhoz is kell kép és név.'],
            ! empty($p['gap2']) && mb_strtolower($p['gap2']) === mb_strtolower($p['gap']) => ['gap2' => 'A két hiányzó szó legyen különböző.'],
            ! empty($p['gap2']) && $p['emoji2'] === $p['emoji'] => ['emoji2' => 'A két hiányzó szóhoz két különböző kép kell.'],
            default => [],
        };
    }
}
