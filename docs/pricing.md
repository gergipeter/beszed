# Premium pricing: decision and reasoning

Decided 2026-10-01 (on the owner's request: "you decide, check the market"). A starting point to test, not a final answer.

## The market

Subscription prices of comparable children's learning apps (US list prices, found 2026-10-01; they vary by country and offer):

| App | Monthly | Yearly | Free trial |
|---|---|---|---|
| [Speech Blubs](https://www.educationalappstore.com/app/speech-blubs-language-therapy) (speech therapy) | about $10 | cheaper billed yearly | 7 days |
| [Lingokids](https://help.lingokids.com/hc/en-us/articles/115005120505-What-is-the-Price-and-currency-for-Lingokids-Plus) | $13.49 | $161.88 | 7 days |
| [Homer](https://myelearningworld.com/homer-vs-abcmouse/) | $12.99 | $79.99 | 30 days |
| ABCmouse | $14.99 | $45 (web), $59.99 (app store) | limited free tier |
| [AdaptedMind](https://learnspark.io/blog/adaptedmind-cost-per-month-annual-pricing/) (math) | $9.95 | – | 30 days |

Pattern: about **$10–15 a month** in the US, and a yearly plan that is **50–70% cheaper per month** than paying monthly.
Most families end up on the yearly plan or never convert, so the yearly price is the one that matters.

Hungary has lower incomes and fewer paid children's apps than the US, so the same value is priced lower there.
I did not find reliable Hungarian price data for this category; treat the HUF numbers as an assumption to test.

## Decision

| | Hungary (HUF) | Rest of the EU (EUR) |
|---|---|---|
| **Monthly** | **2 490 Ft** | €5.99 |
| **Yearly** | **17 990 Ft** (about 40% off the monthly rate, 1 499 Ft a month) | €39.99 |
| Free trial | **7 days on the yearly plan only** | same |

Prices are what the parent pays, VAT included. One subscription covers **all children in the account** and all the
parent's devices (App Store rule 3.1.2(a)).

Why these numbers:

- **Below the US benchmark**: about 60% of a Speech Blubs-style $10, in line with Hungarian purchasing power; far below Lingokids and Homer.
- **The yearly plan is the target.** The 40% discount nudges families to commit for the school year; monthly stays a fair way in.
- **Trial on the yearly plan only.** The free plan (every game up to level 3, no time limit) already works as a long trial. A short trial on the plan with the biggest commitment lowers that barrier, and a trial on the monthly plan would mostly attract people who cancel after a week.
- **Not cheaper than ABCmouse's $45 a year** in absolute terms is deliberate: this is a specialist tool, not a general library.

## What stays free, what is premium

Free: every game up to level 3 (no time limit). Premium: higher levels, the Utazás (guided path) and the full progress
reports and the PDF summary. Never take away what a free user already has (App Store rule 3.1.2(a)).

## Rough economics (Hungary)

2 490 Ft includes 27% VAT, so 1 961 Ft is revenue before the store's commission. Both stores take 15% on subscriptions for small
developers (Apple Small Business Program, Google's reduced rate), so about **1 670 Ft (≈ €4.20) a month** reaches you per monthly
subscriber, and about **1 270 Ft a month** per yearly subscriber (17 990 Ft ÷ 12, net of VAT and 15%). Check both programmes'
current terms before launch; they change.

## Product setup (for when the store accounts exist)

- Subscription group "Premium"; products `beszed.premium.monthly`, `beszed.premium.yearly`; entitlement `premium`
  (the server already reads `users.subscription_plan` = `premium`).
- Introductory offer on the yearly product: 7 days free.
- Set the Hungary price points by hand (HUF); let the stores convert the EUR price for other countries.

## How to learn more after launch

- Watch the free → paid rate, the monthly/yearly split and cancellations in the first 8 weeks.
- If conversion is low and the paywall is seen often: test a lower yearly price or a 14-day trial before touching the monthly price.
- If it is high: you are probably too cheap; try 2 990 Ft / 21 990 Ft.
- Change one thing at a time, and only on new subscribers.
