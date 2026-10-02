# iOS App Store compliance check

Checked 2026-10-01 against the current [App Review Guidelines](https://developer.apple.com/app-store/review/guidelines/)
(text read the same day) and against what the app does today. It is a pre-submission audit, not legal advice:
Apple's reviewers decide, and some points below are judgement calls. Legend: ✅ fine · ⚠️ needs work or a decision · ❌ would be rejected as it is.

## Verdict

**Not ready to submit.** Nothing here is a dead end, but there are seven blockers (❌). The app's privacy basics are
good (no ads, no trackers, no analytics, in-app account deletion, data export), which is the hardest part to retrofit.

## Progress (updated the same day)

| # | Blocker | Status |
|---|---|---|
| 1 | Google-only sign-in | **Done** for the web part and the app: e-mail + password sign-up, sign-in, forgot / reset password (with brute-force limit), tested. The Capacitor shell signs in with a Bearer token and shows only this sign-in (no Google button, no demo), see [native-app.md](native-app.md). Still to do: run `php artisan beszed:review-account` on the live server for the review notes, and try the sign-in on a real device. |
| 2 | Purchases must use Apple in-app purchase | **Built** (web side + server): paywall behind the parental gate, restore button, store prices and renewal terms, RevenueCat sync + webhook, tested. The native shell now exists (Capacitor project with `@revenuecat/purchases-capacitor`, [native-app.md](native-app.md)). Still to do: store accounts and products, the In-App Purchase capability in Xcode, and a sandbox purchase on a device. See [billing.md](billing.md). |
| 3 | Terms of Use and subscription screen | **Terms of Use written (draft)** at `/felhasznalasi-feltetelek`, with the subscription / cancellation wording, linked from sign-up and consent. Needs a lawyer's read and the operator details. The paywall screen itself comes with the billing build. |
| 4 | Parental gate | **Done**: the parents' menu opens only after reading a three-digit number written in words and typing it (3 wrong tries lock it for 30 s). The same component is to guard purchases and links out. |
| 5 | Privacy notice blanks | **Placeholders added** (`[Adatkezelő neve és címe – kitöltendő]`); `php artisan beszed:preflight` fails while they are there. The real publisher details and a public URL are still needed. |
| 6 | Child's voice to third parties | **Mostly done** (another session): the OpenAI speech route is switched off, and a self-hosted Whisper service keeps the child's voice on our server; `beszed:preflight` blocks `STT_DRIVER=azure`. |
| 7 | ARASAAC licence | Open: permission request, or `BESZED_PICTOGRAMS=false` (preflight blocks until one of them). |

New helpers: `php artisan beszed:preflight` (the settings above in one table, exit code 1 on a blocker) and
`php artisan beszed:review-account` (the premium, consented account for Apple's reviewer). Premium pricing is decided in
[pricing.md](pricing.md).

## The first decision: Kids Category or not

The app is for 4–5-year-olds, so the **Kids Category** (guideline 1.3) fits and helps discovery. It also sets stricter rules:

- no links out and no purchase buttons, except behind a **parental gate**
- no third-party analytics or ads, and **no personal or device information sent to third parties**
- 2.3.8: wording such as "for kids" in the name, subtitle, icon, screenshots or description is *reserved* for the Kids Category

If you do not pick the Kids Category you may not describe the app as for children at all (2.3.8), and 5.1.4 still applies
("apps intended primarily for kids should not include third-party analytics or advertising"). The practical difference
is small for this app; **recommendation: Kids Category**, with the changes below. Once chosen, the rules keep applying
even if you later deselect it.

## Blockers (❌)

| # | Problem | Guideline | What to do |
|---|---|---|---|
| 1 | **Sign-in is Google only (plus a local demo).** Offering Google as the way to make the account requires an equivalent privacy-preserving option, e.g. Sign in with Apple. Google also blocks sign-in inside an embedded web view, so it would not work in the app at all. A reviewer also cannot use a Google login. | 4.8, 2.1(a) | In the iOS build, use **your own email sign-in** (password or emailed code) and drop Google there. Apps that use *only* their own sign-in are exempt from 4.8. Create a review account for Apple's notes. |
| 2 | **The premium unlock must use Apple in-app purchase.** A web checkout link for the same thing is not allowed inside the app. | 3.1.1 | Sell through StoreKit (RevenueCat is fine). Do not show web checkout links or prices in the iOS app. |
| 3 | **No Terms of Use (EULA) and no subscription information screen.** The purchase screen must say what you get, the price, the period, that it auto-renews and how to cancel, with links to the Terms and the Privacy Policy, and needs a *Restore purchases* button. | 3.1.2(c), 3.1.1 | Write the Terms of Use; build the paywall to Apple's list (see below). |
| 4 | **Purchases and links have no proper parental gate.** The menu opens on a ~1 second press-and-hold. That keeps toddlers out, but reviewers expect a gate that needs an adult skill (a short sum or reading a number). | 1.3 | Put purchases, links out and the parents' menu behind a sum / read-the-number gate. |
| 5 | **The privacy notice has empty required fields.** "Controller" and "contact" come from `PRIVACY_CONTROLLER` / `PRIVACY_CONTACT`, which are not set, so the page shows placeholders. The policy also needs a public URL for App Store Connect. | 5.1.1(i), 2.1(a) | Set both values; host the privacy policy on a permanent public URL (see the deploy plan). |
| 6 | **Child voice can reach third parties.** With the Azure voice on, the "Mondd utánam" pronunciation check sends the child's recorded voice to Microsoft; the production default (`TTS_DRIVER=piper`) keeps text on your server but pronunciation scoring still needs Azure. A hidden OpenAI Whisper endpoint (`/speech/analyze`) also exists, is not used by the app and is not in the privacy notice. | 1.3, 5.1.2(i), 5.1.4 | For the Kids build: **no Azure** (Piper voice only, pronunciation check off), and **remove or disable the OpenAI speech route**. If you want pronunciation scoring back, do it on-device or with a parental opt-in and a data-processing agreement, and list the provider in the policy. |
| 7 | **ARASAAC pictograms are non-commercial** and the app will be sold. Apple wants only content you created or are licensed to use. | 5.2.1 | Get ARASAAC's written permission, or ship with `BESZED_PICTOGRAMS=false` (details in `docs/pictogram-licensing.md`). |

## Needs work or a decision (⚠️)

| Point | Guideline | Notes |
|---|---|---|
| **Looks like a repackaged website** | 4.2 | A wrapper that only loads your site gets rejected. Bundle the web app inside the app, work offline (the results outbox already does), and add real native features: purchases, haptics, an opt-in daily-adventure reminder for the parent. |
| **Health / therapy positioning** | 1.4.1, 5.1.3, 5.1.1(ix) | Strong points: reports already say "nem diagnózis" and the therapist link shows only a first name and results. Add a line "this does not replace a specialist" to the progress report and share page. Speech therapy support may count as a regulated field, so submit from **a company account, not as an individual**. Keep the FHIR export and the "enterprise" features out of the app build; they are not needed. |
| **Microphone** | 5.1.1(ii)(iv) | Add a clear purpose text ("record your own voice for Csillám's sentences"). Recording must stay optional; the app works without it. |
| **Review account / demo mode** | 2.1(a) | Provide working credentials in the review notes, with the back end running. Premium must be testable (sandbox purchase). |
| **Data label and privacy manifest** | 5.1.1 | Fill in the App Privacy section: parent name and email, child first name and birth date, audio recordings, gameplay data, purchase history (via RevenueCat). Nothing is used for tracking. Add `PrivacyInfo.xcprivacy` for the app and any SDK. |
| **Subscription value and device sync** | 3.1.2(a) | Fine: ongoing content, works on all devices through the account. Do not take away what free users already have. |
| **Consent wording** | 5.1.4 | Parental consent screen exists. Make sure it also covers the purchase and the therapist share link, and say clearly that the privacy notice applies to the child's data. A parental gate is *not* the same thing as parental consent under COPPA/GDPR. |
| **Links in the privacy notice** | 1.3 | They open ARASAAC, Twemoji, Mulberry and creativecommons.org. Keep the notice in the parents' area behind the gate. |
| **App Store text** | 2.3.8 | If Kids Category: you may say "for kids". If not: do not. |

## Already fine (✅)

- **No ads, no trackers, no analytics.** Searched the front end and templates: none. The server has an analytics service but nothing calls it.
- **Account deletion inside the app** ("Fiók törlése", removes the parent, every child, all results and the recordings) and **data export** (JSON).
- **Child data is small**: first name, optional birth date, a picture sign, game results. No location, contacts, photos or chat.
- **Parental consent** is collected before anything is stored, and again when the notice changes.
- **No unrestricted web access**, no links out from the child's screens, no user-generated content shared with others.
- **Credits** for ARASAAC, Twemoji, Mulberry and the fonts are in the privacy notice.
- **Medical wording** is careful: "not a DIFER assessment and not a diagnosis".
- **Share link for the therapist** is revocable and expires in at most 90 days.

## Paywall checklist (3.1.2, for the screen the parent sees)

Show: what premium includes (higher levels, the journey, full reports); price **and** period ("€x / month"); that it renews
automatically until cancelled and how to cancel in the Apple ID settings; the free-trial length if there is one;
links to the Terms of Use and the Privacy Policy; a **Restore purchases** button; no pre-ticked boxes, no dark patterns.
Put it behind the parental gate.

## Suggested order of work

1. Own email sign-in + review account (unblocks #1 and the review).
2. Kids-safe build switches: Piper only, OpenAI route removed, ARASAAC off or licensed (#6, #7).
3. Parental gate (#4), privacy fields and a public policy URL (#5), Terms of Use (#3).
4. Bundled app + native shell, RevenueCat purchases and the paywall (#2, 4.2).
5. App Store Connect: age rating, privacy label, review notes, company account.

## What I need from you

- The legal entity that will publish (a company), and its Apple Developer account.
- The controller name and contact for the privacy notice, and a permanent domain.
- Price and period for premium (and whether you want a free trial).
- Kids Category: yes or no.
