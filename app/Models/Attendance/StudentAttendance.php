<?php

namespace App\Models\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Subject;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** One row per student per date per slot ("day" = daily register, "sub:<id>" = subject period). */
class StudentAttendance extends Model
{
    use Auditable, BelongsToBranch;

    public const METHODS = ['manual', 'bulk', 'qr', 'id_card', 'biometric', 'online'];

    protected $fillable = [
        'academic_session_id', 'student_id', 'student_enrollment_id', 'school_class_id', 'section_id', 'subject_id',
        'slot', 'date', 'status', 'check_in_at', 'method', 'remarks', 'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
            'student_id' => 'integer',
            'student_enrollment_id' => 'integer',
            'academic_session_id' => 'integer',
            'school_class_id' => 'integer',
            'section_id' => 'integer',
            'subject_id' => 'integer',
            'marked_by' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public static function slotFor(?int $subjectId): string
    {
        return $subjectId ? 'sub:'.$subjectId : 'day';
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function corrections(): MorphMany
    {
        return $this->morphMany(AttendanceCorrection::class, 'attendable');
    }

    public function describe(): string
    {
        return $this->student?->name.' — '.$this->date?->toDateString().($this->subject ? ' ('.$this->subject->name.')' : '');
    }
}
