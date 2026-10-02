<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * A consistent copy of the SQLite database (parents, children, every result and recording note). `VACUUM INTO` takes
 * the copy while the app keeps writing, unlike copying the file; each copy is checked before it is kept.
 * Restoring: stop the app, put a snapshot where DB_DATABASE points, start it (docs/backup.md).
 */
class BeszedBackup extends Command
{
    protected $signature = 'beszed:backup';

    protected $description = 'Snapshot the SQLite database into BACKUP_PATH and remove snapshots older than BACKUP_KEEP_DAYS';

    public function handle(): int
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.driver") !== 'sqlite') {
            $this->info("The database is not SQLite ($connection): nothing to do here. Back it up with its own tools.");

            return self::SUCCESS;
        }

        $dir = rtrim((string) config('beszed_backup.path'), '/\\');
        if (! is_dir($dir) && ! @mkdir($dir, 0750, true) && ! is_dir($dir)) {
            $this->error("Cannot create $dir.");

            return self::FAILURE;
        }

        $file = $dir.DIRECTORY_SEPARATOR.'beszed-'.now()->format('Ymd-His').'.sqlite';
        try {
            DB::connection($connection)->statement('VACUUM INTO ?', [$file]);
            $check = (new PDO('sqlite:'.$file))->query('PRAGMA integrity_check')->fetchColumn();
        } catch (Throwable $e) {
            report($e);
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($check !== 'ok') {
            @unlink($file);
            $this->error("The snapshot did not pass the integrity check ($check) and was discarded.");

            return self::FAILURE;
        }
        @chmod($file, 0640);
        $this->info('Backup written: '.$file.' ('.round(filesize($file) / 1024).' KB)');

        $this->prune($dir, $file);

        return self::SUCCESS;
    }

    /** Removes our own snapshots older than the retention period, never the one just made. */
    private function prune(string $dir, string $newest): void
    {
        $limit = now()->subDays(max(1, (int) config('beszed_backup.keep_days')))->getTimestamp();

        foreach (glob($dir.DIRECTORY_SEPARATOR.'beszed-*.sqlite') ?: [] as $old) {
            if ($old !== $newest && filemtime($old) < $limit && @unlink($old)) {
                $this->line('Removed old snapshot '.basename($old));
            }
        }
    }
}
