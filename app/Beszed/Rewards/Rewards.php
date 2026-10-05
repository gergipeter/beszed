<?php

namespace App\Beszed\Rewards;

use App\Beszed\DailyPath;
use App\Models\BeszedBadge;
use App\Models\BeszedProfile;
use App\Models\BeszedSession;
use App\Models\Child;
use App\Notifications\MilestoneEarned;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The gamification layer: player level, streak, daily goal, medals per game,
 * stickers and Csillám's wardrobe. Everything is derived from attempts and
 * finished sessions, except the stickers, which are kept once earned.
 */
class Rewards
{
    public function __construct(private DailyPath $paths) {}

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
            'streak' => ['days' => $stats->streak, 'today' => $stats->playedToday, 'recent' => $stats->recentDays],
            'daily' => ['done' => $stats->today, 'goal' => $cfg['daily_goal']],
            'week' => [
                'games' => $stats->weekGames,
                'goal' => (int) $cfg['weekly_goal'],
                'won' => $stats->weeksWon,
                // days left in this calendar week, today included (Monday = 7 … Sunday = 1)
                'days_left' => 8 - (int) now($cfg['timezone'])->isoWeekday(),
            ],
            'sessions' => $stats->sessions,
            // the game played most, by finished sessions (null until anything is played) — Csillám uses this to remember a favourite
            'favorite_game' => collect($stats->gameSessions)->sortDesc()->keys()->first(),
            // distinct days played, ever: only ever grows, never punishes a missed day (unlike the 7-day streak)
            'lifetime_days' => $stats->lifetimeDays,
            'medals' => collect(config('beszed.games'))
                ->mapWithKeys(fn ($g, $id) => [$id => $this->medal($stats->gameBest[$id] ?? null)]),
            'badges' => collect($cfg['badges'])->map(fn ($b, $id) => [
                'id' => $id,
                'name' => $b['name'],
                'emoji' => $b['emoji'],
                'hint' => str_replace(':goal', (string) $cfg['daily_goal'], $b['hint']),
                'earned_at' => $earned[$id] ?? null,
            ])->values(),
            'worn' => (object) ($child->beszedProfile?->accessories ?? []),
            'accessories' => $this->accessories($level['number']),
            'scene' => $child->beszedProfile?->scene ?? ['background' => null, 'stickers' => []],
            'backgrounds' => collect($cfg['backgrounds'])->map(fn ($b, $id) => ['id' => $id, 'name' => $b['name'], 'emoji' => $b['emoji'], 'colors' => $b['colors']])->values(),
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

            $path = $this->paths->markPlayed($child, $data['game'], CarbonImmutable::parse($session->completed_at ?? now()));
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
                // Today's "Mai kaland" if this game was one of its steps (null otherwise).
                'daily_path' => $path,
            ]];
        });
    }

    /**
     * Dresses Csillám: puts `$accessory` on in `$slot`, or clears the slot when null.
     * Other slots keep whatever they had. Returns false if the accessory isn't unlocked yet
     * or doesn't belong to that slot.
     */
    public function wear(Child $child, string $slot, ?string $accessory): bool
    {
        $worn = $child->beszedProfile?->accessories ?? [];

        if ($accessory !== null) {
            if (config("beszed.rewards.accessories.$accessory.slot") !== $slot) {
                return false;
            }

            $level = PlayerLevel::forStars(
                Stats::for($child, config('beszed.rewards.timezone'))->stars,
                config('beszed.rewards.level_step'),
            );
            if ($level['number'] < config("beszed.rewards.accessories.$accessory.level")) {
                return false;
            }

            $worn[$slot] = $accessory;
        } else {
            unset($worn[$slot]);
        }

        BeszedProfile::updateOrCreate(['child_id' => $child->id], ['accessories' => $worn]);
        $child->unsetRelation('beszedProfile');

        return true;
    }

    /**
     * Saves the sticker scene: a background and where each earned sticker sits on it.
     * Stickers the child doesn't actually have, or hasn't unlocked as a background, are dropped silently
     * (a stale local layout after a badge/background disappeared is not worth failing over).
     */
    public function saveScene(Child $child, ?string $background, array $stickers): array
    {
        $badges = config('beszed.rewards.badges');
        $earned = $child->beszedBadges()->pluck('badge')->flip();
        $backgrounds = config('beszed.rewards.backgrounds');
        $max = config('beszed.rewards.scene_max_stickers');

        if ($background !== null && ! isset($backgrounds[$background])) {
            $background = null;
        }

        $placed = collect($stickers)
            ->filter(fn ($s) => isset($badges[$s['badge']]) && $earned->has($s['badge']))
            ->take($max)
            ->map(fn ($s) => [
                'badge' => $s['badge'],
                'x' => max(0, min(100, (float) $s['x'])),
                'y' => max(0, min(100, (float) $s['y'])),
                'rotate' => max(-180, min(180, (float) ($s['rotate'] ?? 0))),
                'scale' => max(0.5, min(2.5, (float) ($s['scale'] ?? 1))),
            ])
            ->values()
            ->all();

        $scene = ['background' => $background, 'stickers' => $placed];
        BeszedProfile::updateOrCreate(['child_id' => $child->id], ['scene' => $scene]);
        $child->unsetRelation('beszedProfile');

        return $scene;
    }

    /**
     * A parent hands over a sticker by hand, from the parents' menu: never ruled out by play,
     * never repeatable (giving it twice changes nothing). Returns the new summary.
     */
    public function giftBadge(Child $child, string $badge): array
    {
        BeszedBadge::firstOrCreate(['child_id' => $child->id, 'badge' => $badge]);

        return $this->summary($child);
    }

    /** 1–3 medals for a first-try share: every finished game earns at least one (null = never played: 0). */
    public function medal(?float $share): int
    {
        if ($share === null) {
            return 0;
        }
        $medal = 1;
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
            $entry = ['id' => $id, 'name' => $badge['name'], 'emoji' => $badge['emoji']];
            $new[] = $entry;
            if ($badge['email'] ?? false) {
                $this->mailMilestone($child, $entry);
            }
        }

        return $new;
    }

    /** Mails the parent, unless they turned it off or have no address to reach. */
    private function mailMilestone(Child $child, array $badge): void
    {
        $user = $child->user;
        if ($user?->milestone_emails_enabled && $user->email) {
            $user->notify(new MilestoneEarned($child, $badge));
        }
    }

    private function accessories(int $level): array
    {
        return collect(config('beszed.rewards.accessories'))->map(fn ($a, $id) => [
            'id' => $id,
            'name' => $a['name'],
            'emoji' => $a['emoji'],
            'level' => $a['level'],
            'slot' => $a['slot'],
            'unlocked' => $level >= $a['level'],
        ])->values()->all();
    }
}
