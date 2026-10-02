<?php

return [
    // Where the daily SQLite snapshots go. In production this is its own volume (compose.prod.yaml), so a damaged
    // data volume does not take the copies with it. Copy that volume off the server as well (docs/backup.md).
    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    // Snapshots older than this many days are removed; the newest one is always kept.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
];
