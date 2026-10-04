<?php

namespace App\Beszed\Rounds;

use App\Beszed\Content\Hungarian;
use Illuminate\Support\Collection;

/**
 * Riddles: Csillám gives a few clues one by one ("Hosszú a füle. Nagyokat ugrik. Répát rágcsál.")
 * and the child finds the picture among others of the same group (animals with animals,
 * vehicles with vehicles), so the clues, not the group, give the answer away.
 * $level: 1 → three pictures, mostly three easy clues · 2 → two clues, what it is for ·
 * 3 → trickier clues, four pictures. A riddle's `close` names answers of its group that fit some
 * of its clues too (the bus and the tram); those are never offered beside it, either way round.
 */
class TalalosRounds extends RoundFactory
{
    private const PRAISE = ['Kitaláltad!', 'Ügyes vagy, eltaláltad!', 'Így van!', 'Nagyszerű, kitaláltad!'];

    public function build(Collection $items, int $level, int $count): array
    {
        $pool = $items->filter(fn ($i) => ($i->level ?? 1) <= $level)->values();
        $pool = $pool->count() >= $count ? $pool : $items;
        $this->favorLevel($pool, $level);
        $choices = $level >= 3 ? 4 : 3;
        $rounds = [];

        foreach ($this->cycle($pool, $count)->values() as $r => $item) {
            $p = $item->payload;
            $question = self::question($p);
            $options = $this->distractors($items, $item, $choices - 1)->push($item)->shuffle()->values();
            $clues = array_values($p['clues']);
            $parts = $r === 0 ? ['Találós kérdés! Figyeld a nyomokat!', ...$clues, $question] : [...$clues, $question];

            $rounds[] = $this->round('choice', implode(' ', [...$clues, $question]), [
                'stimulus' => ['emoji' => '❓', 'say' => implode(' ', [...$clues, $question])],
                'layout' => $choices === 4 ? 'four' : 'three',
                'options' => $options->map(fn ($o) => ['id' => (string) $o->id, 'emoji' => $o->payload['emoji'], 'label' => $o->payload['answer']])->all(),
                'answer' => (string) $item->id,
                'onCorrect' => self::PRAISE[array_rand(self::PRAISE)].' Ez '.$this->art($p['answer']).' '.$p['answer'].'!',
                'onWrong' => $options->reject(fn ($o) => $o->id === $item->id)->mapWithKeys(fn ($o) => [(string) $o->id => 'Ez '.$this->art($o->payload['answer']).' '.$o->payload['answer']
                    .($p['group'] === 'allat' ? ', de nem rá gondoltam.' : ', de nem erre gondoltam.')
                    .' Figyelj még egyszer! '.implode(' ', $clues)])->all(),
            ], $item->id, $parts);
        }

        return $rounds;
    }

    /** Kire gondolok? for animals, Mire gondolok? for everything else. */
    public static function question(array $p): string
    {
        return ($p['group'] ?? null) === 'allat' ? 'Kire gondolok?' : 'Mire gondolok?';
    }

    /** Two riddles that can't stand side by side: one's clues partly fit the other. */
    public static function close(array $a, array $b): bool
    {
        $fold = fn ($s) => Hungarian::fold(trim($s));

        return in_array($fold($b['answer']), array_map($fold, $a['close'] ?? []), true)
            || in_array($fold($a['answer']), array_map($fold, $b['close'] ?? []), true);
    }

    /** Wrong pictures from the riddle's own group, never a near fit, never the same picture. */
    private function distractors(Collection $items, $item, int $n): Collection
    {
        $p = $item->payload;
        $fair = $items->reject(fn ($o) => $o->id === $item->id
            || $o->payload['emoji'] === $p['emoji']
            || Hungarian::fold($o->payload['answer']) === Hungarian::fold($p['answer'])
            || self::close($p, $o->payload));
        $same = $fair->filter(fn ($o) => $o->payload['group'] === $p['group'])->shuffle();
        // a small group is topped up from the others (never happens with the shipped content)
        $picked = $same->take($n);
        if ($picked->count() < $n) {
            $picked = $picked->concat($fair->reject(fn ($o) => $o->payload['group'] === $p['group'])->shuffle()->take($n - $picked->count()));
        }

        return $picked->unique(fn ($o) => $o->payload['emoji'])->values();
    }

    /** ContentRules: no clue gives the answer away by saying it; a riddle is never close to itself. */
    public static function check(array $p): array
    {
        $answer = Hungarian::fold(trim($p['answer']));
        foreach ($p['clues'] as $clue) {
            if (str_contains(Hungarian::fold($clue), $answer)) {
                return ['clues' => "A nyom nem mondhatja ki a megfejtést („{$p['answer']}”)."];
            }
            if (! preg_match('/[.!?]$/u', trim($clue))) {
                return ['clues' => 'Minden nyom egy egész mondat, írásjellel a végén.'];
            }
            if (str_contains($clue, '…')) {
                return ['clues' => 'Ne legyen benne „…” (a gépi hang kiolvasná).'];
            }
        }
        if (in_array($answer, array_map(fn ($s) => Hungarian::fold(trim($s)), $p['close'] ?? []), true)) {
            return ['close' => 'A megfejtés nem lehet a saját hasonlója.'];
        }

        return [];
    }
}
