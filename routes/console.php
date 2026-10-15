<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduler. Every tenant-aware task runs per branch via `tenancy:run` or TenantAware jobs.
| Times are Bangladesh local time (APP_TIMEZONE=Asia/Dhaka).
*/

Schedule::command('queue:prune-failed --hours=720')->daily()->at('02:10');
Schedule::command('sanctum:prune-expired --hours=48')->daily()->at('02:20');
Schedule::command('auth:clear-resets')->everyFifteenMinutes();

// Part 3B — consecutive-absence alerts after the school day (idempotent; re-run in the evening for late registers).
Schedule::command('tenancy:run "attendance:absence-alerts"')->dailyAt('14:30')->withoutOverlapping();
Schedule::command('tenancy:run "attendance:absence-alerts"')->dailyAt('19:00')->withoutOverlapping();

// Part 4 — fees: monthly invoices on the 1st (only branches with automatic generation on), nightly late fees. Both idempotent.
Schedule::command('tenancy:run "fees:generate-invoices"')->monthlyOn(1, '06:00')->withoutOverlapping();
Schedule::command('tenancy:run "fees:late-fees"')->dailyAt('02:30')->withoutOverlapping();

// Part 6 — library: uncollected reservations expire and overdue reminders go out every morning (idempotent).
Schedule::command('tenancy:run "library:housekeeping"')->dailyAt('07:30')->withoutOverlapping();

// Part 6b — transport & hostel monthly fee invoices (after the school fees, on the 1st; idempotent).
Schedule::command('tenancy:run "services:generate-fees"')->monthlyOn(1, '06:30')->withoutOverlapping();

// Part 5-1 — notification hub: scheduled notices & messages every minute; overdue-fee reminders in the morning (off unless enabled per branch).
Schedule::command('tenancy:run "notifications:dispatch"')->everyMinute()->withoutOverlapping();
Schedule::command('tenancy:run "notifications:fee-reminders"')->dailyAt('10:05')->withoutOverlapping();

// Part 5-2 — online payments: re-verify unfinished checkouts with their gateways, expire abandoned ones.
Schedule::command('tenancy:run "payments:reconcile"')->everyTenMinutes()->withoutOverlapping();

// Part 6c-2 — asset register: maintenance-due reminders every morning (each task is reminded at most once a week).
Schedule::command('tenancy:run "assets:housekeeping"')->dailyAt('07:45')->withoutOverlapping();

// Part 6e — reports centre: finished background exports are deleted after their keep-days (not tenant-bound, runs once).
Schedule::command('reports:housekeeping')->dailyAt('03:10')->withoutOverlapping();

// Part 7 — backups: nightly full backup (retention applied), weekly restore drill of the newest one, daily subscription check.
Schedule::command('backup:run --trigger=schedule')->dailyAt('02:20')->withoutOverlapping(120);
Schedule::command('backup:verify --drill')->weeklyOn(0, '04:00')->withoutOverlapping(120);

// Part 7 — monitoring: a heartbeat proves the scheduler runs; the health checks run every 10 minutes (alerts only on failures).
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::put('monitor.heartbeat', time(), 7200))->everyMinute()->name('monitor-heartbeat');
Schedule::command('monitor:check --quiet-ok')->everyTenMinutes()->withoutOverlapping();
