<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Mi illik hozzá? $level spans 1–100 (RoundFactory::tier(), 3 bands matching the content's own 1–3
 * `level` field): tier 1 (levels 1–33): go-togethers — "Mi illik a zoknihoz?" 🧦 → the shoe among three
 * pictures. Tier 2 (34–66): what a thing is for — "Mivel vágjuk a papírt?" → the scissors among four.
 * Tier 3 (67–100): group names — 🍎🍌🍐 "Hogy hívjuk őket együtt?" with three names said aloud. favorLevel()
 * biases the review pool towards the matching content tier throughout, so the mix of pairs/tools/groups
 * drifts gradually across a tier's levels rather than switching sharply only at its first one.
 *
 * The wrong pictures of a pair or a tool come only from the row's own hand-picked
 * `wrong` list (so a knife is never offered next to the scissors); a group's wrong
 * names are other groups, minus the ones its `close` says would also be right.
 */
class IllikRounds extends RoundFactory
{
    public const SPEAKER = '🔊';

    private const LISTEN = 'Koppints a hangszórókra, és hallgasd meg őket! A jó alatt nyomd meg a felfelé mutató ujjat!';

    public function build(Collection $items, int $level, int $count): array
    {
        $this->favorLevel($items, $level);
        $tier = $this->tier($level, 3);
        $own = $items->filter(fn ($i) => (int) $i->level === $tier)->values();
        $pool = $own->count() >= 6 ? $own : $items->filter(fn ($i) => (int) $i->level <= $tier)->values();
        if ($pool->isEmpty()) {
            $pool = $items->values();
        }
        $groups = $items->filter(fn ($i) => $i->payload['kind'] === 'category')->map(fn ($i) => $i->payload['answer'])->unique()->values();
        $explained = false;
        $rounds = [];

        foreach ($this->cycle($pool, $count)->values() as $item) {
            if ($item->payload['kind'] === 'category' && $groups->count() >= 3) {
                $rounds[] = $this->group($item, $groups, $explained);
                $explained = true;
            } else {
                $rounds[] = $this->picture($item, $item->payload['kind'] === 'function' ? 4 : 3);
            }
        }

        return $rounds;
    }

    /** A pair or a tool: the right picture among the row's own wrong ones. */
    private function picture($item, int $n): array
    {
        $p = $item->payload;
        $wrong = collect($p['wrong'])->reject(fn ($e) => $e === $p['answerEmoji'])->unique()->shuffle()->take($n - 1)->values();
        $options = $wrong->map(fn ($e, $k) => ['id' => "w$k", 'emoji' => $e])
            ->push(['id' => (string) $item->id, 'emoji' => $p['answerEmoji']])->shuffle()->values();
        $again = $p['kind'] === 'function' ? 'Ezzel nem megy. ' : 'Ez nem illik hozzá. ';

        $data = [
            'layout' => count($options) === 4 ? 'four' : 'three',
            'options' => $options->all(),
            'answer' => (string) $item->id,
            'onCorrect' => $p['say'] ?? "Igen! {$this->ucfirst($p['answer'])}.",
            'onWrong' => $again.$p['question'],
        ];
        if (! empty($p['emoji'])) {
            $data['stimulus'] = ['emoji' => $p['emoji']];
        }

        return $this->round('choice', $p['question'], $data, $item->id);
    }

    /** Three or four pictures shown together, three group names said aloud. */
    private function group($item, Collection $groups, bool $explained): array
    {
        $p = $item->payload;
        $wrong = $groups->reject(fn ($g) => $g === $p['answer'] || in_array($g, $p['close'] ?? [], true))->shuffle()->take(2);
        $names = $wrong->push($p['answer'])->shuffle()->values();
        $id = fn (string $g) => $g === $p['answer'] ? (string) $item->id : 'g'.md5($g);

        return $this->round('choice', ($explained ? '' : self::LISTEN.' ').$p['question'], [
            'variant' => 'speakers',
            'layout' => 'three',
            'sequence' => self::emojis($p['emoji']),
            'noMissing' => true,
            'options' => $names->map(fn ($g) => ['id' => $id($g), 'emoji' => self::SPEAKER, 'label' => $g, 'say' => "{$this->ucfirst($g)}."])->all(),
            'answer' => (string) $item->id,
            'onCorrect' => "Igen! Ezek mind {$p['answer']}.",
            'onWrong' => $names->reject(fn ($g) => $g === $p['answer'])
                ->mapWithKeys(fn ($g) => [$id($g) => "Hmm, ezek nem $g. Nézd meg jól őket!"])->all(),
        ], $item->id);
    }

    /** "🍎🍌🍐" → ['🍎', '🍌', '🍐'] (one per grapheme, so 🛋️ keeps its selector). */
    public static function emojis(string $s): array
    {
        preg_match_all('/\X/u', $s, $m);

        return array_values(array_filter($m[0], fn ($g) => trim($g) !== ''));
    }

    /**
     * Content rule: a pair or a tool has its picture, a sentence for the right answer and at least three wrong
     * pictures (none of them the right one or the question's own picture); a group shows 3–4 pictures.
     *
     * @return array<string, string>
     */
    public static function check(array $p): array
    {
        if ($p['kind'] === 'category') {
            $n = count(self::emojis($p['emoji'] ?? ''));

            return match (true) {
                $n < 3 || $n > 4 => ['emoji' => 'Egy csoporthoz 3–4 kép kell.'],
                in_array($p['answer'], $p['close'] ?? [], true) => ['close' => 'A jó csoportnév nem lehet a „szintén jó” nevek között.'],
                default => [],
            };
        }
        $wrong = $p['wrong'] ?? [];

        return match (true) {
            empty($p['answerEmoji']) => ['answerEmoji' => 'A jó válasz képe kötelező.'],
            $p['kind'] === 'pair' && empty($p['emoji']) => ['emoji' => 'Kell egy kép, amihez a párt keressük.'],
            count(array_unique($wrong)) < 3 => ['wrong' => 'Legalább 3 különböző rossz kép kell.'],
            in_array($p['answerEmoji'], $wrong, true) => ['wrong' => 'A jó válasz nem lehet a rosszak között.'],
            ! empty($p['emoji']) && ($p['emoji'] === $p['answerEmoji'] || in_array($p['emoji'], $wrong, true)) => ['emoji' => 'A kérdés képe nem lehet válasz is.'],
            empty($p['say']) => ['say' => 'Mit mondjon Csillám a jó válasz után?'],
            default => [],
        };
    }
}
