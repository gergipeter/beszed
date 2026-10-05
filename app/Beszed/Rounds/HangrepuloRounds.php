<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Hangrepülő: the child's voice flies a rocket, a bee or a balloon (engine `voice`). Csillám models a sound
 * to hold ("ááá", "sssz, mint a kígyó") and the microphone hears whether it is on, how loud and how high.
 * The adaptive level (1–100) splits into three equal tiers (tier()): tier 1 (`sustain`, pauses allowed):
 * the hold time grows smoothly from 2 s up towards 4 s · tier 2 (`sustain`, continuous): the hold time
 * keeps growing across the tier, now without stopping — the flyer falls back when the voice stops ·
 * tier 3 (`pitch`): high voice up, low voice down, to collect the stars (relative to the child's own
 * voice; loudness stands in when no pitch is found) — the star count grows from 2 to 3 within the tier.
 */
class HangrepuloRounds extends RoundFactory
{
    /** Old tier → [mode, ms to hold, without stopping?]; kept for reference/tests. */
    public const LEVELS = [1 => ['sustain', 2000, false], 2 => ['sustain', 4000, true], 3 => ['pitch', 0, false]];

    /** What flies and how Csillám names it (the client's FlyScene draws each with its goal: keep the ids in sync). */
    public const FLYERS = [
        'rocket' => ['emoji' => '🚀', 'goal' => '🌙', 'trail' => 'flame', 'name' => 'a rakéta', 'up' => 'felszáll', 'done' => 'Felért a rakéta a Holdig!'],
        'bee' => ['emoji' => '🐝', 'goal' => '🌻', 'trail' => 'wings', 'name' => 'a méhecske', 'up' => 'repül', 'done' => 'Odaért a méhecske a virághoz!'],
        'balloon' => ['emoji' => '🎈', 'goal' => '☁️', 'trail' => 'none', 'name' => 'a léggömb', 'up' => 'felszáll', 'done' => 'Felszállt a léggömb a felhőkig!'],
    ];

    /** Sounds with a voice in them (a pitch to follow); sz, s and f are only breath. */
    public const VOICED = ['a', 'á', 'e', 'é', 'i', 'í', 'o', 'ó', 'ö', 'ő', 'u', 'ú', 'ü', 'ű', 'm', 'n', 'l', 'z', 'r', 'v'];

    private const PRAISE = ['Szép hosszú hang volt!', 'Ügyes vagy, végig szólt a hangod!', 'Nagyon szépen kitartottad!'];

    /** Is `shown` the sound held long, written as it sounds? ("ááá" for á, "sssz" for sz, at least three letters) */
    public static function modelFits(string $sound, string $shown): bool
    {
        $sound = mb_strtolower($sound);
        $model = mb_strtolower(trim($shown));
        if (mb_strlen($model) < 3) {
            return false;
        }
        if (mb_strlen($sound) === 2) { // sz, zs…: the first letter held, the second closes it
            [$a, $b] = mb_str_split($sound);

            return (bool) preg_match('/^'.preg_quote($a, '/').'{2,}'.preg_quote($b, '/').'$/u', $model);
        }

        return (bool) preg_match('/^(?:'.preg_quote($sound, '/').'){3,}$/u', $model);
    }

    /**
     * Can the voice say `model`? Every word needs a vowel (the voice spells "sss" out as "es es es"), and
     * no "…" (read as "pont pont pont"). A vowel's model says the vowel itself ("Mondd hosszan: ááá").
     */
    public static function speakable(string $sound, string $shown, string $model): bool
    {
        $words = preg_split('/[\s,.!?:;]+/u', trim($model), -1, PREG_SPLIT_NO_EMPTY);
        $allVowelled = $words && collect($words)->every(fn ($w) => preg_match('/[aáeéiíoóöőuúüű]/iu', $w));
        $vowel = in_array(mb_strtolower($sound), \App\Beszed\Content\Hungarian::VOWELS, true);

        return $allVowelled && ! str_contains($model, '…') && (! $vowel || str_contains(mb_strtolower($model), mb_strtolower($shown)));
    }

