<?php

namespace App\Beszed\Rounds;

use App\Beszed\Content\Hungarian;
use Illuminate\Support\Collection;

/**
 * Szóragasztó: compound words. The round kind is a tier of the 1–100 level,
 * evenly split in three: 1–33 → hó + ember → which picture is the glued
 * word? · 34–66 → the compound's picture → which two pictures make it? ·
 * 67–100 → take one part away — "Mi marad a hóemberből, ha elvesszük a
 * havat?" The inflected forms (hóemberből, havat) come from the content:
 * hó → havat can't be made by a rule. favorLevel() biases content choice
 * within the same three tiers.
 */
class SzoragasztoRounds extends RoundFactory
{
    private const PRAISE = ['Igen!', 'Ügyes vagy!', 'Így van!', 'Pontosan!', 'Szuper!'];

    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $tier = $this->tier($level, 3);
        $rounds = [];
        $praise = null;
        foreach ($this->cycle($items, $count)->values() as $r => $item) {
            $praise = $this->pickNot(self::PRAISE, $praise);
            $rounds[] = match ($tier) {
                1 => $this->glue($item, $items, $r === 0, $praise),
                2 => $this->split($item, $items, $r === 0, $praise),
                default => $this->delete($item, $items, $r === 0, $praise),
            };
        }

