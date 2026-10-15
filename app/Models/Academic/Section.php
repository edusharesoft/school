<?php

namespace App\Models\Academic;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use Auditable, BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['school_class_id', 'name', 'shift', 'capacity', 'class_teacher_id', 'room_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'school_class_id' => 'integer',
            'class_teacher_id' => 'integer',
            'room_id' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /** Active students in this section for a session. */
    public function activeCount(int $sessionId): int
    {
        return $this->enrollments()->where('academic_session_id', $sessionId)->where('status', 'enrolled')->count();
    }

    public function label(): string
    {
        return ($this->schoolClass?->displayName() ?? '').' — '.$this->name;
    }
}
