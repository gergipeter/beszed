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

    /** Highest game level this account plays; null = no limit. */
    public function levelCap(User $user): ?int
    {
        return $this->premium($user) ? null : config('beszed_plans.free_max_level');
    }
}
