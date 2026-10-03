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
        $cap = $this->leveler->cap($child, $game);
        $rounds = app($cfg['factory'])
            ->weigh($this->weights($child, $game, $items, isset($cfg['adaptive']), $cfg['rounds']))
            ->choose($options + ['level_cap' => $cap])
            ->build($items, $level, $cfg['rounds']);
        // now and then Csillám has a go first, sometimes wrongly on purpose, and the child judges her
        if ($cfg['guess'] ?? true) {
            $rounds = CsillamGuess::apply($rounds, config('beszed.guesses', []));
        }

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
     * A game can opt out of the practise-again rule (`review` => false: a puzzle graded 2 or 3 is not a wrong answer) and ask
     * for fresh pictures (`fresh` => N: a picture among the last N played comes up only when nothing else is left).
     *
     * @return array<int, float>
     */
    private function weights(Child $child, string $game, Collection $items, bool $adaptive, int $rounds): array
    {
        $recent = BeszedAttempt::where('child_id', $child->id)->where('game', $game)
            ->whereNotNull('content_item_id')->where('created_at', '>=', now()->subDays(90))
            ->latest('id')->limit(2000)->get(['content_item_id', 'correct', 'tries', 'created_at'])
            ->groupBy('content_item_id');
        $ageLevel = $adaptive ? null : (config('beszed_content.age_levels')[AgeBands::of($child)] ?? null);
        $review = (bool) config("beszed.games.$game.review", true);

        $missed = [];
        $weights = $items->mapWithKeys(function ($item) use ($recent, $ageLevel, $review, &$missed) {
            $last = ($recent[$item->id] ?? collect())->take(3);
            $misses = $review ? $last->reject(fn ($a) => $a->correct && (int) $a->tries === 1)->count() : 0;
            if ($misses) {
                $missed[$item->id] = $misses;
            }
            $weight = $misses === 0 ? $this->spacing($recent[$item->id] ?? collect()) : 1.0;
            if ($ageLevel) {
                $weight *= match ($item->level <=> $ageLevel) { 1 => 0.3, -1 => 0.7, 0 => 1.0 };
            }

            return [$item->id => $weight];
        })->all();

        $this->favorFresh($child, $game, $items, $weights);

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

    /** Days to leave an item alone after it was right first time this many times in a row (spaced repetition). */
    private const INTERVAL_DAYS = [0, 1, 2, 4, 8, 16, 32];

    /**
     * Spaced repetition: an item the child keeps getting right at the first try waits longer and longer (1, 2, 4, 8, 16, 32 days)
     * before it comes back; once that time is up it is a little more likely than a new item, before it, much less likely.
     * New items, and items with no clean streak, keep the normal chance.
     *
     * @param  Collection<int, BeszedAttempt>  $attempts  newest first
     */
    private function spacing(Collection $attempts): float
    {
        $streak = 0;
        foreach ($attempts as $a) {
            if (! ($a->correct && (int) $a->tries === 1)) {
                break;
            }
            $streak++;
        }
        if ($streak === 0) {
            return 1.0;
        }
        $wait = self::INTERVAL_DAYS[min($streak, count(self::INTERVAL_DAYS) - 1)];

        return $attempts->first()->created_at->diffInDays(now(), false) >= $wait ? 1.6 : 0.35;
    }

    /** Pictures among the child's last `fresh` plays of this game almost never come up again (several items can share a picture). */
    private function favorFresh(Child $child, string $game, Collection $items, array &$weights): void
    {
        $window = (int) config("beszed.games.$game.fresh", 0);
        if ($window <= 0) {
            return;
        }

        $ids = BeszedAttempt::where('child_id', $child->id)->where('game', $game)->whereNotNull('content_item_id')
            ->latest('id')->limit($window)->pluck('content_item_id')->unique();
        $seen = BeszedContentItem::whereIn('id', $ids)->get(['id', 'payload'])
            ->map(fn ($i) => $i->payload['emoji'] ?? null)->filter()->flip();

        foreach ($items as $item) {
            if (isset($seen[$item->payload['emoji'] ?? ''])) {
                $weights[$item->id] *= 0.02;
            }
        }
    }
}
