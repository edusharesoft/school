<?php

namespace App\Listeners\Notifications;

/** A notification problem must never undo or block the action that triggered it (receipt, admission …). */
trait NotifiesSafely
{
    protected function safely(\Closure $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2);
    }

    protected function portalUrl(string $path): ?string
    {
        return tenant()->branch()?->url($path);
    }
}
