<?php

namespace App\Listeners\Notifications;

use App\Events\Finance\FeePaymentReceived;
use App\Models\Finance\FeeInvoice;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\RecipientResolver;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class NotifyPaymentReceived implements ShouldHandleEventsAfterCommit
{
    use NotifiesSafely;

    public function __construct(protected NotificationHub $hub, protected RecipientResolver $recipients) {}

    public function handle(FeePaymentReceived $event): void
    {
        $this->safely(function () use ($event) {
            $payment = $event->payment->loadMissing('student');
            $student = $payment->student;
            $balance = (float) FeeInvoice::query()->open()->where('student_id', $student->id)->sum('balance');
            $vars = [
                'receipt_no' => $payment->receipt_no, 'amount' => $this->money($payment->amount), 'date' => $payment->date->format('d/m/Y'),
                'balance' => $this->money($balance),
            ];
            $this->hub->notify('payment.received', $this->recipients->guardiansOf($student, $vars), $vars, $payment, ['url' => $this->portalUrl('/parent')]);
        });
    }
}
