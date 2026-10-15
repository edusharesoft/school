<?php

namespace App\Listeners\Notifications;

use App\Events\Finance\FeeInvoiceIssued;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\RecipientResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class NotifyInvoiceIssued implements ShouldHandleEventsAfterCommit
{
    use NotifiesSafely;

    public function __construct(protected NotificationHub $hub, protected RecipientResolver $recipients) {}

    public function handle(FeeInvoiceIssued $event): void
    {
        $this->safely(function () use ($event) {
            $invoice = $event->invoice->loadMissing('student');
            if ($invoice->balance <= 0) {
                return;   // fully covered by advance credit — nothing to ask for
            }
            $vars = [
                'invoice_no' => $invoice->invoice_no, 'amount' => $this->money($invoice->balance), 'due_date' => $invoice->due_date->format('d/m/Y'),
                'month' => $invoice->period ? CarbonImmutable::createFromFormat('Y-m-d', $invoice->period.'-01')->format('F Y') : $invoice->title,
            ];
            $this->hub->notify('fee.invoice', $this->recipients->guardiansOf($invoice->student, $vars), $vars, $invoice, ['url' => $this->portalUrl('/parent')]);
        });
    }
}
