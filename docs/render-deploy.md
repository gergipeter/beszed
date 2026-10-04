# Temporary family trial on Render (free tier)

For when you want a link to send the family that works even when your PC is off.
Not for real production use: free-tier Render sleeps the app after 15 minutes idle
(next visitor waits 30-60s to wake it), and the SQLite database is not backed up.
For an always-on setup with a domain, use [compose.prod.yaml](../compose.prod.yaml)
on a real server instead (see its header comment).

## 1. First deploy

1. [render.com](https://render.com) → sign up (no card needed for the free tier) →
   authorize access to the `gergipeter/beszed` GitHub repo.
2. Dashboard → **New** → **Blueprint** → pick this repo. Render reads
   [render.yaml](../render.yaml) at the repo root and proposes one service,
   `beszed-trial`, building from the root `Dockerfile`.
3. It will ask you to fill in two secret env vars it can't generate itself:
   `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` — leave them blank for now,
   you'll set them in step 2 below. Click **Apply** / **Create**.
4. First build takes ~10-15 min (same multi-stage npm+composer build as local Docker).
   When it's live, Render shows you the URL — something like
   `https://beszed-trial.onrender.com` (the exact subdomain may differ if that
   one was taken; Render appends random characters in that case).

At this point the site loads, but **signing in won't work yet** — sign-in is
Google-only and Google needs to be told about this new URL.

## 2. Wire up Google sign-in

You already have a Google OAuth client for the app
(`GOOGLE_CLIENT_ID` in your local `.env`) — reuse it, don't make a new one:

1. [Google Cloud Console](https://console.cloud.google.com/apis/credentials) →
   Credentials → open that existing OAuth 2.0 Client ID.
2. Under **Authorized redirect URIs**, add (use your actual Render URL from step 1):
   ```
   https://beszed-trial.onrender.com/auth/google/callback
   ```
   Keep the existing URIs too — this adds to the list, it doesn't replace it.
3. Save.
4. Back in Render → `beszed-trial` → **Environment**: set `GOOGLE_CLIENT_ID` and
   `GOOGLE_CLIENT_SECRET` to the same values from your local `.env`.
5. If your actual Render URL differs from `beszed-trial.onrender.com`, also update
   the `APP_URL` and `SANCTUM_STATEFUL_DOMAINS` env vars to match (no `https://`
   prefix for the latter).
6. Save → Render redeploys automatically (~1-2 min, no rebuild needed, just a restart).

## 3. Send the link to your family

Once redeployed, open the URL yourself first to confirm sign-in works, then share
it. First load after idling may take 30-60s (free tier spin-up) — worth mentioning
to them so they don't think it's broken.

## Notes

- **Data persistence**: a 1GB disk is mounted at `/app/storage`, so the SQLite
  database and uploaded files survive restarts and redeploys (but not deleting
  the service). There is no automatic backup — don't treat this as durable.
- **TTS**: `TTS_DRIVER=piper` (same on-server voice as local Docker), no Azure
  key needed.
- **Tearing it down**: Render dashboard → `beszed-trial` → Settings → Delete
  Service, whenever the trial is over. No other cleanup needed — the Google
  redirect URI can stay registered harmlessly, or remove it in the same Google
  Cloud Console screen.
