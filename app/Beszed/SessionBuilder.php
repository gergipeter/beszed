<?php

namespace App\Beszed;

use App\Models\BeszedAttempt;
use App\Models\BeszedContentItem;
use App\Models\Child;
use Illuminate\Support\Collection;

class SessionBuilder
{
    public function __construct(private Leveler $leveler) {}

    public function build(Child $child, string $game): array
    {
        $cfg = config("beszed.games.$game");
        abort_unless($cfg, 404);

        $items = BeszedContentItem::forGame($game)->get();
        abort_if($items->isEmpty(), 422, "No content for '$game'. Run: php artisan db:seed --class=BeszedContentSeeder");

        $level = $this->leveler->current($child, $game);
        $rounds = app($cfg['factory'])
            ->weigh($this->weights($child, $game, $items, isset($cfg['adaptive'])))
            ->build($items, $level, $cfg['rounds']);

        return [
            'game' => $game,
            'level' => $level,
            'intro' => $cfg['intro'],
            'no_idle' => (bool) ($cfg['no_idle'] ?? false),
            'stars' => $child->beszedAttempts()->where('correct', true)->count(),
            'rounds' => collect($rounds)->values()->map(fn ($r, $i) => ['key' => "$game-$i"] + $r)->all(),
        ];
    }

    /**
     * How likely each item is to come up. Missed once in its last three tries →
     * 5× as likely (roughly every other session), twice → 10×, three times → 20×
     * (almost surely next time); right at the first try the last two times → a
     * bit rarer. Games without an adaptive level also lean towards items that
     * fit the child's age.
     *
     * @return array<int, float>
     */
    private function weights(Child $child, string $game, Collection $items, bool $adaptive): array
    {
        $recent = BeszedAttempt::where('child_id', $child->id)->where('game', $game)
            ->whereNotNull('content_item_id')->where('created_at', '>=', now()->subDays(30))
            ->latest('id')->limit(500)->get(['content_item_id', 'correct', 'tries'])
            ->groupBy('content_item_id');
        $ageLevel = $adaptive ? null : (config('beszed_content.age_levels')[AgeBands::of($child)] ?? null);

        return $items->mapWithKeys(function ($item) use ($recent, $ageLevel) {
            $last = ($recent[$item->id] ?? collect())->take(3);
            $missed = $last->reject(fn ($a) => $a->correct && (int) $a->tries === 1)->count();
            $weight = match (true) {
                $missed > 0 => 5.0 * 2 ** ($missed - 1),
                $last->count() >= 2 => 0.6,
                default => 1.0,
            };
            if ($ageLevel) {
                $weight *= match ($item->level <=> $ageLevel) { 1 => 0.3, -1 => 0.7, 0 => 1.0 };
            }

            return [$item->id => $weight];
        })->all();
    }
}
