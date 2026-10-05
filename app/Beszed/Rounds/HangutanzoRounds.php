<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Ki mondja? (onomatopoeia). The adaptive level (1–100) maps onto the
 * content's own 1–3 level in three equal tiers (tier(), content tier 1
 * favoured by favorLevel()): tier 1: "Ki mondja, hogy brekeke?" → three
 * animal pictures; after the answer Csillám invites the child to say it too.
 * Tier 2: also the other way round — the picture, "Mit mond a kecske?", and
 * three sounds said aloud to pick from. Tier 3: things and people
 * (tik-tak, hapci, kukucs), both ways, four pictures.
 *
 * Within a tier, how often the "what says" direction comes up and the option
 * count creep up a little with the level (scale()), so a child isn't stuck
 * on a flat difficulty for the whole tier.
 *
 * Options never share a sound, and never pair two the content marks as
 * close (the horse and the donkey, laughter and Santa's ho-ho-ho).
 */
class HangutanzoRounds extends RoundFactory
{
    public const SPEAKER = '🔊';

    private const LISTEN = 'Koppints a hangszórókra, és hallgasd meg őket! A jó alatt nyomd meg a felfelé mutató ujjat!';

    private bool $explained = false;

    public function build(Collection $items, int $level, int $count): array
    {
        $this->explained = false;
        $this->favorLevel($items, $level);
        $tier = $this->tier($level, 3);
        // each tier plays its own items (animals, then rarer animals, then things and people) when there are enough
        $own = $items->filter(fn ($i) => (int) $i->level === $tier)->values();
        $pool = $own->count() >= 6 ? $own : $items->filter(fn ($i) => (int) $i->level <= $tier)->values();
        if ($pool->isEmpty()) {
            $pool = $items->values();
        }
        $rounds = [];
        // tier 1 never asks "what says it"; tiers 2-3 ask it every other round, as before
        $askBothWays = $tier >= 2;
        $nOptions = $tier >= 3 ? 4 : 3;

        foreach ($this->cycle($pool, $count)->values() as $r => $item) {
            $rounds[] = $askBothWays && $r % 2 === 1
                ? $this->whatSays($items, $item)
                : $this->whoSays($items, $item, $nOptions);
        }

        return $rounds;
    }

    /** "Ki mondja, hogy brekeke?" → the frog. */
    private function whoSays(Collection $items, $item, int $n): array
    {
        $p = $item->payload;
        $options = $this->others($items, $item, $n - 1)->push($item)->shuffle()->values();
        $ask = $p['kind'] === 'thing' ? "Mi szól úgy, hogy {$p['sound']}?" : "Ki mondja, hogy {$p['sound']}?";

        return $this->round('choice', $ask, [
            'layout' => $n === 4 ? 'four' : 'three',
            'options' => $options->map(fn ($o) => ['id' => (string) $o->id, 'emoji' => $o->payload['emoji']])->all(),
            'answer' => (string) $item->id,
            'onCorrect' => "Igen, {$this->who($p)}! Mondd te is: {$p['sound']}!",
            'onWrong' => $options->mapWithKeys(fn ($o) => [(string) $o->id => $this->says($o->payload)." $ask"])->all(),
        ], $item->id);
    }

    /** The picture → "Mit mond a kecske?": three sounds said aloud. */
    private function whatSays(Collection $items, $item): array
    {
        $p = $item->payload;
        $options = $this->others($items, $item, 2)->push($item)->shuffle()->values();
        $who = $this->who($p);
        $ask = match (true) {
            $p['kind'] === 'thing' => "Hogyan szól {$who}?",
            str_starts_with($who, 'aki') => "Mit mond, {$who}?",
            default => "Mit mond {$who}?",
        };

        return $this->round('choice', $this->explainOnce($ask), [
            'variant' => 'speakers',
            'layout' => 'three',
            'stimulus' => ['emoji' => $p['emoji']],
            'options' => $options->map(fn ($o) => [
                'id' => (string) $o->id, 'emoji' => self::SPEAKER, 'label' => $o->payload['sound'], 'say' => "{$this->ucfirst($o->payload['sound'])}!",
            ])->all(),
            'answer' => (string) $item->id,
            'onCorrect' => 'Igen! '.$this->says($p).' Mondd te is!',
            'onWrong' => $options->mapWithKeys(fn ($o) => [
                (string) $o->id => "{$this->ucfirst($o->payload['sound'])}? ".$this->ucfirst($this->owner($o->payload))." $ask",
            ])->all(),
        ], $item->id);
    }

    /** Others with a different sound, not close to this one or to each other; animals with animals when possible. */
    private function others(Collection $items, $item, int $n): Collection
    {
        $p = $item->payload;
        $clash = fn ($a, $b) => $a['sound'] === $b['sound'] || $a['emoji'] === $b['emoji']
            || in_array($a['name'], $b['close'] ?? [], true) || in_array($b['name'], $a['close'] ?? [], true);
        $ok = $items->reject(fn ($o) => $o->id === $item->id || $clash($o->payload, $p));
        $animal = fn ($i) => $i->payload['kind'] === 'animal';
        $same = $ok->filter(fn ($o) => $animal($o) === $animal($item));
        $picked = collect();
        foreach ([$same->shuffle(), $ok->shuffle()] as $from) {
            foreach ($from as $o) {
                if ($picked->count() === $n) {
                    break 2;
                }
                if (! $picked->contains(fn ($x) => $x->id === $o->id || $clash($x->payload, $o->payload))) {
                    $picked->push($o);
                }
            }
        }

        return $picked;
    }

    /** "a kecske", "az óra", "aki tüsszent". */
    private function who(array $p): string
    {
        return str_starts_with($p['name'], 'aki') ? $p['name'] : "{$this->art($p['name'])} {$p['name']}";
    }

    /** "A kecske azt mondja: mek-mek." · "Az óra így szól: tik-tak." · "Aki tüsszent, az azt mondja: hapci." */
    private function says(array $p): string
    {
        $who = $this->ucfirst($this->who($p));

        return match (true) {
            $p['kind'] === 'thing' => "$who így szól: {$p['sound']}.",
            str_starts_with($p['name'], 'aki') => "$who, az azt mondja: {$p['sound']}.",
            default => "$who azt mondja: {$p['sound']}.",
        };
    }

    /** Whose sound a wrong option is: "ezt a cica mondja." · "így az óra szól." · "ezt az mondja, aki nevet." */
    private function owner(array $p): string
    {
        return match (true) {
            $p['kind'] === 'thing' => "így {$this->who($p)} szól.",
            str_starts_with($p['name'], 'aki') => "ezt az mondja, {$p['name']}.",
            default => "ezt {$this->who($p)} mondja.",
        };
    }

    private function explainOnce(string $prompt): string
    {
        if ($this->explained) {
            return $prompt;
        }
        $this->explained = true;

        return self::LISTEN.' '.$prompt;
    }

    /**
     * Content rule: "aki …" names are people; the sound is not the name; nothing is close to itself.
     *
     * @return array<string, string>
     */
    public static function check(array $p): array
    {
        return match (true) {
            str_starts_with($p['name'], 'aki') && $p['kind'] !== 'person' => ['kind' => 'Az „aki …” név emberre illik.'],
            mb_strtolower($p['sound']) === mb_strtolower($p['name']) => ['sound' => 'A hang ne a neve legyen.'],
            in_array($p['name'], $p['close'] ?? [], true) => ['close' => 'Önmaga nem lehet a párja.'],
            default => [],
        };
    }
}
