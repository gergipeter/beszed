<?php

namespace App\Beszed\Rounds;

use App\Beszed\Content\Hungarian;
use Illuminate\Support\Collection;

/**
 * Csigabeszéd: the slow snail says a word in pieces (ci… ca), the child puts it together and taps its picture.
 * Level 1: two syllables, three pictures · 2: three syllables · 3: four or more syllables, four pictures.
 * Each piece is its own utterance, so the voice leaves a real pause between them (an ellipsis would be read
 * out as "pont pont pont" by Piper). A sound on its own ("sss… ó") can't be said by either voice, so the
 * pieces are always syllables.
 */
class CsigabeszedRounds extends RoundFactory
{
    private const ASK = ['Mi lehet ez?', 'Mit mondott a csiga?', 'Melyik képet mondta?'];

    private const PRAISE = ['Igen!', 'Ügyes vagy!', 'Kitaláltad!', 'Így van!', 'Szuper!'];

    public function build(Collection $items, int $level, int $count): array
    {
        $fits = fn ($i) => match (true) {
            $level <= 1 => count($i->payload['pieces']) <= 2,
            $level === 2 => count($i->payload['pieces']) === 3,
            default => count($i->payload['pieces']) >= 4,
        };
        $pool = $items->filter($fits)->values();
        if ($pool->count() < 4) {
            $pool = $items;
        }
        $this->favorLevel($pool, $level);
        $choices = $level >= 3 ? 4 : 3;

        $rounds = [];
        $ask = $praise = null;
        foreach ($this->cycle($pool, $count)->values() as $r => $item) {
            $p = $item->payload;
            $pieces = $p['pieces'];
            $first = mb_strtolower($pieces[0]);
            $n = count($pieces);
            // never a word that starts with the same piece (ka… could be kacsa or kakas), nor the same picture
            $others = $items->reject(fn ($o) => $o->id === $item->id
                || mb_strtolower($o->payload['pieces'][0]) === $first
                || $o->payload['emoji'] === $p['emoji']
                || mb_strtolower($o->payload['word']) === mb_strtolower($p['word']))
                ->unique(fn ($o) => $o->payload['emoji'])
                ->shuffle()
                // as long as the answer if possible: then length gives nothing away
                ->sortBy(fn ($o) => abs(count($o->payload['pieces']) - $n))
                ->take($choices - 1);
            $opts = collect([$item])->merge($others)->shuffle()->values();

            $ask = $this->pickNot(self::ASK, $ask);
            $praise = $this->pickNot(self::PRAISE, $praise);
            $word = $p['word'];
            $parts = [...($r === 0 ? ['Figyelj, a csiga lassan beszél!'] : []), ...$pieces, $ask];

            $rounds[] = $this->round('choice', ($r === 0 ? 'A csiga lassan beszél: ' : '').implode(' – ', $pieces).". $ask", [
                'stimulus' => ['emoji' => '🐌', 'say' => implode('. ', $pieces).'.'],
                'layout' => $choices === 4 ? 'four' : 'three',
                'options' => $opts->map(fn ($o) => ['id' => (string) $o->id, 'emoji' => $o->payload['emoji'], 'label' => $o->payload['word']])->all(),
                'answer' => (string) $item->id,
                'onCorrect' => "{$praise} ".$this->ucfirst(implode(', ', $pieces)).": {$word}!",
                'onWrong' => $opts->mapWithKeys(fn ($o) => [(string) $o->id => 'Ez '.Hungarian::article($o->payload['word']).' '.$o->payload['word'].'. Figyeld a csigát: '.implode(', ', $pieces).'.'])->all(),
            ], $item->id, $parts);
        }

        return $rounds;
    }
}
