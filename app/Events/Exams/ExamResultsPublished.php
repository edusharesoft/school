<?php

namespace App\Events\Exams;

use App\Models\Exams\Examination;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when an exam's results are published. The notification hub (Part 5) listens to this event to
 * send students and guardians their result (SMS / e-mail / in-app).
 */
class ExamResultsPublished
{
    use Dispatchable;

    public function __construct(public Examination $exam) {}
}
