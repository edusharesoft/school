<?php

namespace App\Models\Academic;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Section × day × period of a session → subject, teacher and room. */
class TimetableEntry extends Model
{
    use Auditable, BelongsToBranch;

    /** ISO day numbers used in routines (Saturday-first school week in Bangladesh). */
    public const WEEK = [6, 7, 1, 2, 3, 4, 5];

    protected $fillable = ['academic_session_id', 'section_id', 'period_id', 'day_of_week', 'subject_id', 'teacher_id', 'room_id', 'note'];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'academic_session_id' => 'integer',
            'section_id' => 'integer',
            'period_id' => 'integer',
            'subject_id' => 'integer',
            'teacher_id' => 'integer',
            'room_id' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public static function dayName(int $day): string
    {
        return __([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'][$day] ?? '');
    }
}
