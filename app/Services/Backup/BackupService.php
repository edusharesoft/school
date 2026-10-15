<?php

namespace App\Services\Backup;

use App\Models\Saas\BackupRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Backup = one zip with data/<table>.jsonl for every table, files/…, and manifest.json (counts + SHA-256).
 * Restore = migrate an empty database, then load the JSON lines. See config/backup.php.
 */
class BackupService
{
    // ───────────────────────────── create ─────────────────────────────

    public function run(string $trigger = 'manual', ?int $userId = null): BackupRun
    {
        $run = BackupRun::query()->create(['trigger' => $trigger, 'status' => 'running', 'started_at' => now(), 'created_by' => $userId]);
        $work = storage_path('app/tmp/backup-'.$run->id.'-'.bin2hex(random_bytes(4)));

        try {
            if (! class_exists(ZipArchive::class)) {
                throw new RuntimeException('The PHP zip extension is not enabled. Laragon: Menu → PHP → Extensions → tick zip (then restart the terminal). Linux: apt install php-zip.');
            }
            File::ensureDirectoryExists($work.'/data', 0700);
            [$tables, $rows, $entries, $skipped] = $this->dumpTables($work.'/data');

            $files = [];
            if (config('backup.include_files')) {
                $files = $this->collectFiles();
            }
            $manifest = [
                'format' => 1, 'app' => config('app.name'), 'created_at' => now()->toIso8601String(), 'driver' => DB::connection()->getDriverName(),
                'schema' => $this->lastMigration(), 'tables' => $tables, 'skipped_tables' => $skipped, 'files' => count($files), 'sums' => $entries,
            ];

            $name = 'backup-'.now()->format('Ymd-His').'-'.$run->id.'.zip';
            $disk = Storage::disk((string) config('backup.disk'));
            $relative = trim((string) config('backup.folder'), '/').'/'.$name;
            $disk->makeDirectory(trim((string) config('backup.folder'), '/'));
            $target = $disk->path($relative);

            $zip = new ZipArchive;
            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot create the backup file.');
            }
            $pass = (string) config('backup.passphrase');
            if ($pass !== '') {
                $zip->setPassword($pass);
            }
            foreach ($tables as $table => $count) {
                $this->addEntry($zip, 'data/'.$table.'.jsonl', $work.'/data/'.$table.'.jsonl', $pass);
            }
            foreach ($files as $zipName => $path) {
                $manifest['sums']['files/'.$zipName] = hash_file('sha256', $path);
                $this->addEntry($zip, 'files/'.$zipName, $path, $pass);
            }
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if ($pass !== '') {
                $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256);
            }
            if (! $zip->close()) {
                throw new RuntimeException('Writing the backup file failed (disk full?).');
            }
            @chmod($target, 0600);

