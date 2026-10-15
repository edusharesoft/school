<?php

namespace App\Jobs;

use App\Jobs\Concerns\TenantAware;
use App\Services\Notifications\NotificationHub;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one notification_messages row. Safe to run twice: the hub claims the row (queued → sending) atomically.
 * Transient provider errors are retried with back-off (config notifications.backoff); the hub marks the row
 * failed after notifications.max_attempts.
 */
class SendNotificationMessage implements ShouldQueue
{
    use Queueable, TenantAware;

    public int $tries = 10;       // releases for retries count as attempts; the hub's own counter decides

    public int $timeout = 60;

    public function __construct(public int $messageId)
    {
        $this->captureTenant();
    }

    public function handle(NotificationHub $hub): void
    {
        if ($hub->process($this->messageId) === 'retry') {
            $backoff = (array) config('notifications.backoff', [60, 300, 900]);
            $attempts = (int) \App\Models\Communication\NotificationMessage::query()->whereKey($this->messageId)->value('attempts');
            $this->release($backoff[min(max($attempts - 1, 0), count($backoff) - 1)] ?? 300);
        }
    }
}
