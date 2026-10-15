<?php

namespace App\Models\Hostel;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use Auditable, BelongsToBranch;

    public const STATUSES = ['present', 'absent', 'leave', 'late'];

    protected $table = 'hostel_attendance';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'date' => 'date:Y-m-d'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Students\Student::class, 'student_id');
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class, 'hostel_id');
    }
}
