<?php

namespace App\Events\Admissions;

use App\Models\Admissions\AdmissionApplication;
use Illuminate\Foundation\Events\Dispatchable;

/** An admission application was submitted (online or at the office). The applicant gets the application number. */
class AdmissionApplicationSubmitted
{
    use Dispatchable;

    public function __construct(public AdmissionApplication $application) {}
}
