<?php

/*
|--------------------------------------------------------------------------
| Application backups (Part 7)
|--------------------------------------------------------------------------
| `php artisan backup:run` writes ONE zip: every database table as JSON lines (portable between MySQL / MariaDB /
| SQLite — restoring needs only the migrations), the uploaded files, and a manifest with row counts and SHA-256 sums.
| `backup:verify --drill` proves the backup can be restored: it builds an empty scratch database from the migrations,
| loads the backup into it and compares every table's row count with the manifest. Do this regularly — a backup that
| was never restored is only a hope. The server-level mysqldump + GPG script in deploy/scripts/backup.sh stays as
| a second, independent copy.
*/

return [
    'disk' => env('BACKUP_DISK', 'local'),
    'folder' => 'backups',

    // Directories under storage/ that are put into the zip (uploads of every branch). Temporary things are skipped.
    'file_roots' => ['app/private', 'app/public'],
    'file_exclude' => ['backups', 'exports', 'framework', 'tmp'],
    'include_files' => (bool) env('BACKUP_INCLUDE_FILES', true),

    // Transient tables that are not worth saving.
    'skip_tables' => ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'],

    // Encrypt the zip with AES-256 when a passphrase is set (keep it somewhere other than the server!).
    'passphrase' => env('BACKUP_PASSPHRASE'),

    // Retention: delete older than N days but always keep the newest `keep_min` good backups.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
    'keep_min' => 3,

    // The monitor warns when the newest good backup is older than this many hours.
    'max_age_hours' => 36,
];
