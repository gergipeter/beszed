<?php

namespace App\Beszed\Rounds;

use Illuminate\Support\Collection;

/**
 * Following directions ("Csináld, amit mondok!"): Csillám says what to tap, the
 * child does it. Each content item is a picture with its group and its "-ra/-re"
 * form (kutyára). $level picks the kinds of direction:
 *   1 → one picture, or the one animal / fruit… among four
 *   2 → two pictures in order, or every picture of a group, among six
 *   3 → three in order, "before" (said in reverse), or everything that is NOT of a group
 * `steps` is what to tap, in order; the pictures inside one step in any order.
 */
class UtasitasRounds extends RoundFactory
{
    /** level → [pictures on the grid, kinds of direction (taken in turn)] */
    public const LEVELS = [
        1 => [4, ['one', 'group']],
        2 => [6, ['two', 'all']],
        3 => [6, ['three', 'before', 'not']],
    ];

    /** group key → [the group's word, its "-ra/-re" form, article] */
    private const GROUPS = [
        'allat' => ['állat', 'állatra', 'az'], 'gyumolcs' => ['gyümölcs', 'gyümölcsre', 'a'],
        'zoldseg' => ['zöldség', 'zöldségre', 'a'], 'etel' => ['étel', 'ételre', 'az'],
        'jarmu' => ['jármű', 'járműre', 'a'], 'ruha' => ['ruha', 'ruhára', 'a'],
        'jatek' => ['játék', 'játékra', 'a'], 'hangszer' => ['hangszer', 'hangszerre', 'a'],
        'virag' => ['virág', 'virágra', 'a'],
    ];

    private const PRAISE = [
        'Ügyes! Pontosan azt csináltad, amit mondtam!',
        'Szuper! Jól figyeltél!',
        'Ez az! Mindent jól csináltál!',
    ];

    public function build(Collection $items, int $level, int $count): array
    {
        [$size, $kinds] = self::LEVELS[$level] ?? self::LEVELS[1];
        $rounds = [];

        foreach ($this->cycle($items, $count)->values() as $r => $item) {
            $kind = $kinds[$r % count($kinds)];
            $rounds[] = match ($kind) {
                'one', 'two', 'three', 'before' => $this->sequence($items, $item, $size, $kind),
                'group', 'all', 'not' => $this->groupRound($items, $item, $size, $kind),
            };
        }

        return $rounds;
    }

    /** Named pictures in order: "Koppints először a kutyára, aztán az almára!" */
    private function sequence(Collection $items, object $first, int $size, string $kind): array
    {
        $n = ['one' => 1, 'two' => 2, 'three' => 3, 'before' => 2][$kind];
        $named = collect([$first])->merge($items->reject(fn ($i) => $i->id === $first->id)->shuffle()->take($n - 1))->values();
        $others = $items->reject(fn ($i) => $named->contains('id', $i->id))->shuffle()->take($size - $n);
        $to = fn ($i) => "{$this->art($i->payload['onto'])} {$i->payload['onto']}";
        [$a, $b, $c] = [$named[0], $named[1] ?? null, $named[2] ?? null];

        $prompt = match ($kind) {
            'one' => "Koppints {$to($a)}!",
            'two' => "Koppints először {$to($a)}, aztán {$to($b)}!",
            'three' => "Koppints {$to($a)}, aztán {$to($b)}, végül {$to($c)}!",
            // said the other way round: the child has to work out the order
            'before' => 'Mielőtt '.$to($b).' koppintasz, koppints '.$to($a).'!',
        };

        return $this->directions($prompt, $named->merge($others), $named->map(fn ($i) => [$i])->all(), $first->id);
    }

    /** A group: the only animal, every animal, or everything that isn't an animal. */
    private function groupRound(Collection $items, object $item, int $size, string $kind): array
    {
        $key = $item->payload['group'];
        [$word, $onto, $art] = self::GROUPS[$key] ?? [$key, $key, 'a'];
        $inGroup = $items->filter(fn ($i) => $i->payload['group'] === $key && $i->id !== $item->id)->shuffle();
        // the rest from other groups, never two of the same picture
        $outside = $items->reject(fn ($i) => $i->payload['group'] === $key)->shuffle();

        $members = match ($kind) {
            'group' => 1,
            'all' => random_int(2, 3),
            'not' => $size - random_int(2, 3), // the group fills the grid; 2–3 pictures to tap
        };
        $group = collect([$item])->merge($inGroup->take($members - 1));
        $rest = $outside->take($size - $group->count());

        [$prompt, $tap] = match ($kind) {
            'group' => ["Koppints $art $onto!", $group],
            'all' => ["Koppints az összes $onto!", $group],
            'not' => ["Koppints mindenre, ami nem $word!", $rest],
        };

        return $this->directions($prompt, $group->merge($rest), [$tap->values()->all()], $item->id);
    }

    /** @param  array<int, array<int, object>>  $steps */
    private function directions(string $prompt, Collection $pictures, array $steps, int $itemId): array
    {
        return $this->round('directions', $prompt, [
            'grid' => $pictures->shuffle()->values()->map(fn ($i) => [
                'id' => (string) $i->id, 'emoji' => $i->payload['emoji'], 'label' => $i->payload['name'],
            ])->all(),
            'steps' => array_map(fn ($step) => array_map(fn ($i) => (string) $i->id, $step), $steps),
            'wrong' => 'Hoppá, nem ez jött! Figyelj, elmondom még egyszer.',
            'onCorrect' => self::PRAISE[array_rand(self::PRAISE)],
        ], $itemId);
    }
}
