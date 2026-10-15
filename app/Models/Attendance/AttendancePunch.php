<?php

namespace App\Models\Attendance;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Raw device punch (append-only log; unique per branch + person code + timestamp). */
class AttendancePunch extends Model
{
    use BelongsToBranch;

    protected $fillable = ['attendance_device_id', 'person_code', 'person_type', 'person_id', 'punched_at', 'result', 'message'];

    protected function casts(): array
    {
        return ['punched_at' => 'datetime', 'person_id' => 'integer', 'attendance_device_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }
}
