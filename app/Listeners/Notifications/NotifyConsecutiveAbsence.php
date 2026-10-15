<?php

namespace App\Listeners\Notifications;

use App\Events\Attendance\ConsecutiveAbsenceDetected;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\RecipientResolver;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class NotifyConsecutiveAbsence implements ShouldHandleEventsAfterCommit
{
    use NotifiesSafely;

    public function __construct(protected NotificationHub $hub, protected RecipientResolver $recipients) {}

    public function handle(ConsecutiveAbsenceDetected $event): void
    {
        $this->safely(function () use ($event) {
            $alert = $event->alert->loadMissing(['student', 'section.schoolClass']);
            $vars = [
                'days' => $alert->streak_days, 'since' => $alert->first_absent_on->format('d/m/Y'),
                'class' => trim(($alert->section?->schoolClass?->name ?? '').' '.($alert->section?->name ?? '')),
            ];
            $this->hub->notify('attendance.absent', $this->recipients->guardiansOf($alert->student, $vars), $vars, $alert);
            $alert->forceFill(['notified_at' => now()])->save();
        });
    }
}
