<?php

namespace App\Http\Controllers\Api;

use App\Beszed\Billing\SubscriptionSync;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * RevenueCat tells us a subscription started, renewed, was cancelled, refunded or expired. The body is only a prompt:
 * the state is read again from RevenueCat's API (SubscriptionSync), so a replayed or reordered message cannot change it.
 * Authenticated by the Authorization header set in the RevenueCat dashboard (BILLING webhook secret).
 */
class RevenueCatWebhookController extends Controller
{
    public function __invoke(Request $request, SubscriptionSync $sync): JsonResponse
    {
        $secret = config('billing.revenuecat.webhook_secret');
        $given = (string) $request->bearerToken() ?: (string) $request->header('Authorization');

        if (blank($secret) || ! hash_equals($secret, $given) && ! hash_equals('Bearer '.$secret, $given)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $event = $request->input('event', []);
        // Every id the event names: the one that bought it, and (after an alias merge) the others.
        $ids = collect([$event['app_user_id'] ?? null, $event['original_app_user_id'] ?? null])
            ->merge($event['aliases'] ?? [])
            ->filter(fn ($id) => is_string($id) && ctype_digit($id))
            ->unique();

        foreach (User::whereIn('id', $ids)->get() as $user) {
            try {
                $sync->sync($user);
            } catch (ConnectionException|RequestException $e) {
                Log::warning('RevenueCat webhook sync failed', ['user' => $user->id, 'error' => $e->getMessage()]);

                return response()->json(['message' => 'Try again.'], 503); // RevenueCat retries a non-2xx answer
            }
        }

        return response()->json(['ok' => true]);
    }
}
