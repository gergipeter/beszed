<?php

namespace App\Beszed;

use App\Beszed\Content\Pictures;
use App\Models\BeszedAttempt;
use App\Models\BeszedContentItem;
use App\Models\Child;
use Illuminate\Support\Collection;

class SessionBuilder
{
    public function __construct(private Leveler $leveler) {}

    /** @param  array{category?: ?string}  $options  what the child chose before playing (Kirakó's picture theme) */
    public function build(Child $child, string $game, array $options = [], ?int $pickedLevel = null): array
    {
        $cfg = config("beszed.games.$game");
        abort_unless($cfg, 404);

        $items = BeszedContentItem::forGame($game)->limit(1000)->get();
        // Without ARASAAC (config beszed_content.pictograms = false) an item is only played when every pictogram
        // in it has a substitute (a Mulberry symbol or an emoji); the rest is left out.
        if (! config('beszed_content.pictograms')) {
            $items = $items->filter(fn ($item) => Pictures::hasSubstitutes($item->arasaacIds()))->values();
        }
        abort_if($items->isEmpty(), 422, "No content for '$game'. Run: php artisan db:seed --class=BeszedContentSeeder");

        $level = $pickedLevel !== null ? $this->leveler->set($child, $game, $pickedLevel) : $this->leveler->current($child, $game);
        $rounds = app($cfg['factory'])
            ->weigh($this->weights($child, $game, $items, isset($cfg['adaptive']), $cfg['rounds']))
            ->choose($options)
            ->build($items, $level, $cfg['rounds']);
        // now and then Csillám has a go first, sometimes wrongly on purpose, and the child judges her
        if ($cfg['guess'] ?? true) {
            $rounds = CsillamGuess::apply($rounds, config('beszed.guesses', []));
        }

        $cap = $this->leveler->cap($child);

        return [
            'game' => $game,
            'level' => $level,
            // the free plan's top level when the game has more levels behind it (null = nothing is held back)
            'level_cap' => $cap !== null && ($cfg['adaptive']['max'] ?? 0) > $cap ? $cap : null,
            'intro' => $cfg['intro'],
            // Csillám introduces a game only the first time; after that the first question comes right away.
            'first_time' => ! $child->beszedAttempts()->where('game', $game)->exists(),
            'no_idle' => (bool) ($cfg['no_idle'] ?? false),
            'stars' => $child->beszedAttempts()->where('correct', true)->count(),
            'rounds' => collect(Pictures::apply($rounds))->values()->map(fn ($r, $i) => ['key' => "$game-$i"] + $r)->all(),
        ];
    }

    /** Chance that an item missed 1, 2 or 3 times in its last three tries is practised this session. */
    private const REVIEW_CHANCE = [1 => 0.5, 2 => 0.75, 3 => 0.95];

    /** Weight that puts an item picked for practice at the front of the session. */
    private const REVIEW_WEIGHT = 1_000_000.0;

    /**
     * How likely each item is to come up. Missed items are practised again
     * whatever the size of the game's pool: missed once in its last three tries
     * → about every other session, twice → three sessions in four, three times
     * → almost always; at most a third of a session is such practice. Items
     * right at the first try the last two times come up a bit less. Games
     * without an adaptive level also lean towards items that fit the child's age.
     *
     * @return array<int, float>
     */
    private function weights(Child $child, string $game, Collection $items, bool $adaptive, int $rounds): array
    {
        $recent = BeszedAttempt::where('child_id', $child->id)->where('game', $game)
            ->whereNotNull('content_item_id')->where('created_at', '>=', now()->subDays(30))
            ->latest('id')->limit(500)->get(['content_item_id', 'correct', 'tries'])
            ->groupBy('content_item_id');
        $ageLevel = $adaptive ? null : (config('beszed_content.age_levels')[AgeBands::of($child)] ?? null);

        $missed = [];
        $weights = $items->mapWithKeys(function ($item) use ($recent, $ageLevel, &$missed) {
            $last = ($recent[$item->id] ?? collect())->take(3);
            $misses = $last->reject(fn ($a) => $a->correct && (int) $a->tries === 1)->count();
            if ($misses) {
                $missed[$item->id] = $misses;
            }
            $weight = $misses === 0 && $last->count() >= 2 ? 0.6 : 1.0;
            if ($ageLevel) {
                $weight *= match ($item->level <=> $ageLevel) { 1 => 0.3, -1 => 0.7, 0 => 1.0 };
            }

            return [$item->id => $weight];
        })->all();

        // The most-missed first; each gets its chance until a third of the rounds is practice.
        arsort($missed);
        $slots = max(1, intdiv($rounds, 3));
        foreach ($missed as $id => $misses) {
            if ($slots === 0) {
                break;
            }
            if (mt_rand() / mt_getrandmax() < self::REVIEW_CHANCE[$misses]) {
                $weights[$id] = self::REVIEW_WEIGHT;
                $slots--;
            }
        }

        return $weights;
    }
}
