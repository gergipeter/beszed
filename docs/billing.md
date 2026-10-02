# In-app premium subscription: how it works and what is left

Premium is sold **only inside the iOS / Android app**, with Apple In-App Purchase and Google Play Billing, through
[RevenueCat](https://www.revenuecat.com). No HiPay, no Stripe, no outside checkout link anywhere in the app (App Store
3.1.1). Prices and products: [pricing.md](pricing.md).

## How a purchase flows

1. The parent opens the parents' menu (parental gate) → **Prémium csomag** (`PremiumPage.vue`).
2. The page asks the server for the RevenueCat setup (`GET /api/billing`), starts RevenueCat with the account id, and shows
   the store's own price for the monthly and yearly products.
3. **Subscribe** and **Restore purchases** each ask for the parental gate first, then open the store's purchase sheet.
4. The app tells the server to look (`POST /api/billing/sync`). The **server** asks RevenueCat's REST API whether the
   parent has the `premium` entitlement and sets `users.subscription_plan`, `subscription_expires_at` and
   `subscription_source` (`App\Beszed\Billing\SubscriptionSync`). The app's own claim is never used.
5. RevenueCat also calls `POST /api/webhooks/revenuecat` on renewals, cancellations, refunds and expiry. The webhook body is
   only a prompt: the server re-reads the state from RevenueCat, so replayed or reordered messages are harmless.
6. `Entitlements::premium()` stops counting a paid plan once `subscription_expires_at` has passed, even if a webhook is lost.
   Plans given by hand (the review account, `family`) have no end date and are never taken away by a sync.

In a plain browser there is no store: the page describes the plan and says it is available in the app.

## App Review rules this covers

| Rule | Where |
|---|---|
| Purchase behind a parental gate (1.3) | `PremiumPage.vue`: every subscribe / restore calls `ParentGate` first |
| Restore purchases button (3.1.1) | `PremiumPage.vue` → `store.restore()` |
| Price, period, auto-renewal, how to cancel, trial length (3.1.2) | the terms block under the plans; price text comes from the store |
| Terms of Use + Privacy links (3.1.2(c)) | links under the plans, in-app pages (`/felhasznalasi-feltetelek`, `/adatvedelem`) |
| No outside payment mentioned or linked (3.1.1) | none in the app; Stripe/HiPay are not used |
| Premium testable by the reviewer (2.1) | `php artisan beszed:review-account` (premium without a purchase); also a sandbox purchase works |

## What still has to be done outside this repo

The native shell now exists (`android/`, `ios/`, see [native-app.md](native-app.md)) but has never run on a device, so the buying itself is untested until these are done:

1. **Accounts:** Apple Developer Program (company), Google Play Console, a RevenueCat project.
2. **Store products:** subscription group "Premium" with `beszed.premium.monthly` and `beszed.premium.yearly` in App Store
   Connect and Play Console (the 7-day free trial on the yearly product; set HUF prices by hand). Sign the Paid Applications agreement.
3. **RevenueCat:** add the iOS and Android apps, import the products, create the entitlement `premium` that contains both,
   an offering "default" with both packages, and a webhook to `<APP_URL>/api/webhooks/revenuecat` with the Authorization header set to `REVENUECAT_WEBHOOK_SECRET`.
4. **.env on the server:** `REVENUECAT_SECRET_KEY`, `REVENUECAT_WEBHOOK_SECRET`, `REVENUECAT_PUBLIC_KEY_IOS`, `REVENUECAT_PUBLIC_KEY_ANDROID`
   (see `.env.example`). `php artisan beszed:preflight` blocks until the first two are set.
5. **Native shell:** done as a project, not yet run: a Capacitor 8 project that bundles the built web app (`npm run build:native`,
   `npm run cap:sync`) with `@revenuecat/purchases-capacitor` 13.7. The web code finds the plugin as `Capacitor.registerPlugin('Purchases')`
   (`resources/native/main.js` loads `@capacitor/core` so `registerPlugin` exists), and its method names and data shapes were checked against the
   installed plugin (`configure`, `getOfferings`, `purchasePackage`, `restorePurchases`): no change was needed. Still to do: the In-App Purchase
   capability in Xcode (iOS; the project has none yet), the plugin's Android billing permission is merged by the SDK at build time, the real bundle id
   (placeholder `hu.beszed.app`), and signing.
6. **Test with sandbox accounts** (Apple sandbox tester, Google licence testers): buy, cancel, let it expire (sandbox renewals are minutes), restore on a second device.
