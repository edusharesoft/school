<?php

namespace App\Services\Monitoring;

use App\Models\Saas\BackupRun;
use App\Models\Saas\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Cheap self-checks of the things that break a school SaaS quietly: database, cache, disk, queue, scheduler, backups. */
class HealthService
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /** @return array{status:string, at:string, checks: array<int,array{key:string,label:string,status:string,detail:string}>} */
    public function run(): array
    {
        $checks = [];
        foreach (['database', 'cache', 'storage', 'queue', 'scheduler', 'backup', 'drill', 'subscriptions', 'config', 'slowQueries'] as $name) {
            try {
                $checks[] = $this->{'check'.ucfirst($name)}();
            } catch (\Throwable $e) {
                $checks[] = $this->item($name, $name, self::FAIL, $e->getMessage());
            }
        }
        $order = [self::OK => 0, self::WARN => 1, self::FAIL => 2];
        $worst = collect($checks)->max(fn ($c) => $order[$c['status']]);

        return ['status' => array_search($worst, $order, true), 'at' => now()->toIso8601String(), 'checks' => $checks];
    }

    protected function item(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }

    protected function checkDatabase(): array
    {
        $t = microtime(true);
        DB::select('select 1');
        $ms = (int) round((microtime(true) - $t) * 1000);

        return $this->item('database', __('Database'), $ms > 300 ? self::WARN : self::OK, __('Answers in :ms ms.', ['ms' => $ms]));
    }

    protected function checkCache(): array
    {
        $key = 'monitor.probe.'.bin2hex(random_bytes(3));
        Cache::put($key, 'x', 30);
        $ok = Cache::get($key) === 'x';
        Cache::forget($key);

        return $this->item('cache', __('Cache'), $ok ? self::OK : self::FAIL, $ok ? __('Reads and writes work.') : __('The cache does not keep values.'));
    }

    protected function checkStorage(): array
    {
        $dir = storage_path('app/tmp');
        @mkdir($dir, 0775, true);
        $probe = $dir.'/probe-'.bin2hex(random_bytes(3));
        $writable = @file_put_contents($probe, 'x') !== false;
        @unlink($probe);
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        $pct = $free && $total ? round($free / $total * 100, 1) : null;
        if (! $writable) {
            return $this->item('storage', __('Storage'), self::FAIL, __('The storage folder is not writable.'));
        }
        $status = $pct === null ? self::OK : ($pct < config('monitor.disk_fail_percent') ? self::FAIL : ($pct < config('monitor.disk_warn_percent') ? self::WARN : self::OK));

        return $this->item('storage', __('Storage'), $status, $pct === null ? __('Writable.') : __('Writable, :p% of the disk is free (:g GB).', ['p' => $pct, 'g' => number_format($free / 1073741824, 1)]));
    }

    protected function checkQueue(): array
    {
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $waitMin = 0;
        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            $oldest = DB::table('jobs')->min('created_at');
            $waitMin = $oldest ? (int) floor((time() - $oldest) / 60) : 0;
        }
        $status = $failed >= config('monitor.failed_jobs_fail') ? self::FAIL : (($failed >= config('monitor.failed_jobs_warn') || $waitMin >= config('monitor.queue_wait_warn_minutes')) ? self::WARN : self::OK);
        $detail = __(':n failed job(s).', ['n' => $failed]).($waitMin >= config('monitor.queue_wait_warn_minutes') ? ' '.__('The oldest waiting job is :m minutes old — is the queue worker running?', ['m' => $waitMin]) : '');

        return $this->item('queue', __('Queue'), $status, $detail);
    }

    protected function checkScheduler(): array
    {
        $beat = Cache::get('monitor.heartbeat');
        if (! $beat) {
            return $this->item('scheduler', __('Scheduler'), self::WARN, __('No heartbeat yet — is the every-minute cron (schedule:run) set up?'));
        }
        $age = (int) floor((time() - $beat) / 60);
        $status = $age >= config('monitor.scheduler_fail_minutes') ? self::FAIL : ($age >= config('monitor.scheduler_warn_minutes') ? self::WARN : self::OK);

        return $this->item('scheduler', __('Scheduler'), $status, __('Last run :m minute(s) ago.', ['m' => $age]));
    }

    protected function checkBackup(): array
    {
        $last = BackupRun::query()->good()->orderByDesc('finished_at')->first();
        if (! $last) {
            return $this->item('backup', __('Backup'), self::WARN, __('No backup has been made yet.'));
        }
        $hours = (int) floor($last->finished_at->diffInMinutes(now()) / 60);
        $status = $hours > config('backup.max_age_hours', 36) ? self::WARN : self::OK;

        return $this->item('backup', __('Backup'), $status, __('Newest backup is :h hour(s) old (:s).', ['h' => $hours, 's' => $last->verify_status === 'ok' ? __('verified') : __('not verified yet')]));
    }

    protected function checkDrill(): array
    {
        $drill = BackupRun::query()->good()->where('drilled', true)->where('verify_status', 'ok')->orderByDesc('verified_at')->first();
        if (! $drill) {
            return $this->item('drill', __('Restore drill'), self::WARN, __('No backup has ever been test-restored. Run: php artisan backup:verify --drill'));
        }
        $days = (int) floor($drill->verified_at->diffInHours(now()) / 24);

        return $this->item('drill', __('Restore drill'), $days > config('monitor.drill_warn_days') ? self::WARN : self::OK, __('Last successful test restore :d day(s) ago.', ['d' => $days]));
    }

    protected function checkSubscriptions(): array
    {
        $bad = Subscription::query()->with('plan')->get()->filter(fn ($s) => in_array($s->phase(), ['grace', 'expired'], true))->count();

        return $this->item('subscriptions', __('Subscriptions'), self::OK, $bad ? __(':n institution(s) are past their end date.', ['n' => $bad]) : __('All subscriptions are in order.'));
    }

    protected function checkConfig(): array
    {
        $problems = [];
        if (app()->isProduction() && config('app.debug')) {
            $problems[] = __('APP_DEBUG is on in production.');
        }
        if (app()->isProduction() && ! str_starts_with((string) config('app.url'), 'https://')) {
            $problems[] = __('APP_URL is not https.');
        }
        if (app()->isProduction() && config('queue.default') === 'sync') {
            $problems[] = __('QUEUE_CONNECTION is sync: background exports run inside the web request.');
        }

        return $this->item('config', __('Configuration'), $problems ? self::WARN : self::OK, $problems ? implode(' ', $problems) : __('No problems found.'));
    }

    protected function checkSlowQueries(): array
    {
        $limit = (int) config('monitor.slow_query_ms');
        if ($limit <= 0) {
            return $this->item('slowQueries', __('Slow queries'), self::OK, __('Logging is off (set PERF_SLOW_QUERY_MS).'));
        }
        $n = (int) Cache::get('monitor.slow.'.now()->format('YmdH'), 0);

        return $this->item('slowQueries', __('Slow queries'), $n > 50 ? self::WARN : self::OK, __(':n quer(ies) over :ms ms this hour — see the log.', ['n' => $n, 'ms' => $limit]));
    }
}
