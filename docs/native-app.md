# The iOS and Android app (Capacitor shell)

The product is sold as an iOS and an Android app. The app is **the same Vue app as the website, bundled inside a
[Capacitor](https://capacitorjs.com) shell**: the HTML, JavaScript, styles, emoji and symbols are files on the device, and
only the data (accounts, children, results, Csillám's voice, recordings) comes from this server. Purchases are Apple In-App
Purchase / Google Play Billing through RevenueCat ([billing.md](billing.md)).

Status: the shell exists and builds its web part; the Android and iOS projects are created and synced
(`android/`, `ios/`). Nothing has been run on a device or an emulator yet (see [What is not done](#what-is-not-done)).

## Decisions and why

| Decision | Why |
|---|---|
| **The web app is bundled, not a remote site** (`capacitor.config.json` has no `server.url`) | Apple 4.2 rejects a thin wrapper around a website. A bundled app also starts without a network; the games' results already queue in the outbox (`Idempotency-Key`) and are sent later. |
| **The app signs in with a Sanctum Bearer token, not the cookie session** | The shell serves the page from its own origin (`capacitor://app.beszed.local` on iOS, `https://app.beszed.local` on Android), so Sanctum's cookie + XSRF flow cannot work across origins. The token is sent as `Authorization: Bearer …`; CORS has no credentials. |
| **The token is kept in the Keychain / Keystore** (`@aparajita/capacitor-secure-storage`) | Not in `localStorage`. This plugin is the maintained choice for Capacitor 8 (its 8.x line targets Capacitor 8, last release 2026-09, MIT, iOS Keychain and Android Keystore-backed storage, string API). The older `capacitor-secure-storage-plugin` has not been updated since January. The token is saved `whenUnlockedThisDeviceOnly`: it never migrates with a backup to another phone. Android auto-backup is off (`allowBackup=false`). |
| **`server.hostname` is `app.beszed.local`** | Sanctum's default stateful list contains `localhost`; an app served from `https://localhost` would be treated as the cookie web app and be asked for CSRF tokens. See the secure-context caveat below. |
| **Only the app's own e-mail sign-in** (no Google, no demo) | Google blocks sign-in in an embedded web view. Apple 4.8 only asks for an equivalent option when a third-party sign-in is offered, so none is. `appConfig.auth` is forced to `{ google: false, demo: false, email: true }` in the app (`resources/js/app/config.js`). |
| **Hash router in the app** | There is no server to answer `/gyerekek`; pages live after the `#` (`resources/js/app/router.js`). |
| **No service worker in the app** | Its files are already on the device. |
| **Audio goes through the authenticated client** | `<audio>` cannot send the Bearer header. In the app Csillám's server voice and the parent's recordings are fetched as a Blob and played from an object URL (`services/audio/blobCache.js`: cached by URL, 60 entries, least recently used revoked, cleared on sign-out). The website still plays the URL directly. |
| **Mulberry symbols and emoji are in the bundle** (`./symbols/`, `./emoji/`), pictograms and uploaded images come from the server | `public/symbols` is 1.4 MB and the used Twemoji set 1.1 MB; the public image routes need no auth. |

### Secure-context caveat (check this first on a device)

Capacitor recommends `server.hostname` = `localhost`, because a page on a non-localhost custom scheme may not count as a
secure context in iOS's WKWebView, and then `navigator.mediaDevices` (the parents' voice recordings) and a few other APIs are
missing. Android is fine (`https://app.beszed.local` is https). **If recording does not work on an iPhone**, switch
`server.hostname` back to `localhost`, rebuild, and on the production server set `SANCTUM_STATEFUL_DOMAINS` to the
production host only (without `localhost`) so that `capacitor://localhost` is not treated as a cookie SPA. No code change.

## Endpoints the app uses

| | |
|---|---|
| `POST /api/auth/register` `{name,email,password,device_name}` → 201 `{token,expires_at}` | sign-up (`device_name` is `Csillám (ios)` / `Csillám (android)`) |
| `POST /api/auth/login` `{email,password,device_name}` → 200 `{token,expires_at}` | 422 `errors.email` on a wrong password, 429 when locked |
| `POST /api/auth/forgot-password` `{email}` → 204 | the link in the e-mail opens the **website's** reset page in the browser; the app says so (`texts.auth.forgotSentNative`) |
| `POST /api/auth/reset-password` | only used if the reset page is ever opened inside the app |
| `DELETE /api/auth/token` (Bearer) → 204 | logout: revokes this device's token (best effort; the token is forgotten on the device either way) |
| every other `/api/...` route | `Authorization: Bearer <token>`; 90-day tokens, a password reset revokes all of them |
| `/api/beszed/tts`, `/api/beszed/recordings/{id}/audio` | need auth: fetched as Blobs (above) |
| `/api/content-images/{id}`, `/pictograms/{id}.png` | public, loaded by `<img>` from the API origin |

A 401 on an ordinary request clears the token and sends the parent to the sign-in page (`onUnauthorized` + `session.signedOut()`).
A failed `/api/me` that is **not** a 401 (no connection) keeps the token for the next start. With no token the app does not
call `/api/me` at all.

## How the web code branches

| File | Native behaviour |
|---|---|
| `resources/js/app/native.js` | `isNative()` (`window.Capacitor.isNativePlatform()`), `apiOrigin` (`VITE_API_ORIGIN`), `apiUrl(path)`. On the web `apiUrl` returns the path unchanged. |
| `resources/js/app/authToken.js` | token store: memory cache + secure storage (injected by `resources/native/main.js`) |
| `resources/js/app/http.js` | `baseURL` = API origin, no cookies / XSRF, Bearer header added to requests that go to the API origin only; 401 forgets the token |
| `resources/js/app/stores/session.js` | sign-up / sign-in / forgot / reset / logout use `/api/auth/*`; the token is saved and cleared here |
| `resources/js/app.js` | module options: `apiBase`, relative emoji / symbols, `blobAudio`; no service worker |
| `resources/js/app/pages/ChildrenPage.vue` | "Adataim letöltése" (data export, App Store 5.1.1(v)): a plain link cannot send the Bearer token, so the app fetches `/api/me/export` and hands the file to the share sheet (`navigator.share`, else a download); the website keeps the link |
| `resources/js/components/LanguageSwitcher.vue` | no call to the server's language session in the app |
| `resources/js/modules/beszed/…` | `config.blobAudio`, `config.contentImages`, `api/client.js` (`fetchBlob`, `onApiOrigin`), `services/audio/{player,preload,blobCache}.js` |
| `resources/js/modules/beszed/services/billing/purchases.js` | RevenueCat through `Capacitor.registerPlugin('Purchases')`; names checked against `@revenuecat/purchases-capacitor` 13.7 |

## Build and run

Everything is driven from the repo root. The native build is separate from the website build (`vite.config.js`,
`npm run build`, `public/build`) and never touches it.

```bash
cp .env.native.example .env.native      # then edit it, at least VITE_API_ORIGIN
npm install
npm run build:native                    # vite build (vite.native.config.js) + emoji + symbols  →  dist-native/
npm run cap:sync                        # copy dist-native into android/ and ios/, update plugins
```

`npm run build:native` fails without `VITE_API_ORIGIN` (the server's origin, https, no path). It warns while
`VITE_PRIVACY_CONTROLLER` / `VITE_PRIVACY_CONTACT` are unset (the in-app privacy notice shows placeholders; App Store blocker #5).
`resources/native/index.html` has no server to render `#app-config`, so the build writes the same JSON into it from these values.

### Environment variables (`.env.native`, only `VITE_*` are read, they end up in the app: no secrets)

| Variable | Meaning |
|---|---|
| `VITE_API_ORIGIN` | **required.** The server the app talks to, e.g. `https://beszed.example.hu` |
| `VITE_PRIVACY_CONTROLLER`, `VITE_PRIVACY_CONTACT`, `VITE_PRIVACY_VERSION` | the in-app privacy notice and terms; keep equal to the server's `PRIVACY_*` |
| `VITE_STT_DRIVER`, `VITE_TTS_DRIVER` | which speech services the privacy notice names; keep equal to the server's `STT_DRIVER` / `TTS_DRIVER` |
| `VITE_APP_NAME` | heading of the sign-in page (default `Beszéd & DIFER`, the server's `APP_NAME`) |

Server side (already in `config/cors.php`): `CORS_ALLOWED_ORIGINS` defaults to `capacitor://app.beszed.local,https://app.beszed.local`
plus Capacitor's localhost variants. `RevenueCat` keys come from the server (`GET /api/billing`), not from the app files.

### Android (Windows, macOS or Linux)

Needs [Android Studio](https://developer.android.com/studio) (JDK 21 and the Android SDK come with it). Not installed on the
machine this was written on, so **the Gradle build has not been run**.

```bash
npm run build:native && npm run cap:sync
npx cap open android        # Android Studio: Run ▶ on an emulator or a phone
# or: npx cap run android
```

The manifest has `INTERNET`, `RECORD_AUDIO` and `MODIFY_AUDIO_SETTINGS` (the last two are what `getUserMedia` in a WebView
needs; the microphone feature is marked not required), `usesCleartextTraffic="false"`, `allowBackup="false"`.
Google Play Billing's own permission is merged in by the RevenueCat SDK.

### iOS (needs a Mac with Xcode)

The project targets iOS 15 and later; take the Xcode version from Capacitor 8's environment-setup page (it moves with Apple's SDK rules).

The `ios/` project was created on Windows from Capacitor's template (Capacitor 8 uses Swift Package Manager, no CocoaPods)
and has never been opened in Xcode. On the Mac:

```bash
git pull && npm ci
cp .env.native.example .env.native      # edit it
npm run build:native
npx cap sync ios                        # rewrites ios/App/CapApp-SPM/Package.swift for the installed plugins
npx cap open ios
```

In Xcode: select the **App** target → *Signing & Capabilities*: choose the team, set the real bundle identifier, add the
**In-App Purchase** capability; run on a device (the microphone and purchases do not work in the simulator the same way).
Already in the project: `NSMicrophoneUsageDescription` (Hungarian purpose text, recording is optional) in `Info.plist`, and
`App/App/PrivacyInfo.xcprivacy` (no tracking, no tracking domains, required-reason APIs UserDefaults `CA92.1` and file
timestamps `C617.1`, collected-data list). The manifest was written without Xcode: run *Product → Archive → Generate Privacy
Report* and compare it with the App Store Connect privacy answers and the in-app privacy notice.

## Tests

* `npm run test:js` (Node's test runner): `native.test.mjs` (`apiUrl`), `authToken.test.mjs` (token store with a fake secure
  storage), `http.test.mjs` (Bearer on native and not on the web, never to another origin, 401 clears the token, quiet 401
  keeps it), `blobCache.test.mjs` (audio cache, LRU, revoking, `onApiOrigin`).
* A runtime smoke test of the real `dist-native` bundle was run in headless Chromium with a fake native bridge (in-memory
  Keychain behind the real secure-storage plugin code) and a fake cross-origin API: sign-in page on `#/login`, no API call
  without a token, wrong and right password, Bearer on `/api/me`, hash route to the hub, TTS request with Bearer played from a
  `blob:` URL, reload keeps the sign-in, sign-out revokes the token and empties the Keychain, forgot-password hint. It is not
  part of the repo (it needs a browser); redo it by hand on a device.

## What is not done

* **Bundle id is a placeholder**: `hu.beszed.app` (`capacitor.config.json`, `android/app/build.gradle`, Xcode project). The owner has
  not picked one. Change it in all three places before the first store upload (it cannot be changed afterwards).
* **Store accounts and products**: Apple Developer Program (company), Google Play Console, App Store Connect / Play subscription
  products, RevenueCat project and keys ([billing.md](billing.md) steps 1 to 4).
* **Signing and release builds**: iOS certificates and provisioning; an Android upload keystore, `versionCode` / `versionName`,
  an App Bundle (`.aab`). `minifyEnabled` is off.
* **App icons and splash screen**: the template's placeholders are still there (`ios/App/App/Assets.xcassets`,
  `android/app/src/main/res/mipmap-*`). `@capacitor/assets` can generate them from one 1024 px image.
* **Universal links / App Links**: the password-reset e-mail opens the website in the browser, not the app. To open the app
  instead: an `apple-app-site-association` and `assetlinks.json` on the server, the Associated Domains capability, and an
  App listener (`@capacitor/app`) that routes the URL to `#/jelszo-visszaallitas`.
* **Push notifications** (for example the opt-in daily reminder for the parent).
* **A real-device test.** Nothing has run on iOS or Android yet: recording (see the secure-context caveat), audio unlock after
  the first tap, the keyboard, safe areas / notch, the PDF report and sticker picture sharing (`<a download>` and
  `navigator.share({ files })` in a WebView; probably needs `@capacitor/share` + `@capacitor/filesystem`), links that open
  outside the app (the privacy page's ARASAAC / Twemoji links; Kids Category needs a parental gate before any link out),
  the RevenueCat purchase sheet in a sandbox.
* **Review of the English consent / privacy wording** (and the privacy notice's cookie paragraph, which still says "session
  cookie only": in the app it is a token in the Keychain).
* **Export compliance**: `ITSAppUsesNonExemptEncryption` is not set in `Info.plist` (the app only uses the system's HTTPS);
  decide, then add it to skip the question at every upload.
* **Keychain after uninstall**: iOS keeps Keychain items when an app is deleted, so a reinstall can still be signed in. If that is
  unwanted, clear the token on the first launch after install.
* **Localised microphone text**: only the Hungarian string exists; an `en.lproj/InfoPlist.strings` would show English on English phones.
* **Content editor in the app**: `/tartalom` still plays previews and exports CSV through same-origin URLs; use it on the website.