    public function build(Collection $items, int $level, int $count): array
    {
        $tier = $this->tier($level, 3);
        $mode = $tier >= 3 ? 'pitch' : 'sustain';
        $continuous = $tier >= 2;
        // 2 s at the start of tier 1 growing to 4 s by the end of tier 2; tier 3 (pitch) doesn't use ms
        $ms = $this->scaleInt($level, 2000, 4000, 66);
        $kind = $mode === 'pitch' ? 'pitch' : 'sustain';
        $pool = $items->filter(fn ($i) => ($i->payload['kind'] ?? 'sustain') === $kind)->values();
        if ($pool->isEmpty()) {
            $pool = $items->values();
        }
        $this->favorLevel($pool, $level);
        // within tier 3, the default star count grows from 2 to 3 (the first round of a session always starts at 2)
        $tierSpan = 100 / 3;
        $intoTier = $level - ($tier - 1) * $tierSpan;
        $defaultStars = $intoTier > $tierSpan / 2 ? 3 : 2;

        $rounds = [];
        $highFirst = (bool) random_int(0, 1);
        foreach ($this->cycle($pool, $count)->values() as $r => $item) {
            $p = $item->payload;
            $flyer = self::FLYERS[$p['flyer'] ?? 'rocket'] ?? self::FLYERS['rocket'];
            $model = $p['model'];
            $ask = "$model, {$p['helper']}!";
            $data = [
                'mode' => $mode,
                'sound' => $p['sound'],
                'shown' => $p['shown'],
                'helper' => $p['helper'],
                'emoji' => $p['emoji'],
                'sayModel' => "$model, {$p['helper']}.",
                // the id, not the emoji: the client draws it (a session would swap a bare emoji for a pictogram)
                'flyer' => array_key_exists($p['flyer'] ?? '', self::FLYERS) ? $p['flyer'] : 'rocket',
                'hints' => ['quiet' => "Hangosabban! $ask"],
            ];

            if ($mode === 'sustain') {
                $data['target'] = ['ms' => $ms, 'continuous' => $continuous];
                if ($continuous) {
                    $data['hints']['fell'] = 'Hoppá, elhallgattál! Kezdd újra, és most egyfolytában!';
                    $say = ['Most egyfolytában, ne hagyd abba!', $ask];
                    if ($r === 0) {
                        $say[] = 'Ha elhallgatsz, '.$flyer['name'].' visszaesik.';
                    }
                } else {
                    $say = [$ask];
                    if ($r === 0) {
                        $say[] = 'Amíg szól a hangod, '.$flyer['name'].' '.$flyer['up'].'!';
                    }
                }
                $data['onCorrect'] = $flyer['done'].' '.$this->pickNot(self::PRAISE, null);
            } else {
                // two stars in the first round regardless, then the level's default count; high and low in turn
                $n = $r === 0 ? 2 : $defaultStars;
                $xs = $n === 2 ? [0.42, 0.78] : [0.34, 0.6, 0.86];
                $data['stars'] = collect($xs)->map(fn ($x, $k) => ['x' => $x, 'high' => ($k % 2 === 0) === ($highFirst xor $r % 2 === 1)])->all();
                $data['hints'] += ['high' => 'Most magasabban!', 'low' => 'Most mélyebben!'];
                $say = [$ask];
                $say[] = $r === 0
                    ? 'Magas hangon '.$flyer['name'].' felszáll, mély hangon leereszkedik. Szedd össze a csillagokat!'
                    : 'Szedd össze a csillagokat!';
                $data['onCorrect'] = 'Összeszedted mind a '.self::NUM[count($xs)].' csillagot! Ügyes vagy!';
            }

            $rounds[] = $this->round('voice', implode(' ', $say), $data, $item->id, $say);
        }

        return $rounds;
    }
}
