<?php

use Illuminate\Support\Facades\DB;

/*
| VACUUM cannot run inside a transaction, which is how the other tests wrap their in-memory database, so this one
| works on a separate, file-based SQLite connection.
*/
beforeEach(function () {
    $this->defaultConnection = config('database.default'); // the transaction wrapper rolls back whatever is default at the end
    $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'beszed-backup-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
    $this->source = $this->dir.DIRECTORY_SEPARATOR.'source.sqlite';
    touch($this->source);

    config([
        'database.connections.backup_source' => ['driver' => 'sqlite', 'database' => $this->source, 'prefix' => '', 'foreign_key_constraints' => true],
        'database.default' => 'backup_source',
        'beszed_backup.path' => $this->dir.DIRECTORY_SEPARATOR.'snapshots',
        'beszed_backup.keep_days' => 14,
    ]);
    DB::connection('backup_source')->statement('create table parents (id integer primary key, name text)');
    DB::connection('backup_source')->table('parents')->insert(['name' => 'Anna']);
});

afterEach(function () {
    config(['database.default' => $this->defaultConnection]);
    DB::purge('backup_source');
    foreach (glob($this->dir.'/{,snapshots/}*', GLOB_BRACE) ?: [] as $file) {
        is_file($file) && unlink($file);
    }
    @rmdir($this->dir.'/snapshots');
    @rmdir($this->dir);
});

it('takes a snapshot with the data in it', function () {
    $this->artisan('beszed:backup')->assertSuccessful();

    $files = glob($this->dir.'/snapshots/beszed-*.sqlite');
    expect($files)->toHaveCount(1);
    $copy = new PDO('sqlite:'.$files[0]);
    expect($copy->query('select name from parents')->fetchColumn())->toBe('Anna');
});

it('removes snapshots past the retention period but never the new one', function () {
    mkdir($this->dir.'/snapshots');
    $old = $this->dir.'/snapshots/beszed-20200101-030000.sqlite';
    $recent = $this->dir.'/snapshots/beszed-20990101-030000.sqlite';
    $stranger = $this->dir.'/snapshots/notes.txt';
    foreach ([$old, $recent, $stranger] as $file) {
        touch($file);
    }
    touch($old, now()->subDays(40)->getTimestamp());
    touch($stranger, now()->subDays(40)->getTimestamp());

    $this->artisan('beszed:backup')->assertSuccessful();

    expect(file_exists($old))->toBeFalse()
        ->and(file_exists($recent))->toBeTrue()
        ->and(file_exists($stranger))->toBeTrue() // only files named like our snapshots are ever removed
        ->and(glob($this->dir.'/snapshots/beszed-*.sqlite'))->toHaveCount(2);
});

it('leaves a database that is not SQLite to its own tools', function () {
    config(['database.connections.pretend' => ['driver' => 'mysql'], 'database.default' => 'pretend']);

    $this->artisan('beszed:backup')->expectsOutputToContain('not SQLite')->assertSuccessful();
    expect(is_dir($this->dir.'/snapshots'))->toBeFalse();
});
