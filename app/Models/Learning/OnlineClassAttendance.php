<?php

namespace App\Models\Learning;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineClassAttendance extends Model
{
    use BelongsToBranch;

    public const STATUSES = ['present', 'late', 'absent', 'excused'];

    protected $fillable = ['student_id', 'status'];

    protected function casts(): array
    {
        return ['marked_at' => 'datetime', 'student_id' => 'integer', 'online_class_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function onlineClass(): BelongsTo
    {
        return $this->belongsTo(OnlineClass::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
