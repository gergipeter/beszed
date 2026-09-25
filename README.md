# Beszéd & DIFER module for Betűvarázs

Laravel API + Vue 3 + Pinia port of the *Zoé kertje* prototype: 10 speech/DIFER games,
Csillám the unicorn guide, server-side neural TTS, parent voice recordings,
adaptive difficulty and a printable progress report.

## How it's built

```
content JSON ─seed─▶ beszed_content_items
                          │
GET /session ──▶ SessionBuilder ──▶ RoundFactory (per game) ──▶ ready-to-play rounds
                          ▲                                        │
                      Leveler ◀── POST /attempts ◀── SessionRunner.vue ─▶ engine component
                                                        │
                                   Csillám (guide store): recording ▸ server TTS ▸ Web Speech
```

- **Server decides, client plays.** RoundFactories build options, distractors and every feedback
  sentence. The 5 Vue engines (`choice`, `sequence`, `tapcount`, `trace`, `judged`) know nothing about
  specific games.
- **Voice order of preference:** parent's recording (if that line was recorded) → server TTS mp3
  (cached forever by text hash) → browser Web Speech. After 3 TTS failures in a row the client stays on
  Web Speech for the rest of the visit.
- **Adaptive levels** (`config/beszed.php → adaptive`): Papagáj = number of words (2–6), Mondd utánam =
  sentence level (1–3). When the level changes mid-session the runner swaps in rounds of the new level.

## File map

```
app/Beszed/Rounds/*Rounds.php     game logic (one per game)
app/Beszed/{SessionBuilder,Leveler,Lines}.php
app/Beszed/Tts/                   TtsClient (Azure / Null) + TtsCache
app/Http/Controllers/Beszed/      meta, session, attempts, progress, tts, recordings
app/Jobs/SynthesizeSpeech.php     + artisan beszed:tts-warm
app/Providers/BeszedServiceProvider.php   routes + TTS binding
config/beszed.php, config/tts.php
database/migrations/…_create_beszed_tables.php
database/seeders/BeszedContentSeeder.php + data/beszed/*.json   (content from the prototype)
routes/beszed.php
tests/Feature/BeszedSessionTest.php   (Pest)

resources/js/modules/beszed/
  api.ts, types.ts, utils.ts, routes.ts
  stores/guide.ts        Csillám: speech queue, mood, talking
  stores/beszed.ts       meta + recordings
  components/            Csillam.vue, SessionRunner.vue, SceneView.vue
  engines/               Choice, Sequence, TapCount, Trace, Judged
  composables/useRecorder.ts
  pages/                 BeszedHub, BeszedPlay, RecordingsPage, ProgressPage
  styles/beszed.css      everything scoped under .bz
```

## Install

1. Copy the folders into the Laravel project.
2. Register the provider in `bootstrap/providers.php`:
   `App\Providers\BeszedServiceProvider::class`
3. Routes use `api` + `auth:sanctum` (SPA cookie auth). If the api stack isn't set up: `php artisan install:api`.
4. Migrate and seed:
   ```bash
   php artisan migrate
   php artisan db:seed --class=BeszedContentSeeder
   ```
5. Frontend deps (skip what you already have): `npm i pinia vue-router axios`, then
   ```ts
   import { beszedRoutes } from '@/modules/beszed/routes'
   const router = createRouter({ history: createWebHistory(), routes: [...yourRoutes, ...beszedRoutes] })
   ```
   and open `/beszed/{childId}`.
6. Server voice (optional but the big win):
   ```dotenv
   TTS_DRIVER=azure
   AZURE_SPEECH_KEY=...
   AZURE_SPEECH_REGION=westeurope
   AZURE_SPEECH_VOICE=hu-HU-NoemiNeural   # check Azure's current Hungarian voice list
   TTS_RATE=-10%
   TTS_PITCH=+8%
   ```
   Then run a queue worker and pre-generate the fixed sentences:
   ```bash
   php artisan beszed:tts-warm        # or --sync
   ```
   Changing voice/rate/pitch changes the cache key, so audio regenerates automatically.

## Things to adapt to Betűvarázs

- **Child model / ownership.** The migration only creates `children` if it doesn't exist, and
  `Child.php` is a minimal version. Ownership is checked in `AuthorizesChild` (`child.user_id === user.id`);
  replace with your family/policy check.
- **axios.** `api.ts` creates its own instance; swap in your configured one if you have interceptors.
- **Guide name / child name.** Hub and play pages accept `childName` / `guideName` props. Wire them from
  your child store (e.g. let the child name the unicorn and store it on the child profile).
- **Fonts.** `beszed.css` imports Baloo 2 from Google Fonts; drop the import if you self-host.

## Adding a game

1. Content: `database/seeders/data/beszed/<game>.json` (`[{ "level": 1, "payload": {...} }]`).
2. Logic: `app/Beszed/Rounds/<Game>Rounds.php` extending `RoundFactory`, returning rounds for an existing engine.
3. Config: add an entry in `config/beszed.php → games` (name, emoji, colour, intro, rounds, adaptive).

No frontend change is needed unless the game needs a new interaction type (then add an engine that
implements the `EngineEmits` contract in `types.ts`).

## Tests

```bash
php artisan test --filter=Beszed
```
Covers: every game builds a valid session, Papagáj levels up/down, other families get 403.

## Next steps worth doing

- **PWA:** `vite-plugin-pwa` with a runtime cache rule for `/api/beszed/tts` and `/recordings/*/audio`
  (CacheFirst), so sessions work offline after the first play.
- **Unit tests per RoundFactory** (e.g. HolRounds never shows fölött + mögött together).
- **Privacy:** recordings and results are children's data. Before this ships to paying families: explicit
  parental consent, EU storage, retention period, and a "delete all my data" action.
