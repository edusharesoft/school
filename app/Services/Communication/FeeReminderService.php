<?php

namespace App\Services\Communication;

use App\Models\Finance\FeeInvoice;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\NotificationSettings;
use App\Services\Notifications\RecipientResolver;
use Carbon\CarbonImmutable;

/**
 * Outstanding-fee reminders (scheduler, daily): guardians of students whose oldest open invoice is at least
 * "fee_reminder_days" past its due date. At most one reminder per student per week (ISO week in the de-duplication key).
 */
class FeeReminderService
{
    public function __construct(protected NotificationHub $hub, protected RecipientResolver $recipients, protected NotificationSettings $settings) {}

    public function run(?CarbonImmutable $today = null): int
    {
        if (! $this->settings->get('fee_reminder_enabled', false)) {
            return 0;
        }
        $today ??= CarbonImmutable::today();
        $cutoff = $today->subDays(max(0, (int) $this->settings->get('fee_reminder_days', 3)));
        $week = $today->format('o-\WW');
        $sent = 0;

        FeeInvoice::query()->open()->whereDate('due_date', '<=', $cutoff->toDateString())->with('student')->get()->groupBy('student_id')
            ->each(function ($invoices) use ($week, &$sent) {
                $oldest = $invoices->sortBy('due_date')->first();
                if (! $oldest->student || $oldest->student->status !== 'active') {
                    return;
                }
                $vars = [
                    'amount' => number_format((float) $invoices->sum('balance'), 2), 'invoices' => $invoices->count(),
                    'oldest_due' => $oldest->due_date->format('d/m/Y'),
                ];
                $stats = $this->hub->notify('fee.due', $this->recipients->guardiansOf($oldest->student, $vars), $vars, $oldest->student,
                    ['dedupe_suffix' => $week, 'url' => tenant()->branch()?->url('/parent')]);
                $sent += $stats['created'];
            });

        return $sent;
    }
}
