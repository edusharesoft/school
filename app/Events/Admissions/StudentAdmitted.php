<?php

namespace App\Events\Admissions;

use App\Models\Students\Student;
use Illuminate\Foundation\Events\Dispatchable;

/** An applicant was admitted and enrolled. The guardians get the confirmation. */
class StudentAdmitted
{
    use Dispatchable;

    public function __construct(public Student $student) {}
}
