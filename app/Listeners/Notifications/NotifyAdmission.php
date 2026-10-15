<?php

namespace App\Listeners\Notifications;

use App\Events\Admissions\AdmissionApplicationSubmitted;
use App\Events\Admissions\StudentAdmitted;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RecipientResolver;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Application received (to the applicant's contact number / e-mail) and admission confirmed (to the guardians). */
class NotifyAdmission implements ShouldHandleEventsAfterCommit
{
    use NotifiesSafely;

    public function __construct(protected NotificationHub $hub, protected RecipientResolver $recipients) {}

    public function handleSubmitted(AdmissionApplicationSubmitted $event): void
    {
        $this->safely(function () use ($event) {
            $application = $event->application->loadMissing('campaign.schoolClass');
            $vars = ['application_no' => $application->application_no, 'class' => $application->campaign?->schoolClass?->name];
            $recipient = new Recipient('applicant', $application->id, $application->name, $application->phone ?: $application->guardian_phone, $application->email, null, null, $vars);
            $this->hub->notify('admission.received', [$recipient], $vars, $application, ['url' => $this->portalUrl('/admission/status')]);
        });
    }

    public function handleAdmitted(StudentAdmitted $event): void
    {
        $this->safely(function () use ($event) {
            $student = $event->student->loadMissing('currentEnrollment.schoolClass', 'currentEnrollment.section');
            $enrollment = $student->currentEnrollment;
            $vars = [
                'student_code' => $student->student_code, 'class' => $enrollment?->schoolClass?->name, 'section' => $enrollment?->section?->name,
                'roll' => $enrollment?->roll_no ?: '—',
            ];
            $this->hub->notify('admission.confirmed', $this->recipients->guardiansOf($student, $vars), $vars, $student, ['url' => $this->portalUrl('/parent')]);
        });
    }
}
