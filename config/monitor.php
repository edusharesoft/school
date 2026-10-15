<?php

/*
|--------------------------------------------------------------------------
| Monitoring & performance (Part 7)
|--------------------------------------------------------------------------
| GET /health/status?token=…  → JSON for an uptime monitor (UptimeRobot, Better Stack …): HTTP 200 when ok/warn,
| 503 when something failed. Set HEALTH_TOKEN to enable it. `php artisan monitor:check` runs every 10 minutes
| and e-mails MONITOR_ALERT_EMAIL (at most once per 6 hours for the same problem).
*/

return [
    'token' => env('HEALTH_TOKEN'),
    'alert_email' => env('MONITOR_ALERT_EMAIL'),
    'alert_every_hours' => 6,

    'disk_warn_percent' => 15,
    'disk_fail_percent' => 5,
    'failed_jobs_warn' => 1,
    'failed_jobs_fail' => 50,
    'queue_wait_warn_minutes' => 10,
    'scheduler_warn_minutes' => 5,
    'scheduler_fail_minutes' => 30,
    'drill_warn_days' => 14,

    // Queries slower than this many milliseconds are written to the log (0 = off). A short count is shown on the health page.
    'slow_query_ms' => (int) env('PERF_SLOW_QUERY_MS', env('APP_ENV') === 'production' ? 500 : 0),
];
