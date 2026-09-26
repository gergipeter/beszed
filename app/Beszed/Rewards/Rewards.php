<?php

namespace App\Beszed\Rewards;

use App\Models\BeszedBadge;
use App\Models\BeszedProfile;
use App\Models\BeszedSession;
use App\Models\Child;
use Illuminate\Support\Facades\DB;

/**
 * The gamification layer: player level, streak, daily goal, medals per game,
 * stickers and Csillám's wardrobe. Everything is derived from attempts and
 * finished sessions, except the stickers, which are kept once earned.
 */
class Rewards
{
    /** Full reward state of a child, as the client shows it. */
    public function summary(Child $child, ?Stats $stats = null): array
    {
        $cfg = config('beszed.rewards');
        $stats ??= Stats::for($child, $cfg['timezone']);
        $level = PlayerLevel::forStars($stats->stars, $cfg['level_step']);
        $earned = $child->beszedBadges()->pluck('earned_at', 'badge');

        return [
            'stars' => $stats->stars,
            'level' => $level,
            'streak' => ['days' => $stats->streak, 'today' => $stats->playedToday],
            'daily' => ['done' => $stats->today, 'goal' => $cfg['daily_goal']],
            'sessions' => $stats->sessions,
            'medals' => collect(config('beszed.games'))
                ->mapWithKeys(fn ($g, $id) => [$id => $this->medal($stats->gameBest[$id] ?? null)]),
            'badges' => collect($cfg['badges'])->map(fn ($b, $id) => [
                'id' => $id,
                'name' => $b['name'],
                'emoji' => $b['emoji'],
                'hint' => str_replace(':goal', (string) $cfg['daily_goal'], $b['hint']),
                'earned_at' => $earned[$id] ?? null,
            ])->values(),
            'accessory' => $child->beszedProfile?->accessory,
            'accessories' => $this->accessories($level['number']),
        ];
    }

    /**
     * Stores a finished game and awards what it earned.
     * Returns the new summary plus `result`: what changed with this game.
     */
    public function complete(Child $child, array $data): array
    {
        $cfg = config('beszed.rewards');

        return DB::transaction(function () use ($child, $data, $cfg) {
            $session = new BeszedSession(collect($data)->except('played_at')->all() + ['child_id' => $child->id]);
            if (isset($data['played_at'])) {
                $session->completed_at = $data['played_at'];
            }
            $session->save();

            $stats = Stats::for($child, $cfg['timezone']);
            $newBadges = $this->award($child, $stats);
            $summary = $this->summary($child, $stats);

            // Stars are saved per answer, so the level before this game = today's total minus its stars.
            $before = PlayerLevel::forStars(max(0, $stats->stars - $data['correct']), $cfg['level_step']);
            $after = $summary['level']['number'];

            return $summary + ['result' => [
                'stars' => $data['correct'],
                'medal' => $this->medal($data['first_try'] / $data['rounds']),
                'level_before' => $before,
                'level_up' => $after > $before['number'],
                'new_badges' => $newBadges,
                'unlocked' => collect($summary['accessories'])
                    ->filter(fn ($a) => $a['level'] > $before['number'] && $a['level'] <= $after)
                    ->values(),
            ]];
        });
    }

    /** Dresses Csillám; `null` takes the accessory off. Returns false if it isn't unlocked yet. */
    public function wear(Child $child, ?string $accessory): bool
    {
        if ($accessory !== null) {
            $level = PlayerLevel::forStars(
                Stats::for($child, config('beszed.rewards.timezone'))->stars,
                config('beszed.rewards.level_step'),
            );
            if ($level['number'] < config("beszed.rewards.accessories.$accessory.level")) {
                return false;
            }
        }

        BeszedProfile::updateOrCreate(['child_id' => $child->id], ['accessory' => $accessory]);
        $child->unsetRelation('beszedProfile');

        return true;
    }

    /** 0–3 medals for a first-try share (null = never played). */
    public function medal(?float $share): int
    {
        if ($share === null) {
            return 0;
        }
        $medal = 0;
        foreach (config('beszed.rewards.medals') as $count => $min) {
            if ($share + 1e-9 >= $min) {
                $medal = $count;
            }
        }

        return $medal;
    }

    /** Awards every sticker whose rule is met now; returns the new ones. */
    private function award(Child $child, Stats $stats): array
    {
        $cfg = config('beszed.rewards');
        $games = array_keys(config('beszed.games'));
        $have = $child->beszedBadges()->pluck('badge')->flip();
        $new = [];

        foreach ($cfg['badges'] as $id => $badge) {
            if ($have->has($id) || ! BadgeRules::passes($badge['rule'], $stats, $cfg['daily_goal'], $games)) {
                continue;
            }
            BeszedBadge::firstOrCreate(['child_id' => $child->id, 'badge' => $id]);
            $new[] = ['id' => $id, 'name' => $badge['name'], 'emoji' => $badge['emoji']];
        }

        return $new;
    }

    private function accessories(int $level): array
    {
        return collect(config('beszed.rewards.accessories'))->map(fn ($a, $id) => [
            'id' => $id,
            'name' => $a['name'],
            'emoji' => $a['emoji'],
            'level' => $a['level'],
            'unlocked' => $level >= $a['level'],
        ])->values()->all();
    }
}