            $run->forceFill([
                'status' => 'done', 'file_path' => $relative, 'bytes' => filesize($target), 'checksum' => hash_file('sha256', $target), 'tables_count' => count($tables),
                'rows_count' => array_sum($tables), 'files_count' => count($files), 'encrypted' => $pass !== '', 'finished_at' => now(),
                'error' => $skipped ? 'Warning — tables skipped because they cannot be read (listed by the database but missing): '.implode(', ', $skipped) : null,
            ])->save();
            $this->prune();
        } catch (\Throwable $e) {
            report($e);
            $run->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 480), 'finished_at' => now()])->save();
        } finally {
            File::deleteDirectory($work);
        }

        return $run->fresh();
    }

    /** @return array{0: array<string,int>, 1: int, 2: array<string,string>, 3: array<int,string>} tables => rows, total rows, entry => sha256, skipped tables */
    protected function dumpTables(string $dir): array
    {
        $tables = [];
        $sums = [];
        $skipped = [];
        $total = 0;
        $skip = (array) config('backup.skip_tables');
        foreach (self::tableNames() as $name) {
            if (in_array($name, $skip, true)) {
                continue;
            }
            $out = fopen($dir.'/'.$name.'.jsonl', 'wb');
            $n = 0;
            try {
                foreach (DB::table($name)->cursor() as $row) {
                    fwrite($out, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION)."\n");
                    $n++;
                }
            } catch (\Illuminate\Database\QueryException $e) {
                // A table the database lists but cannot open (orphaned / half-dropped) must not stop the whole backup — it is reported instead.
                fclose($out);
                @unlink($dir.'/'.$name.'.jsonl');
                if (! str_contains($e->getMessage(), '42S02') && ! str_contains($e->getMessage(), 'no such table')) {
                    throw $e;
                }
                $skipped[] = $name;

                continue;
            }
            fclose($out);
            $tables[$name] = $n;
            $total += $n;
            $sums['data/'.$name.'.jsonl'] = hash_file('sha256', $dir.'/'.$name.'.jsonl');
        }
        ksort($tables);

        return [$tables, $total, $sums, $skipped];
    }

    /**
     * Tables of THIS database only. MySQL's catalogue lists the tables of every database on the server (Laragon usually has
     * several projects), so they are filtered by schema; other drivers already return one schema.
     *
     * @return array<int,string>
     */
    public static function tableNames(?string $connection = null): array
    {
        $db = DB::connection($connection);
        $schema = $connection ? Schema::connection($connection) : Schema::getFacadeRoot();
        $driver = $db->getDriverName();
        $names = [];
        foreach ($schema->getTables() as $t) {
            if (in_array($driver, ['mysql', 'mariadb'], true) && isset($t['schema']) && $t['schema'] !== $db->getDatabaseName()) {
                continue;
            }
            $names[$t['name']] = true;
        }

        return array_keys($names);
    }

    /** @return array<string,string> zip name => absolute path */
    protected function collectFiles(): array
    {
        $out = [];
        $exclude = (array) config('backup.file_exclude');
        foreach ((array) config('backup.file_roots') as $root) {
            $base = storage_path($root);
            if (! is_dir($base)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                if (in_array(explode('/', $relative)[0], $exclude, true) || str_ends_with($relative, '.gitignore')) {
                    continue;
                }
                $out[str_replace('app/', '', $root).'/'.$relative] = $file->getPathname();
            }
        }

        return $out;
    }

    protected function addEntry(ZipArchive $zip, string $name, string $path, string $pass): void
    {
        $zip->addFile($path, $name);
        if ($pass !== '') {
            $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
        }
    }

    protected function lastMigration(): ?string
    {
        return Schema::hasTable('migrations') ? DB::table('migrations')->orderByDesc('id')->value('migration') : null;
    }

    // ───────────────────────────── verify / drill ─────────────────────────────

    /**
     * Check the file (SHA-256 of the zip, then of every entry). With $drill the data is also loaded into an empty scratch
     * database built from the migrations and every table's row count is compared with the manifest.
     *
     * @return array{ok: bool, notes: array<int,string>}
     */
    public function verify(BackupRun $run, bool $drill = false, ?string $connection = null): array
    {
        $notes = [];
        $ok = true;
        $disk = Storage::disk((string) config('backup.disk'));
        if (! $run->file_path || ! $disk->exists($run->file_path)) {
            return $this->finishVerify($run, false, ['The backup file is missing.'], false);
        }
        $path = $disk->path($run->file_path);
        if ($run->checksum && ! hash_equals($run->checksum, hash_file('sha256', $path))) {
            return $this->finishVerify($run, false, ['The file does not match its checksum — it was changed or damaged after it was written.'], false);
        }
        $notes[] = 'File checksum OK.';

        try {
            $zip = $this->open($path);
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            if (! is_array($manifest)) {
                throw new RuntimeException('manifest.json cannot be read (wrong passphrase?).');
            }
            $bad = [];
            foreach ((array) $manifest['sums'] as $entry => $sum) {
                $stream = $zip->getStream($entry);
                if (! $stream) {
                    $bad[] = $entry.' (missing)';

                    continue;
                }
                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $stream);
                fclose($stream);
                if (! hash_equals($sum, hash_final($ctx))) {
                    $bad[] = $entry;
                }
            }
            if ($bad) {
                $ok = false;
                $notes[] = 'Damaged or missing entries: '.implode(', ', array_slice($bad, 0, 10));
            } else {
                $notes[] = count($manifest['sums']).' entries checked.';
            }

            if ($ok && $drill) {
                $drillNotes = $this->drill($zip, $manifest, $connection);
                $ok = $drillNotes['ok'];
                $notes = array_merge($notes, $drillNotes['notes']);
            }
            $zip->close();
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
            $notes[] = $e->getMessage();
        }

        return $this->finishVerify($run, $ok, $notes, $drill && $ok);
    }

    protected function finishVerify(BackupRun $run, bool $ok, array $notes, bool $drilled): array
    {
        $run->forceFill(['verified_at' => now(), 'verify_status' => $ok ? 'ok' : 'failed', 'verify_note' => implode("\n", $notes), 'drilled' => $drilled || ($ok && $run->drilled)])->save();

        return ['ok' => $ok, 'notes' => $notes];
    }

    /** Build a scratch database, load the backup, compare counts. */
    protected function drill(ZipArchive $zip, array $manifest, ?string $connection): array
    {
        $tmp = null;
        if ($connection === null) {
            $tmp = tempnam(sys_get_temp_dir(), 'drill').'.sqlite';
            touch($tmp);
            $connection = 'drill';
            config(['database.connections.drill' => ['driver' => 'sqlite', 'database' => $tmp, 'prefix' => '', 'foreign_key_constraints' => false]]);
            DB::purge('drill');
        }
        try {
            $code = Artisan::call('migrate', ['--database' => $connection, '--force' => true]);
            if ($code !== 0) {
                return ['ok' => false, 'notes' => ['The scratch database could not be built from the migrations: '.trim(Artisan::output())]];
            }
            $counts = $this->import($zip, $manifest, $connection, truncate: false);
            $notes = [];
            $ok = true;
            foreach ((array) $manifest['tables'] as $table => $expected) {
                $actual = $counts[$table] ?? null;
                if ($actual !== $expected) {
                    $ok = false;
                    $notes[] = "Table {$table}: backup has {$expected} rows, restore gave ".($actual ?? 'none').'.';
                }
            }
            $notes[] = $ok ? 'Restore drill OK: '.count((array) $manifest['tables']).' tables, '.array_sum((array) $manifest['tables']).' rows restored into a scratch database.' : 'Restore drill FAILED.';

            return ['ok' => $ok, 'notes' => $notes];
        } finally {
            DB::purge($connection);
            if ($tmp) {
                @unlink($tmp);
            }
        }
    }

    // ───────────────────────────── restore ─────────────────────────────

    /**
     * Load the data of a backup into a connection. The tables must already exist (run the migrations first).
     * Only columns that exist in the target are loaded, so a backup from an older version restores into a newer schema.
     *
     * @return array<string,int> rows loaded per table
     */
    public function import(ZipArchive $zip, array $manifest, string $connection, bool $truncate): array
    {
        $db = DB::connection($connection);
        $schema = Schema::connection($connection);
        $schema->disableForeignKeyConstraints();
        $counts = [];
        try {
            foreach (array_keys((array) $manifest['tables']) as $table) {
                if (! $schema->hasTable($table)) {
                    continue;
                }
                if ($truncate) {
                    $db->table($table)->delete();
                }
                $columns = array_flip($schema->getColumnListing($table));
                $stream = $zip->getStream('data/'.$table.'.jsonl');
                $batch = [];
                $n = 0;
                while ($stream && ($line = fgets($stream)) !== false) {
                    $row = json_decode($line, true);
                    if (! is_array($row)) {
                        continue;
                    }
                    $batch[] = array_intersect_key($row, $columns);
                    $n++;
                    if (count($batch) >= 200) {
                        $db->table($table)->insert($batch);
                        $batch = [];
                    }
                }
                if ($batch) {
                    $db->table($table)->insert($batch);
                }
                if ($stream) {
                    fclose($stream);
                }
                $counts[$table] = $n;
            }
        } finally {
            $schema->enableForeignKeyConstraints();
        }

        return $counts;
    }

    /** Restore a backup over the CURRENT database (and files). Destructive: every table in the backup is emptied first. */
    public function restore(BackupRun $run, bool $withFiles = true): array
    {
        $check = $this->verify($run, false);
        if (! $check['ok']) {
            throw new RuntimeException('The backup did not pass verification: '.implode(' ', $check['notes']));
        }
        $zip = $this->open(Storage::disk((string) config('backup.disk'))->path($run->file_path));
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $counts = $this->import($zip, $manifest, DB::getDefaultConnection(), truncate: true);

        $files = 0;
        if ($withFiles) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! str_starts_with($name, 'files/') || str_contains($name, '..')) {
                    continue;
                }
                $dest = storage_path('app/'.substr($name, 6));
                File::ensureDirectoryExists(dirname($dest));
                file_put_contents($dest, $zip->getFromIndex($i));
                $files++;
            }
        }
        $zip->close();

        return ['tables' => count($counts), 'rows' => array_sum($counts), 'files' => $files];
    }

    protected function open(string $path): ZipArchive
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The backup file cannot be opened.');
        }
        if ($pass = (string) config('backup.passphrase')) {
            $zip->setPassword($pass);
        }

        return $zip;
    }

    /** Register a backup zip from anywhere on the server (disaster recovery on a fresh install): it is copied into the backups folder. */
    public function adopt(string $source): BackupRun
    {
        if (! is_file($source)) {
            throw new RuntimeException('File not found: '.$source);
        }
        $disk = Storage::disk((string) config('backup.disk'));
        $relative = trim((string) config('backup.folder'), '/').'/imported-'.now()->format('Ymd-His').'-'.basename($source);
        $disk->makeDirectory(trim((string) config('backup.folder'), '/'));
        copy($source, $disk->path($relative));

        return BackupRun::query()->create(['trigger' => 'import', 'status' => 'done', 'file_path' => $relative, 'bytes' => filesize($source), 'checksum' => hash_file('sha256', $source),
            'encrypted' => (string) config('backup.passphrase') !== '', 'started_at' => now(), 'finished_at' => now()]);
    }

    // ───────────────────────────── housekeeping ─────────────────────────────

    /** Delete backups older than keep_days, always keeping the newest keep_min good ones. @return int removed */
    public function prune(): int
    {
        $keep = BackupRun::query()->good()->orderByDesc('finished_at')->limit((int) config('backup.keep_min', 3))->pluck('id');
        $old = BackupRun::query()->whereNotIn('id', $keep)->where('created_at', '<', now()->subDays((int) config('backup.keep_days', 30)))->get();
        foreach ($old as $run) {
            $this->delete($run);
        }

        return $old->count();
    }

    public function delete(BackupRun $run): void
    {
        if ($run->file_path) {
            Storage::disk((string) config('backup.disk'))->delete($run->file_path);
        }
        $run->delete();
    }

    public function path(BackupRun $run): ?string
    {
        $disk = Storage::disk((string) config('backup.disk'));

        return $run->file_path && $disk->exists($run->file_path) ? $disk->path($run->file_path) : null;
    }

    public function latestGood(): ?BackupRun
    {
        return BackupRun::query()->good()->orderByDesc('finished_at')->first();
    }
}
