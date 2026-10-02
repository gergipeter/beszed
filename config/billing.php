<?php

/*
| In-app subscriptions. The iOS / Android app sells the premium plan through Apple In-App Purchase and Google Play
| Billing, with RevenueCat in between (the app buys through the store; RevenueCat checks the receipt). This server never
| trusts the app's word: it asks RevenueCat's REST API whether the parent has the entitlement, and sets
| users.subscription_plan from the answer (App\Beszed\Billing\SubscriptionSync).
*/

return [
    // RevenueCat project → API keys. The SECRET key (sk_…) stays on the server; the app gets the public SDK keys below.
    'revenuecat' => [
        'secret_key' => env('REVENUECAT_SECRET_KEY'),
        'api_url' => env('REVENUECAT_API_URL', 'https://api.revenuecat.com/v1'),
        // Authorization header RevenueCat sends with every webhook call (set the same value in the RevenueCat dashboard).
        'webhook_secret' => env('REVENUECAT_WEBHOOK_SECRET'),
        // Public SDK keys the native app configures itself with (appl_… / goog_…); safe to expose.
        'public_key_ios' => env('REVENUECAT_PUBLIC_KEY_IOS'),
        'public_key_android' => env('REVENUECAT_PUBLIC_KEY_ANDROID'),
    ],

    // The RevenueCat entitlement that means "premium" (docs/pricing.md).
    'entitlement' => env('BILLING_ENTITLEMENT', 'premium'),

    // Store product ids (docs/pricing.md); the app shows the store's own localised price for them.
    'products' => [
        'monthly' => 'beszed.premium.monthly',
        'yearly' => 'beszed.premium.yearly',
    ],

    // Free trial days on the yearly product, shown on the paywall only when the store reports an introductory offer.
    'yearly_trial_days' => 7,
];
