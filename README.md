# Beszéd & DIFER module for Betűvarázs

Laravel API + Vue 3 + Pinia port of the *Zoé kertje* prototype: 25 speech/DIFER games, grouped on the
hub into simple and advanced ones, with ~5,500 checked content items, Csillám the unicorn guide with a
free self-hosted Hungarian voice (Piper) or
Azure neural TTS, server-side pronunciation assessment, parent voice recordings, adaptive difficulty
and age-aware content, a daily path ("Mai kaland"), rewards (levels, streaks, medals, stickers,
Csillám's wardrobe), parent sign-in with Google, a skill-area progress report with a read-only
link for the speech therapist, and a content editor.

## Games

| Game | Engine | Practises |
|---|---|---|
| Zümi vagy Susi?, Első hang, Hol van?, Okoska, Melyik mondja szépen? | `choice` | sounds, first sounds, relations, patterns, grammar |
| Dobolós szavak, Számolós | `tapcount` (+ `choice`) | syllables, counting |
| Papagáj | `sequence` | word-list recall (adaptive 2–6 words) |
| Mondd utánam | `judged` | sentence repetition; scored by Azure pronunciation assessment, or judged by the parent |
| Méhecske útja | `trace` | fine motor tracing |
| 🧩 **Kirakó** | `puzzle` | picture puzzle: tap two pieces to swap; adaptive 2×2 → 3×2 → 3×3 |
| 🃏 **Párkereső** | `memory` | find the pairs; each card says its word; adaptive 3–6 pairs |
| 👤 **Árnyékkereső** | `choice` (silhouette) | whose shadow is it? |
| 🎵 **Rímelő** | `choice` | which word rhymes (distractors never share the last vowel) |
| 🧺 **Válogató** | `sort` | put each picture in the right basket; adaptive 4 → 6 → 8 pictures |
| 🔍 **Mi a különbség?** | `difference` | spot the one different picture on two panels; adaptive 2×2 → 3×2 → 3×3, the top level swaps in a look-alike |
| 🪆 **Kicsitől a nagyig** | `order` | tap one picture in 3 → 4 → 5 sizes from smallest to biggest (top level: also biggest first) |
| 😊 **Hogy érzi magát?** | `choice` | find the happy / sad / angry… face, or pick how Brumi or Nyuszi feels in a little situation |
| 🎩 **Mi tűnt el?** | `vanish` | remember 3–6 pictures; a sparkle cloud hides them and one is gone (Kim's game) |
| 🐣 **Mi történt előbb?** | `order` | put a little story's pictures in order (3 → 4 steps); Csillám names each step, then tells the story |
| 🎼 **Állatkórus** | `simon` | four animals sing in turn, each with its own note and colour; repeat the tune (2–7 notes) |
| 👆 **Csináld, amit mondok!** | `directions` | follow Csillám's spoken direction: one picture → two in order / "az összes állatra" → three in order, "Mielőtt…" (said the other way round), "mindenre, ami nem…" |

The seven games at the bottom were picked from what recurs across leading children's apps (Kiddopia,
BabyBus, Bimi Boo, Lingokids, Otsimo, MentalUP, LogicLike, Okos Doboz, Hamaguchi…): spot the difference,
"what's missing?", size ordering, story sequencing, emotion recognition, Simon-style sequence memory
and following directions. The directions need each picture's "-ra/-re" form (kutyára, kenyérre),
stored with the picture (`onto`) and checked by the content rules.

**Hub groups.** Each game has a `tier` in `config/beszed.php`: *Egyszerű játékok* (one tap, little
to remember: good first games for 3–5 year olds) or *Haladó játékok* (sounds, rhymes, memory and
reasoning). The hub shows the simple group first; the order inside a group is the config order.

Puzzle, memory, sort and order count mistakes as part of play, so they grade their own win
(`answer({ correct, tries: 1–3 })`) instead of one "try" per wrong move.

## Rewards

All derived on the server (`app/Beszed/Rewards/`, rules in `config/beszed.php → rewards`):

- **Stars** = correct answers. **Player level** n needs 5·n·(n−1) stars (0, 10, 30, 60, 100…).
- **Daily streak** (days in a row with a finished game) and **daily goal** (3 games), in `BESZED_TIMEZONE`.
- **Medals** per game (1–3 ⭐): the best session's share of first-try answers.
- **Stickers** (25): first game, flawless game, daily goal, 3/7-day streak, 50/200 stars, 10/30 games,
  every game tried, 5× Kirakó/Párkereső/Rímelő/Mondd utánam and each of the seven newest games,
  Párkereső beginner/master, 1/7 daily paths. Kept once earned.
- **Csillám's wardrobe**: bow, glasses, flower, hat, crown unlock at levels 2–7.

A finished game is posted to `POST children/{child}/sessions`; the answer includes what changed
(medal, level-up, new stickers, unlocked accessories, the daily path step), which the finish screen shows
and Csillám announces.

## Daily path and repetition

- **Mai kaland** (`app/Beszed/DailyPath.php`, `GET children/{child}/daily-path`): three games a day on
  the hub. The first is the game with the lowest first-try share over the last two weeks; the others
  are games not played for a while, each from a different skill area, never yesterday's. Fixed for the
  local day (`BESZED_TIMEZONE`), the same on every device. Finishing a step ticks it (offline results
  count for the day they were played); the whole path earns the *Kalandor* / *Kalandmester* stickers.
- **Missed items come back** (`SessionBuilder::weights`, `RoundFactory::weightedShuffle`), however big
  the game's pool is: an item missed once in its last three tries is practised again in about every
  other session, twice in three sessions of four, three times almost always; at most a third of a
  session is such practice. Items right at the first try twice in a row come up a bit less.
- **Age.** With a birth date on the child (optional, on the *Ki játszik?* page), adaptive games start at
  an age-appropriate level (`starts_by_age`), and the other games lean towards items whose `level` fits
  the age band (3–4 → 1, 5–6 → 2, 7+ → 3; `config/beszed_content.php → age_levels`).

## Sign-in

Parents sign in with Google (Laravel Socialite); a child picker ("Ki játszik ma?") lets them add,
choose or delete children (deleting removes all of that child's results). Local development without
Google keys signs straight in as the demo parent.

To enable Google: in Google Cloud Console → *APIs & Services → Credentials*, create an **OAuth client ID**
of type *Web application*, add the redirect URI `<APP_URL>/auth/google/callback`
(e.g. `http://localhost:8000/auth/google/callback`), then set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`
(in `.env`, which `docker compose` also reads). Google only accepts `localhost` or a real HTTPS domain
as redirect: a LAN address like `http://192.168.0.100:8000` won't work, so use the demo sign-in on a
tablet, or put the app behind HTTPS (a tunnel or a real host).

## Pronunciation assessment

In "Mondd utánam" the child can tap the mic and speak the sentence instead of (or as well as)
the parent judging it by ear. `app/Beszed/Stt/` (`SttClient` / `AzureSttClient` / `NullSttClient`,
mirroring the TTS client) sends the recording to Azure's Pronunciation Assessment API and maps its
accuracy/fluency/completeness scores onto the engines' existing 1–3 `tries` self-grade, so it drives
levels and medals the same way a parent's judgement does.

Off by default (`STT_DRIVER=null`): the mic button only appears when a server assessment is
configured *and* the browser has mic access: the parent's Approve/Not-yet buttons are always there
underneath as a fallback (no mic permission, no server key, or a failed request all fall back to them
seamlessly). To enable it, reuse the `AZURE_SPEECH_KEY`/`AZURE_SPEECH_REGION` from the TTS setup below
and set:

```dotenv
STT_DRIVER=azure
AZURE_SPEECH_STT_LANGUAGE=hu-HU
```

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
  sentence. The 13 Vue engines (`choice`, `sequence`, `tapcount`, `trace`, `judged`, `puzzle`, `memory`,
  `sort`, `difference`, `order`, `vanish`, `simon`, `directions`) know nothing about specific games.
- **Voice order of preference:** parent's recording (if that line was recorded) → server TTS mp3
  (cached forever by text hash) → browser Web Speech. After 3 TTS failures in a row the client stays on
  Web Speech for the rest of the visit.
- **Adaptive levels** (`config/beszed.php → adaptive`): Papagáj = number of words (2–6), Mondd utánam =
  sentence level (1–3). When the level changes mid-session the runner swaps in rounds of the new level.

## File map

```
app/Beszed/Rounds/*Rounds.php     game logic (one per game)
app/Beszed/{SessionBuilder,Leveler,Lines}.php
app/Beszed/Rewards/               Rewards (summary, finish a game, wardrobe), Stats, PlayerLevel, BadgeRules
app/Beszed/Tts/                   TtsClient (Azure / Null) + TtsCache
app/Beszed/Stt/                   SttClient (Azure / Null) + PronunciationResult (Mondd utánam scoring)
app/Http/Controllers/Beszed/      meta, session, attempts, progress, rewards, tts, pronunciation, recordings
app/Http/Controllers/Auth/        GoogleController, DemoLoginController (local only), LogoutController
app/Http/Controllers/Api/         MeController, ChildController (a parent's children), AccountController (consent, export, delete)
app/Http/Controllers/SpaController.php   serves the Vue app + its sign-in options
routes/web.php, routes/api.php    sign-in + SPA pages; /api/me, /api/children
app/Jobs/SynthesizeSpeech.php     + artisan beszed:tts-warm
app/Providers/BeszedServiceProvider.php   routes + TTS/STT bindings
config/beszed.php, config/tts.php, config/stt.php
database/migrations/…_create_beszed_tables.php
database/seeders/BeszedContentSeeder.php + data/beszed/*.json   (content from the prototype)
routes/beszed.php
tests/Feature/BeszedSessionTest.php   (Pest)

resources/js/app.js                      host entry: router + Pinia + the module plugin
resources/js/app/                        app shell outside the module: router (auth + consent guard), http (shared
                                         axios, 401 → sign-in), stores/session, pages/LoginPage, ChildrenPage,
                                         ConsentPage, PrivacyPage
public/sw.js, public/manifest.webmanifest, public/icons/   offline play + installable app
compose.prod.yaml, docker/production/    FrankenPHP (Caddy) image with automatic HTTPS
resources/js/modules/beszed/             plain JavaScript (no TypeScript), Vue 3 <script setup>
  index.js               public API: default plugin, beszedRoutes, createBeszedRoutes, registerEngine
  types.js               API contract as JSDoc typedefs (editor completion without TS)
  config/options.js      every tunable: apiBase, guideName, emoji set, voice, timings, preloading
  config/icons.js        every UI glyph in one place
  i18n/                  t() + hu.js; all UI text (game sentences come from the server)
  api/                   one file per resource (incl. pronunciation.js); client.js holds the (replaceable) axios instance
  router/routes.js       layout route with the pages nested under it
  layouts/BeszedLayout   loads styles + meta/recordings once, provides child/guide context, audio unlock
  pages/                 HubPage, PlayPage, ProgressPage, RecordingsPage, RewardsPage (sticker album + wardrobe)
  components/
    ui/                  BzButton, BzIconButton, BzNotice, EmojiArt, OptionTile, OptionGrid, PictureCard, PageHeader
    guide/               CsillamAvatar (animated SVG + accessories.js), GuideBubble
    game/                SessionRunner, GameHud, FinishScreen
    rewards/             LevelBar, PlayerStatus (hub strip), MedalStars, StickerCard
    charts/              TrendChart (weekly columns / line, SVG)
    hub/, recordings/    GameTile, RecordingRow
  engines/               registry (lazy, one chunk each) + contract.js; one folder per engine:
                         choice/ (+ SceneView, scenes.js), sequence/, tapcount/, trace/ (+ paths.js), judged/,
                         puzzle/, memory/, sort/, difference/, order/, vanish/, simon/, directions/
  composables/           useGameSession (the round state machine), useIdleHelp, useRecorder,
                         useAsync, useTimers, useShake, useModuleContext
  services/audio/        player (shared <audio>), webSpeech (fallback voice), preload, sfx (synthesised effects)
  services/effects/      confetti (Web Animations, compositor-only)
  stores/                meta, recordings, guide, rewards (Pinia, ids prefixed "beszed/")
  styles/                index.css → fonts, tokens (design variables), base (zero-specificity reset)
  assets/                fonts/ (Baloo 2, self-hosted, OFL), audio/silent.wav (iOS unlock)
  utils/                 text, emoji, errors, random, async
```

### Frontend assets

- **Fonts** are self-hosted: two variable woff2 files (latin + latin-ext for ő/ű, weights 500–800),
  fingerprinted by Vite. No request goes to Google Fonts, so no visitor IP reaches a third party.
- **Audio.** All speech plays through one shared `<audio>` element (`services/audio/player.js`) that iOS
  unlocks on the first tap. Interrupted playback settles immediately. While a round is played, the next
  round's sentences (and the parent's recorded praise/retry lines) are fetched in the background, so the
  server synthesises TTS ahead of time and the next prompt starts instantly.
- **Pictures** are emojis, all drawn by `components/ui/EmojiArt.vue` from the bundled Twemoji SVG set
  (`@twemoji/svg`, copied to `public/build/emoji/` by `npm run build`), so they look the same on every
  phone. A multi-emoji picture ("🐱📦") becomes one image per emoji; a missing file falls back to the
  native emoji. `npm run check:emoji` fails if any content or UI emoji has no Twemoji image.
- **Styles.** Each component carries its own `<style scoped>`. Global CSS is only tokens
  (`styles/tokens.css`: colours, shadows, radii, type; dark mode) and a `:where()` reset that never
  wins over a component class, so no `!important` is needed.
- **Code splitting.** Every page and every engine is its own chunk; a session downloads the engines it
  needs before its first round shows.

### Smooth on phones

Everything that moves continuously animates only `transform`/`opacity` on HTML layers, which the GPU
runs off the main thread. Measured on an emulated phone (4× CPU slowdown), layout passes per animation:

| Scene | Before | After |
|---|---|---|
| Hub idle (Csillám bobbing) | 121 | 0 |
| Memory card flip | 102 | 0 |
| Puzzle swap + solve | 119 | 15 |
| Finish screen | 379 | 22 |

How: Csillám's body bob/jump, arms, talking mouth and sparkles are HTML layers around the SVG (browsers
don't composite animations inside SVG); the level bar slides with `translateX` instead of growing
`width`; the puzzle's "solved" moment fades a picture in instead of animating grid gaps; confetti uses
the Web Animations API with concrete values (CSS keyframes with custom properties fall back to the main
thread). Rounds, pages and the finish screen glide in with short transform/opacity transitions; hub
tiles float in one by one. `prefers-reduced-motion` switches all of it off.

### Celebration

- **Sounds** (`services/audio/sfx.js`) are synthesised with WebAudio: a chime for a right answer, a fanfare
  at the end of a game, a rising run on level-up, a shimmer for stickers. No files; unlocked on the first
  tap for iOS; soft enough not to cover Csillám. `config.sfx = { enabled, volume }`.
- **Confetti** (`services/effects/confetti.js`) at the end of every game, a bigger burst on level-up.

## Offline play

`public/sw.js` (a small hand-written service worker, no build plugin) makes the app work without a
connection after the first visit: it precaches the whole Vite build and the app shell, keeps TTS and
recording audio, and answers `GET /api/*` from the last response when offline (so a game played before
can be replayed). Results played offline go to an outbox in `localStorage` (`api/outbox.js`) and are
uploaded, oldest first, when the device is back online; they carry `played_at`, so streaks count the
day the child actually played. Signing out wipes the cached personal data.

Service workers need HTTPS (or `localhost`), so on the LAN address the app works as before, just without
offline mode. `public/manifest.webmanifest` + `public/icons/` make it installable ("Add to home screen").

## Privacy

- **Consent.** After the first sign-in a parent must accept the privacy notice before anything is stored
  about a child; bumping `PRIVACY_VERSION` asks everyone again. `/adatvedelem` is the notice (public);
  set `PRIVACY_CONTROLLER` and `PRIVACY_CONTACT`. **Have the text reviewed before real families use it.**
- **Export.** "Adataim letöltése" downloads everything stored about the parent and their children as JSON.
- **Delete.** "Fiók törlése" (type `TÖRLÉS`) deletes the parent, all children, every result and the
  voice recording files. Deleting one child removes that child's results and share links.
- **Share links** show only what the notice lists (first name, age band, results) and stop working
  when revoked or expired.

## Voice

`TTS_DRIVER` picks Csillám's server voice; the browser's own voice is the fallback either way.

- `piper` (default in Docker): [Piper](https://github.com/rhasspy/piper) runs on the server with the
  Hungarian `hu_HU-anna-medium` (female, default) and `hu_HU-imre-medium` voices; both images download
  it at build time, `lame` makes the mp3. Free, offline, ~0.15 s per sentence on a laptop. The parent's
  *Beállítások* page picks the voice and speed. **License:** the voices are trained on CC0 Hungarian
  recordings but fine-tuned from Piper's English base model; check the voice model cards before
  commercial use.
- `azure`: Azure neural voices (Noémi, Tamás), see *Install*.
- `null`: browser voice only.

Your own `.env` wins over the compose default: if it has `TTS_DRIVER=null`, set it to `piper` (or
remove the line) and restart. Changing voice or speed changes the TTS cache key, so audio regenerates.

## Content and the editor

Every game's items live in `database/seeders/data/beszed/<game>.json`, about 5,500 in all.

**Word bank.** Most of them are generated from `database/lexicon/hu.json`: about 780 picturable
Hungarian words with a category and how familiar each is to a small child (1–3). The first ~400 were
written by hand with their Twemoji picture and accusative (almát, lovat, kezet); the rest are
everyday preschool words from a hand-picked list (`database/lexicon/arasaac-words.json`) that have an
ARASAAC pictogram but no emoji. They appear in the sound, syllable, memory, puzzle, shadow and rhyme
games; the category, counting, "Hol van?" and grammar games keep to the hand-checked words. `php artisan beszed:content-generate` (`--dry-run` to preview)
works out syllables, first sounds, zs/s, rhyme endings, categories and simple grammar pairs from it,
keeps the hand-written rows, marks generated ones `"gen": true`, never uses a word or picture twice in a
game, skips rhymes that are just a compound of the same word (labda / kosárlabda), and runs every item
through the content rules. A test fails if the seed files and the word bank drift apart. To add words,
add them to the word bank and run the command; to add something the rules can't derive (sentences for
*Mondd utánam*, tricky grammar), add a hand-written row.

The seeder
runs on every start: new items are added, changed ones updated, removed ones switched off (old results
keep their reference), and items changed in the editor are never overwritten.

`app/Beszed/Content/ContentRules.php` checks each item against its game's schema
(`config/beszed_content.php`) and the Hungarian it teaches: *zs* words really contain zs, *s* words a
plain s (not sz, zs or cs), syllables rebuild the word with one per vowel, first sounds are
digraph-aware (gy, sz, dzs…), rhymes are the word's ending, sentence chunks rebuild the sentence,
accusatives end in -t. A test runs every seed item through it.

**Editor** (`/tartalom`): parents whose e-mail is in `ADMIN_EMAILS` (nobody by default in Docker, since
the demo sign-in is public through the tunnel; set `ADMIN_EMAILS=parent@example.test` in `.env` for the demo parent) get a
*Tartalomszerkesztő* link on the *Ki játszik?* page. The form is built from the schema, shows the
rules' errors in Hungarian, previews the pictures, can play a word in Csillám's voice, and refuses a
word that is already in the game. Deleting a played item only switches it off.
API: `GET/POST /api/admin/content/{game}`, `PUT/DELETE /api/admin/content/{game}/{item}`.

## Pictures: ARASAAC pictograms

Where a word has one, the games show its [ARASAAC](https://arasaac.org) pictogram, the picture set
speech therapists use, instead of the emoji (`BESZED_PICTOGRAMS=false` turns this off).

- `node scripts/arasaac-import.mjs` matches every word-bank word to the pictogram whose Hungarian
  keyword is the word and whose tags fit its category (so *levél* is a leaf, not a letter), adds the
  words of `arasaac-words.json` (an entry `óvoda=nursery school` falls back to the English keyword),
  and records the pictogram id as `"p"`. Every choice was checked by eye; the few wrong ones are pinned
  in `PICK` in the script. Then `php artisan beszed:content-generate` rebuilds the content.
- Content keeps the emoji where a word has one; a session swaps it for `arasaac:<id>~<emoji>`
  (`App\Beszed\Content\Pictures`), and `EmojiArt.vue` draws the pictogram, or the emoji if the
  picture can't load. Words without an emoji store `arasaac:<id>`, which the content editor accepts too.
- The server fetches each pictogram from ARASAAC once and serves its own copy at
  `/pictograms/<id>.png` (no session or cookies, cached for a year); players never contact ARASAAC.
  `php artisan beszed:pictograms-fetch` downloads all of them ahead (about 720 small PNGs, ~5 MB).
  The service worker keeps the ones seen for offline play.
- **License: CC BY-NC-SA 4.0 — non-commercial use only**, with attribution (Sergio Palao, ARASAAC,
  Government of Aragón), shown on `/adatvedelem`. A paid or ad-supported version needs a different
  picture set or ARASAAC's permission.

## Skill map and the therapist link

- **Skill areas** (`config/beszed_skills.php`, `app/Beszed/ProgressReport.php`): the games grouped into
  the five DIFER areas (írásmozgás-koordináció, beszédhanghallás, relációszókincs, elemi számolás,
  tapasztalati következtetés) plus two extra groups. Each shows the first-try share of the period, the
  same-length period before (tick on the bar) and a trend. Bands (*Biztosan megy*, *Fejlődik*,
  *Gyakoroljuk még*, *Még kevés adat* under 5 answers) describe how the games went, not a comparison
  with other children: there are no norms, the mapping to DIFER is approximate, and the page says so.
- **Share link** (*Haladás → Megosztás a logopédussal*): the parent creates a read-only link valid for
  7, 30 or 90 days, optionally named ("Kovács Anna logopédus"), sees when it was opened, and can revoke
  it. The link opens `/megosztas/<token>` without sign-in: the child's first name, age band, the last 90
  days per area and per game, and the written summary. Only the token's SHA-256 is stored, so a link is
  shown once. At most 5 live links per child; the page and API are `no-store`, `noindex` and
  `Referrer-Policy: no-referrer`; the service worker never caches them.

## Progress charts

The parent's *Haladás* page shows two weekly charts over the selected period: games finished per week
(columns) and the share of first-try answers (line). Two single-measure charts rather than one with two
scales; one validated series colour (`--bz-chart`) per theme; hover or keyboard focus shows each week,
and "Heti adatok táblázatban" lists the same numbers. `GET children/{child}/progress/history?weeks=&game=`.

## Public demo (free Cloudflare quick tunnel)

Cloudflare's free hosting can't run PHP, so the demo is this PC's Docker app behind a free
[quick tunnel](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/do-more-with-tunnels/trycloudflare/)
(no account needed). With `docker compose up` running:

```bash
docker run -d --name beszed-tunnel --network beszed_default cloudflare/cloudflared:latest \
  tunnel --no-autoupdate --url http://app:8000
docker logs beszed-tunnel 2>&1 | grep trycloudflare.com   # the public https address
```

The address changes whenever the tunnel restarts, and it only works while this PC is on.
`compose.yaml` accepts sign-in cookies from `*.trycloudflare.com`. Anyone with the link can use
"Demó belépés", so everyone shares the one demo parent, and it has no content-editor rights
(`ADMIN_EMAILS` is empty by default). Debug error pages are off (`APP_DEBUG=false`). For a
stable address, use a named tunnel on your own domain with the production setup below.

## Production (HTTPS)

`compose.prod.yaml` + `docker/production/` run the app on FrankenPHP (Caddy), which gets and renews the
HTTPS certificate for your domain by itself. On a server with Docker and a domain pointing at it:

```bash
cp .env.production.example .env.production   # domain, APP_KEY, Google keys, privacy contact
docker compose -f compose.prod.yaml up -d --build
```

Ports 80 and 443 must be reachable. Production mode has no demo sign-in, caches config/routes/views,
seeds only the game content, sets security headers and keeps data in the `storage` volume. Google's
redirect URI is `https://<your-domain>/auth/google/callback`. The dev setup below is unchanged.

## Install

Start the complete app with Docker Desktop running:

```bash
docker compose up --build
```

Open [http://localhost:8000](http://localhost:8000). The local app creates a demo parent and child,
migrates SQLite, and seeds all game content automatically. Use Ctrl+C to stop it; Docker keeps
progress and recordings in the `beszed-storage` volume. The local-only demo sign-in is disabled
outside `APP_ENV=local`; with Google keys set (see *Sign-in*) you get the sign-in page instead.

Run the feature tests with:

```bash
docker compose exec app php artisan test
```

`phpunit.xml` forces an in-memory database and `APP_ENV=testing` (`<env>` and `<server>` with
`force="true"`), so this never touches the app's real database even inside the container.

In Docker, Csillám speaks with the built-in Piper voice (see *Voice*). To use Azure's voice instead, copy `.env.example` to `.env`, then set:

   ```dotenv
   TTS_DRIVER=azure
   AZURE_SPEECH_KEY=...
   AZURE_SPEECH_REGION=westeurope
   AZURE_SPEECH_VOICE=hu-HU-NoemiNeural   # check Azure's current Hungarian voice list
   TTS_RATE=-10%
   TTS_PITCH=+8%
   ```

Then rebuild/restart the container and pre-generate the fixed sentences:
   ```bash
  docker compose exec app php artisan beszed:tts-warm
   ```
   Changing voice/rate/pitch changes the cache key, so audio regenerates automatically.

Pronunciation assessment for "Mondd utánam" is off by default too. See *Pronunciation assessment*
above to enable it with the same Azure Speech resource.

## Things to adapt to Betűvarázs

- **Child model / ownership.** The migration only creates `children` if it doesn't exist, and
  `Child.php` is a minimal version. Ownership is checked in `AuthorizesChild` (`child.user_id === user.id`);
  replace with your family/policy check.
- **Plugin options.** Everything is set once in `resources/js/app.js`:
  ```js
  app.use(beszed, {
    http: myAxios,                   // your configured axios (interceptors, CSRF…); default: its own
    guideName: 'Csillám',
    routes: { path: '/beszed', props: route => ({ childName: children.byId(route.params.childId)?.name }) },
    router,                          // optional: registers the routes instead of spreading beszedRoutes
    exitTo: { name: 'children' },    // "Gyerekek" button on the hub → your child picker
  })
  ```
  See `config/options.js` for the rest (API base, emoji set, voice, timings).
- **Guide name / child name.** The layout takes `childName` / `guideName` props from `routes.props`.
  Wire them from your child store (e.g. let the child name the unicorn and store it on the child profile).
- **Fonts.** Already self-hosting Baloo 2 in the host app? Drop `@import './fonts.css'` in `styles/index.css`.

## Adding a game

1. Content: `database/seeders/data/beszed/<game>.json` (`[{ "level": 1, "payload": {...} }]`).
2. Logic: `app/Beszed/Rounds/<Game>Rounds.php` extending `RoundFactory`, returning rounds for an existing engine.
3. Config: add an entry in `config/beszed.php → games` (name, emoji, colour, intro, rounds, adaptive).

No frontend change is needed unless the game needs a new interaction type. Then add
`engines/<name>/<Name>Engine.vue` using `defineProps(engineProps)` / `defineEmits(engineEmits)` from
`engines/contract.js`, and register it in `engines/index.js` (or call `registerEngine(name, loader)`).

## Tests

```bash
php artisan test
```
Covers: every game builds a valid session; game rules (Kirakó never starts solved, every Párkereső word
exactly twice, Rímelő distractors never rhyme, Válogató baskets); adaptive levels; rewards (levels,
stickers awarded once, daily goal, streaks across local-day boundaries, wardrobe unlocks); Google sign-in
(new parent, linking a verified email, refusing an unverified one, cancel), demo sign-in, `/api/me`,
children CRUD, consent versioning, data export, account deletion (including recording files, and that
logout can't resurrect the deleted user), weekly history, back-dated offline results, and that other
families get 403. Also: every seed item passes `ContentRules`, and the rules catch wrong content; the
seeder keeps edited items; missed items come back more often and small children get easier items;
the editor's access, validation, duplicate check and delete-vs-switch-off; share links (what the
therapist sees, revoke, expiry, limits, headers); the daily path (areas, stability, weakest game first,
ticking steps, the sticker, next day).

## Credits

Pictograms: Sergio Palao, [ARASAAC](https://arasaac.org), property of the Government of Aragón (Spain),
CC BY-NC-SA 4.0. Emoji graphics: [Twemoji](https://github.com/jdecked/twemoji) © Twitter, Inc. and other
contributors, CC-BY 4.0 (both credited on `/adatvedelem`). Font: Baloo 2, SIL OFL. Voice: Piper (MIT) with the
`hu_HU` voices from rhasspy/piper-voices.

## Next steps worth doing

- **Legal review** of the privacy notice, and a retention period (e.g. prune answers older than 2 years).
- **Deploy** with `compose.prod.yaml` on an EU server, then add HSTS in `docker/production/Caddyfile`.
- **Unit tests per RoundFactory** (e.g. HolRounds never shows fölött + mögött together).
