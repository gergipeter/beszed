<?php

namespace App\Http\Controllers\Api;

use App\Beszed\Billing\SubscriptionSync;
use App\Beszed\Entitlements;
use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** What the native app needs to sell the premium plan, and the "look again" call it makes after a purchase or a restore. */
class BillingController extends Controller
{
    /** The RevenueCat setup for the signed-in parent: the app calls Purchases.configure / logIn with these. */
    public function show(Request $request, SubscriptionSync $sync, Entitlements $plans): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $sync->configured(),
            'app_user_id' => SubscriptionSync::appUserId($user),
            'keys' => ['ios' => config('billing.revenuecat.public_key_ios'), 'android' => config('billing.revenuecat.public_key_android')],
            'products' => config('billing.products'),
            'trial_days' => config('billing.yearly_trial_days'),
            'premium' => $plans->premium($user),
        ]);
    }

    /** After a purchase or "Restore purchases": ask RevenueCat what the parent has and store it. */
    public function sync(Request $request, SubscriptionSync $sync, Entitlements $plans): JsonResponse
    {
        if (! $sync->configured()) {
            return response()->json(['message' => 'Billing is not set up on this server.'], 503);
        }

        try {
            $sync->sync($request->user());
        } catch (ConnectionException|RequestException $e) {
            Log::warning('RevenueCat sync failed', ['user' => $request->user()->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'The subscription could not be checked right now. Try again in a moment.'], 502);
        }

        return response()->json(['premium' => $plans->premium($request->user()->fresh())]);
    }
}
