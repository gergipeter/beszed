<?php

namespace App\Beszed;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** What a parent's plan allows: every game, with the free plan stopping at the first levels. */
class Entitlements
{
    public function premium(User $user): bool
    {
        return in_array($user->subscription_plan, config('beszed_plans.premium_plans'), true)
            || Gate::forUser($user)->allows('edit-content'); // the people who run the content always see all of it
    }

    /** Highest level of a game this account plays; null = no limit. */
    public function levelCap(User $user, ?string $game = null): ?int
    {
        if ($this->premium($user)) {
            return null;
        }

        return ($game ? config("beszed_plans.free_max_level_by_game.$game") : null) ?? config('beszed_plans.free_max_level');
    }
}
