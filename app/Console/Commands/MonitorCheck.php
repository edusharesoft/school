<?php

namespace App\Console\Commands;

use App\Services\Monitoring\HealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MonitorCheck extends Command
{
    protected $signature = 'monitor:check {--quiet-ok : Print nothing when everything is fine}';

    protected $description = 'Run the health checks, log problems and e-mail MONITOR_ALERT_EMAIL (once per 6 hours per problem)';

    public function handle(HealthService $health): int
    {
        $result = $health->run();
        Cache::put('monitor.last', $result, 86400);
        $bad = collect($result['checks'])->where('status', '!=', HealthService::OK);

        if (! $this->option('quiet-ok') || $bad->isNotEmpty()) {
            foreach ($result['checks'] as $c) {
                $this->line(sprintf('%-6s %-16s %s', strtoupper($c['status']), $c['label'], $c['detail']));
            }
        }
        if ($bad->isEmpty()) {
            return self::SUCCESS;
        }

        $failing = $bad->where('status', HealthService::FAIL);
        Log::log($failing->isNotEmpty() ? 'critical' : 'warning', 'Health check: '.$bad->map(fn ($c) => $c['key'].'='.$c['status'])->implode(', '));

        $to = config('monitor.alert_email');
        if ($to && $failing->isNotEmpty() && Cache::add('monitor.alert.'.md5($failing->pluck('key')->implode(',')), 1, now()->addHours((int) config('monitor.alert_every_hours', 6)))) {
            try {
                Mail::raw(config('app.name')." — health check FAILED\n\n".$failing->map(fn ($c) => '- '.$c['label'].': '.$c['detail'])->implode("\n"), fn ($m) => $m->to($to)->subject('['.config('app.name').'] Health check failed'));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $failing->isNotEmpty() ? self::FAILURE : self::SUCCESS;
    }
}