        return $rounds;
    }

    /** Every picture an item shows: the word and its two parts. @return list<string> */
    public static function pictures(array $p): array
    {
        return [$p['emoji'], $p['emojiA'], $p['emojiB']];
    }

    /** hó + ember → ⛄ among three compounds; one wrong one may share a part, so both parts have to be heard. */
    private function glue($item, Collection $items, bool $first, string $praise): array
    {
        $p = $item->payload;
        $mine = self::pictures($p);
        $others = $items->reject(fn ($o) => $o->id === $item->id || in_array($o->payload['emoji'], $mine, true))
            ->unique(fn ($o) => $o->payload['emoji'])->shuffle();
        $sharing = $others->first(fn ($o) => array_intersect([$o->payload['a'], $o->payload['b']], [$p['a'], $p['b']]));
        $wrong = collect($sharing ? [$sharing] : [])->merge($others->reject(fn ($o) => $o->id === $sharing?->id))->take(2);
        $opts = collect([$item])->merge($wrong)->shuffle()->values();
        $glued = "{$p['a']} meg {$p['b']}";

        $question = $first ? "Ragasszuk össze a két szót: {$p['a']}, {$p['b']}! Mi lesz belőle?" : $this->ucfirst($glued).'. Mi lesz belőle?';

        return $this->round('choice', $question, [
            'sequence' => [$p['emojiA'], $p['emojiB']],
            'noMissing' => true,
            'layout' => 'three',
            'options' => $opts->map(fn ($o) => ['id' => (string) $o->id, 'emoji' => $o->payload['emoji'], 'label' => $o->payload['word']])->all(),
            'answer' => (string) $item->id,
            'onCorrect' => "{$praise} ".$this->ucfirst($glued).": {$p['word']}!",
            'onWrong' => $opts->mapWithKeys(fn ($o) => [(string) $o->id => 'Ez '.Hungarian::article($o->payload['word'])." {$o->payload['word']}: {$o->payload['a']} meg {$o->payload['b']}. Nekünk {$glued} kell!"])->all(),
        ], $item->id);
    }

    /** ⛄ → which two pictures? The wrong pairs keep one right part, so a half-right guess doesn't pass. */
    private function split($item, Collection $items, bool $first, string $praise): array
    {
        $p = $item->payload;
        $right = ['a' => [$p['a'], $p['emojiA']], 'b' => [$p['b'], $p['emojiB']]];
        // every other part in the content, as [word, picture]
        $parts = $items->reject(fn ($o) => $o->id === $item->id)
            ->flatMap(fn ($o) => [[$o->payload['a'], $o->payload['emojiA']], [$o->payload['b'], $o->payload['emojiB']]])
            ->reject(fn ($part) => in_array($part[1], self::pictures($p), true) || in_array($part[0], [$p['a'], $p['b'], $p['word']], true))
            ->unique(fn ($part) => $part[1])->shuffle()->values();
        $pairs = collect([[$right['a'], $parts[0] ?? null], [$parts[1] ?? null, $right['b']]])
            ->filter(fn ($pair) => $pair[0] && $pair[1])->values();
        // too few parts to mix: whole pairs of other words
        foreach ($items->shuffle() as $o) {
            if ($pairs->count() >= 2) {
                break;
            }
            $op = $o->payload;
            if ($o->id !== $item->id && ! array_intersect([$op['emojiA'], $op['emojiB']], self::pictures($p))) {
                $pairs->push([[$op['a'], $op['emojiA']], [$op['b'], $op['emojiB']]]);
            }
        }
        $opts = collect([['id' => 'ok', 'pair' => [$right['a'], $right['b']]]])
            ->merge($pairs->take(2)->map(fn ($pair, $k) => ['id' => "x$k", 'pair' => $pair]))
            ->shuffle()->values();
        $label = fn ($pair) => "{$pair[0][0]} + {$pair[1][0]}";

        $question = $first
            ? 'Ez '.Hungarian::article($p['word'])." {$p['word']}. Melyik két szóból ragasztottuk össze?"
            : $this->ucfirst($p['word']).'. Melyik két szóból áll?';

        return $this->round('choice', $question, [
            'stimulus' => ['emoji' => $p['emoji'], 'label' => $p['word'], 'say' => $p['word']],
            'layout' => 'three',
            'options' => $opts->map(fn ($o) => [
                // one string: the two pictures side by side with the words under them (an `emojis` list would stack them, without a label)
                'id' => $o['id'], 'emoji' => $o['pair'][0][1].$o['pair'][1][1], 'label' => $label($o['pair']),
            ])->all(),
            'answer' => 'ok',
            'onCorrect' => "{$praise} {$this->ucfirst($p['a'])} meg {$p['b']}: {$p['word']}!",
            'onWrong' => $opts->mapWithKeys(fn ($o) => [$o['id'] => $this->ucfirst($o['pair'][0][0])." meg {$o['pair'][1][0]}? Abból nem {$p['word']} lesz. Próbáld újra!"])->all(),
        ], $item->id);
    }

    /** "Mi marad a hóemberből, ha elvesszük a havat?" → ember; the part taken away is one of the wrong answers. */
    private function delete($item, Collection $items, bool $first, string $praise): array
    {
        $p = $item->payload;
        $takeA = (bool) random_int(0, 1);
        [$gone, $goneEmoji, $goneAcc] = $takeA ? [$p['a'], $p['emojiA'], $p['accA']] : [$p['b'], $p['emojiB'], $p['accB']];
        [$left, $leftEmoji] = $takeA ? [$p['b'], $p['emojiB']] : [$p['a'], $p['emojiA']];
        $third = $items->reject(fn ($o) => $o->id === $item->id)
            ->flatMap(fn ($o) => [[$o->payload['a'], $o->payload['emojiA']], [$o->payload['b'], $o->payload['emojiB']]])
            ->reject(fn ($part) => in_array($part[1], self::pictures($p), true) || in_array($part[0], [$p['a'], $p['b'], $p['word']], true))
            ->shuffle()->first();
        $opts = collect([
            ['id' => 'left', 'emoji' => $leftEmoji, 'label' => $left],
            ['id' => 'gone', 'emoji' => $goneEmoji, 'label' => $gone],
            ...($third ? [['id' => 'other', 'emoji' => $third[1], 'label' => $third[0]]] : []),
        ])->shuffle()->values();

        $from = Hungarian::article($p['from'])." {$p['from']}";
        $take = Hungarian::article($goneAcc)." {$goneAcc}";
        $question = ($first ? 'Most elveszünk egy darabot a szóból! ' : '')."Mi marad {$from}, ha elvesszük {$take}?";

        return $this->round('choice', $question, [
            'stimulus' => ['emoji' => $p['emoji'], 'label' => $p['word'], 'say' => $p['word']],
            'layout' => 'three',
            'options' => $opts->all(),
            'answer' => 'left',
            'onCorrect' => "{$praise} Ha {$from} elvesszük {$take}, {$left} marad.",
            'onWrong' => [
                'gone' => "Nem, {$take} elvettük! Mi maradt?",
                'other' => ($third ? $this->ucfirst($third[0]) : '').'? Az nincs benne '.Hungarian::article($p['word'])." {$p['word']} szóban.",
            ],
        ], $item->id);
    }
}
