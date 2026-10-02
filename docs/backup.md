# Database backup

Production keeps everything (parents, children, results, recordings' notes) in **one SQLite file**,
`/app/storage/app/database.sqlite`, on the Docker volume `storage`. Losing that volume loses every account.

## What runs

`php artisan beszed:backup` takes a consistent snapshot with SQLite's `VACUUM INTO` (safe while the app is writing, unlike
copying the file), checks it with `PRAGMA integrity_check`, discards it if it is damaged, and removes snapshots older than
`BACKUP_KEEP_DAYS` (default 14). The scheduler runs it every night at 03:30 (app timezone); the production entrypoint
starts the scheduler. Snapshots are named `beszed-YYYYMMDD-HHMMSS.sqlite`.

In `compose.prod.yaml` the snapshots go to their own volume, `backups`, mounted at `/backups` (`BACKUP_PATH`). A separate
volume protects against a damaged data volume, **not** against losing the server, so:

## Copy it off the server (still to do)

Pick one and run it from the host (cron) or a second container:

```sh
# the newest snapshot to another machine or object storage
docker run --rm -v beszed_backups:/backups:ro -v "$PWD":/out alpine \
  sh -c 'cp "$(ls -1t /backups/beszed-*.sqlite | head -1)" /out/'
rclone copy ./beszed-*.sqlite remote:beszed-backups      # or scp / restic / your provider's tool
```

Snapshots hold personal data of children: store them encrypted and keep them no longer than needed (they are a copy of
data the parents can delete in the app, so the retention period is part of the privacy notice).

## Restore

1. Stop the app: `docker compose -f compose.prod.yaml stop app`.
2. Put the chosen snapshot in place of `/app/storage/app/database.sqlite` (for example with a one-off container that mounts
   the `storage` and `backups` volumes), keeping the broken file under another name.
3. Start the app: `docker compose -f compose.prod.yaml start app`. The entrypoint runs the migrations, so a snapshot from an
   older version is brought up to date.
4. Try signing in and open a child's progress. Run `beszed:backup` once to check that backups still work.

Practise this once before you need it.

## Not covered

- MySQL (the shared development database): `beszed:backup` only does SQLite and says so; use `mysqldump`.
- Uploaded content images and recordings live in `storage/app` on the `storage` volume and are not part of the snapshot.
