<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/** Phonological awareness: which word rhymes with this one? Content items share a `rhyme` key. */
class RimeloRounds extends RoundFactory
{
    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $byRhyme = $items->groupBy(fn ($i) => $i->payload['rhyme']);
        $targets = $byRhyme->filter(fn ($g) => $g->count() >= 2)->keys()->all();
        $rounds = [];
        $last = null;

        for ($r = 0; $r < $count && count($targets) && $byRhyme->count() >= 3; $r++) {
            $rhyme = $last = $this->pickNot($targets, $last);
            [$ans, $ask] = $this->weightedShuffle($byRhyme[$rhyme])->take(2)->all();
            $word = $ask->payload['word'];

            // Distractors from other rhymes; avoid the same last vowel (kéz / szék) so the odd one out is clear.
            $others = $items->reject(fn ($i) => $i->payload['rhyme'] === $rhyme);
            $clear = $others->reject(fn ($i) => $this->lastVowel($i->payload['word']) === $this->lastVowel($word));
            $distract = ($clear->count() >= 2 ? $clear : $others)->shuffle()->take(2);

            $opts = $distract->push($ans)->shuffle()->values();
            $names = $opts->map(fn ($o) => $o->payload['word'])->all();

            $rounds[] = $this->round('choice',
                "Melyik rímel arra, hogy {$word}? {$names[0]}, {$names[1]}, vagy {$names[2]}?", [
                    'stimulus' => ['emoji' => $ask->payload['emoji'], 'label' => $word, 'say' => $word, 'highlight' => true],
                    'layout' => 'three',
                    'options' => $opts->map(fn ($o) => [
                        'id' => (string) $o->id, 'emoji' => $o->payload['emoji'], 'label' => $o->payload['word'],
                    ])->all(),
                    'answer' => (string) $ans->id,
                    'onCorrect' => "Igen! {$word}, {$ans->payload['word']}. Ez rímel!",
                    'onWrong' => $opts->mapWithKeys(fn ($o) => [
                        (string) $o->id => "{$word}, {$o->payload['word']}. Ez nem rímel. Próbáld újra!",
                    ])->all(),
                ], $ans->id);
        }

        return $rounds;
    }

    private function lastVowel(string $word): ?string
    {
        preg_match_all('/[aáeéiíoóöőuúüű]/iu', $word, $m);

        return mb_strtolower(end($m[0]) ?: '') ?: null;
    }
}
