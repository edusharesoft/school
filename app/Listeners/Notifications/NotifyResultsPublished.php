<?php

namespace App\Listeners\Notifications;

use App\Events\Exams\ExamResultsPublished;
use App\Models\Exams\ExamResult;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\RecipientResolver;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class NotifyResultsPublished implements ShouldHandleEventsAfterCommit
{
    use NotifiesSafely;

    public function __construct(protected NotificationHub $hub, protected RecipientResolver $recipients) {}

    public function handle(ExamResultsPublished $event): void
    {
        $this->safely(function () use ($event) {
            $exam = $event->exam;
            ExamResult::query()->where('examination_id', $exam->id)->with('student.guardians.user:id,locale', 'student.user:id,locale')
                ->chunkById(200, function ($results) use ($exam) {
                    foreach ($results as $result) {
                        if (! $result->student) {
                            continue;
                        }
                        $vars = [
                            'exam' => $exam->name, 'gpa' => number_format((float) $result->gpa, 2), 'grade' => $result->grade,
                            'status' => __(ucfirst((string) $result->status)), 'position' => $result->class_position ?: '—',
                        ];
                        $recipients = $this->recipients->guardiansOf($result->student, $vars)->put('student', $this->recipients->student($result->student, $vars));
                        $this->hub->notify('result.published', $recipients, $vars, $result, ['url' => $this->portalUrl('/student/results')]);
                    }
                });
        });
    }
}
