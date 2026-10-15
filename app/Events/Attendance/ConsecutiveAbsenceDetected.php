<?php

namespace App\Events\Attendance;

use App\Models\Attendance\AttendanceAlert;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised once per new consecutive-absence streak. The notification hub (Part 5) listens to this event
 * to send the guardian SMS / e-mail / in-app message and then stamps AttendanceAlert::notified_at.
 */
class ConsecutiveAbsenceDetected
{
    use Dispatchable;

    public function __construct(public AttendanceAlert $alert) {}
}
