# Operations runbook (Part 7)

Everything the platform owner needs to run the system after go-live: scheduler and queue, backups and restore drills,
monitoring, two-factor sign-in, subscriptions and performance.

## 1. Processes that must be running

| Process | How | Why |
|---|---|---|
| Web server + PHP-FPM | nginx / Apache (see `deploy/`) | the application |
| **Scheduler** | cron, every minute: `* * * * * cd /var/www/school && php artisan schedule:run >> /dev/null 2>&1` | notices, fee reminders, library / asset reminders, nightly backup, weekly restore drill, health checks, export clean-up |
| **Queue worker** | Supervisor (`deploy/supervisor/school-worker.conf`) or `php artisan queue:work` | background report exports, SMS / e-mail / WhatsApp, "Back up now" button |
| MySQL 8 / MariaDB 10.6 | | data |

The **System health** page (*Central → System → System health*) turns amber when the scheduler heartbeat or the queue is
not working.

## 2. Backups

* `php artisan backup:run` (also every night at 02:20, kept 30 days, newest 3 always kept) writes one zip to
  `storage/app/private/backups/`: every table as JSON lines, all uploaded files, and `manifest.json` (row counts + SHA-256).
* Set `BACKUP_PASSPHRASE` in `.env` to encrypt the zip with AES-256. **Store the passphrase outside the server** (password
  manager, printed in the safe) — without it the backup cannot be opened.
* **Copy the backups off the server** (the zip lives on the same disk as the database otherwise). Examples:
  `rclone sync /var/www/school/storage/app/private/backups remote:school-backups` from a nightly cron at 03:00, or mount an
  S3 / Backblaze disk and set `BACKUP_DISK`.
* `deploy/scripts/backup.sh` (mysqldump + GPG) is an independent second copy; keep both.

### Restore drill — do it monthly (it also runs by itself every Sunday 04:00)

```bash
php artisan backup:verify --drill          # newest good backup
php artisan backup:verify 12 --drill       # a specific one
```

The drill builds an empty scratch database from the migrations, loads the backup into it and compares the row count of
**every table** with the manifest. It prints `Restore drill OK …` or the tables that differ. The result is shown on the
Backups page ("Restore tested") and the health page warns if no drill succeeded in the last 14 days. To drill into a real
scratch MySQL database instead of a temporary SQLite file: create an empty database, add a connection for it in
`config/database.php` and run `backup:verify --drill --connection=scratch`.

### Real restore (disaster)

1. Install the same version of the code (`git checkout <tag>`), `composer install --no-dev`, copy `.env` (same `APP_KEY`!
   Encrypted columns — two-factor secrets, gateway keys — cannot be read with a different key).
2. Create an **empty** database and run `php artisan migrate --force`.
3. `php artisan backup:restore /path/to/backup-20261201-022000-7.zip --force` (a backup id also works when the `backup_runs`
   row exists). The file is copied into the backups folder, its checksums are verified, every table in the backup is emptied
   and refilled, and the files are copied back. Use `--no-files` to restore the database only.
4. Sign in and check *Backups* and *System health*.
5. `php artisan optimize:clear && php artisan queue:restart`.

Target times: restore of a 1 GB database ≈ 10–20 minutes. Record your own drill duration in the log below.

| Date | Backup # | Duration | Result | By |
|---|---|---|---|---|
| | | | | |

## 3. Monitoring

* `HEALTH_TOKEN=<long random string>` in `.env`, then point an uptime monitor (UptimeRobot, Better Stack …) at
  `https://<central-domain>/health/status?token=<HEALTH_TOKEN>`. It answers **200** (ok / warn) or **503** (a check failed);
  the body lists each check as ok / warn / fail. Without the token the URL is a 404.
* `MONITOR_ALERT_EMAIL=you@example.com`: `php artisan monitor:check` (every 10 minutes) e-mails when a check **fails**
  (database down, disk < 5 %, ≥ 50 failed jobs, scheduler silent for 30 minutes) — at most once per 6 hours per problem.
* Checks: database, cache, storage (+ free disk), queue (failed jobs, waiting time), scheduler heartbeat, newest backup
  age (36 h), last successful restore drill (14 days), subscriptions, configuration (debug / https / sync queue in
  production), slow-query count.
* Logs: `storage/logs/laravel-*.log` (daily, 14 days). Failed jobs: `php artisan queue:failed`, `queue:retry all`.

## 4. Performance

* `php artisan performance:audit` lists `*_id` / `status` columns that no index covers and the biggest tables. The
  indexes it found are added by migration `2026_12_01_000400_add_performance_indexes` (safe to re-run).
* Slow queries: set `PERF_SLOW_QUERY_MS=500` (default in production) — queries over the limit are logged as `Slow query`
  with their SQL, and counted on the health page.
* Production checklist: `APP_DEBUG=false`, `php artisan config:cache route:cache view:cache event:cache` (done by
  `deploy/scripts/deploy.sh`), OPcache on, `CACHE_STORE=redis` and `QUEUE_CONNECTION=redis` for more than a few branches,
  MySQL `innodb_buffer_pool_size` ≈ 60–70 % of the database server's RAM, one queue worker per ~5 branches sending SMS.
* Big lists are always paginated (25/page); reports show at most 300 rows on screen and exports above 3 000 rows run in the
  background. Branch data is isolated by `branch_id` with composite indexes starting with it.

## 5. Two-factor sign-in

* Every user can switch it on at *Account security* (profile menu): authenticator app (TOTP, RFC 6238) + 8 one-time
  recovery codes. The API login (`POST /api/v1/auth/login`) then also needs `"otp": "123456"`.
* Make it mandatory for roles: `SECURITY_2FA_REQUIRED_ROLES=super-admin,institution-admin` (comma separated). Those users
  are held at the Account security page until it is set up and cannot switch it off.
* Lost phone: the user signs in with a recovery code, or an administrator opens *Central → Users* and presses the
  shield button (*Reset two-factor*); the user sets it up again. Both are written to the audit log.
* Secrets are stored encrypted with `APP_KEY`; changing `APP_KEY` locks everyone with 2FA out (reset them as above).

## 6. Subscriptions (SaaS)

* *Central → Subscriptions → Plans* (platform owner only): price, branch limit, active-student limit, trial days, grace days.
* New institutions that register themselves start on the first active plan that has trial days. For others:
  *Subscriptions → Manage* → choose the plan (starts a trial) → **Record payment** after the money arrives; the period is
  extended from its end date (or from today if it already ran out).
* After the end date there is a **grace period** (everything works, a warning banner is shown), then the institution is
  **read-only**: users can see everything but every change is refused (HTTP 402 for the API). *Suspend* does the same at once.
  Nothing is deleted. Recording a payment or *Reactivate* restores full access immediately.
* Limits are checked when a student is admitted (active students of the whole institution) and when a branch is created.
  An institution without a subscription has no limits.
