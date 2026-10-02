<?php

namespace App\Beszed\Billing;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Asks RevenueCat (which has checked the App Store / Google Play receipt) whether a parent has the premium entitlement,
 * and stores the answer on the user. The app's own claim is never used: after a purchase or "Restore purchases" it only
 * tells us to look, and the webhook does the same when a subscription renews, lapses or is refunded.
 */
class SubscriptionSync
{
    /** The id the app gives RevenueCat for this parent (Purchases.logIn). */
    public static function appUserId(User $user): string
    {
        return (string) $user->id;
    }

    public function configured(): bool
    {
        return filled(config('billing.revenuecat.secret_key'));
    }

    /**
     * Brings users.subscription_plan up to date. Returns whether the parent is premium afterwards.
     *
     * @throws ConnectionException|RequestException when RevenueCat cannot be reached (the stored plan is left as it is)
     */
    public function sync(User $user): bool
    {
        $subscriber = Http::withToken(config('billing.revenuecat.secret_key'))
            ->acceptJson()
            ->timeout(10)
            ->get(rtrim(config('billing.revenuecat.api_url'), '/').'/subscribers/'.rawurlencode(self::appUserId($user)))
            ->throw()
            ->json('subscriber', []);

        $entitlement = $subscriber['entitlements'][config('billing.entitlement')] ?? null;
        $expires = isset($entitlement['expires_date']) ? Carbon::parse($entitlement['expires_date']) : null;
        // A lifetime / promotional entitlement has no end date; a subscription is active until its date passes.
        $active = $entitlement !== null && ($expires === null || $expires->isFuture());

        $source = $active ? ($subscriber['subscriptions'][$entitlement['product_identifier'] ?? '']['store'] ?? null) : null;

        $user->forceFill([
            'subscription_plan' => $active ? 'premium' : $this->planAfterLapse($user),
            'subscription_expires_at' => $active ? $expires : null,
            'subscription_source' => $active ? $source : null,
        ])->save();

        return $active;
    }

    /** A plan granted by hand (the review account, "family") is not ours to take away; only a paid "premium" lapses. */
    private function planAfterLapse(User $user): string
    {
        return $user->subscription_source !== null || $user->subscription_expires_at !== null ? 'free' : $user->subscription_plan;
    }
}
