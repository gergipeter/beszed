<?php

namespace App\Beszed;

use App\Models\BeszedContentItem;
use App\Models\Child;

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
        $rounds = app($cfg['factory'])->build($items, $level, $cfg['rounds']);

        return [
            'game' => $game,
            'level' => $level,
            'intro' => $cfg['intro'],
            'no_idle' => (bool) ($cfg['no_idle'] ?? false),
            'stars' => $child->beszedAttempts()->where('correct', true)->count(),
            'rounds' => collect($rounds)->values()->map(fn ($r, $i) => ['key' => "$game-$i"] + $r)->all(),
        ];
    }
}
