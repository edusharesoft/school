<?php

namespace App\Models\Students;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\StudyGroup;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per student per academic session: class, section, group, roll and outcome. */
class StudentEnrollment extends Model
{
    use Auditable, BelongsToBranch;

    public const STATUSES = ['enrolled', 'promoted', 'repeated', 'transferred', 'withdrawn', 'completed'];

    protected $fillable = [
        'student_id', 'academic_session_id', 'school_class_id', 'section_id', 'study_group_id',
        'roll_no', 'status', 'enrolled_on', 'left_on', 'previous_enrollment_id',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_on' => 'date',
            'left_on' => 'date',
            'roll_no' => 'integer',
            'student_id' => 'integer',
            'academic_session_id' => 'integer',
            'school_class_id' => 'integer',
            'section_id' => 'integer',
            'study_group_id' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_enrollment_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'enrolled');
    }
}
