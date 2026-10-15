<?php

namespace App\Models\Learning;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Subject;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Homework, assignment or project given to a class (or one section of it). */
class Assignment extends Model
{
    use Auditable, BelongsToBranch;

    public const KINDS = ['homework', 'assignment', 'project'];

    public const MODES = ['either', 'file', 'text', 'none'];

    protected $fillable = ['school_class_id', 'section_id', 'subject_id', 'kind', 'title', 'instructions', 'due_at', 'max_marks', 'submission_mode', 'allow_late'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime', 'published_at' => 'datetime', 'max_marks' => 'decimal:2', 'allow_late' => 'boolean',
            'school_class_id' => 'integer', 'section_id' => 'integer', 'subject_id' => 'integer', 'academic_session_id' => 'integer',
            'created_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer',
        ];
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /** Visible to students: published or closed (closed ones stay readable, they just stop accepting work). */
    public function scopeVisibleToStudents(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'closed']);
    }

    public function scopeForEnrollment(Builder $query, StudentEnrollment $enrollment): Builder
    {
        return $query->where('academic_session_id', $enrollment->academic_session_id)
            ->where('school_class_id', $enrollment->school_class_id)
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $enrollment->section_id));
    }

    public function isGraded(): bool
    {
        return $this->max_marks !== null;
    }

    public function isPastDue(): bool
    {
        return $this->due_at->isPast();
    }

    /** Can this student hand work in (or hand it in again) right now? */
    public function acceptsWorkFrom(?AssignmentSubmission $submission): bool
    {
        if ($this->status !== 'published' || $this->submission_mode === 'none') {
            return false;
        }
        if ($submission?->status === 'evaluated') {
            return false;
        }
        if ($submission?->status === 'returned') {
            return true;   // the teacher asked for a new version
        }

        return ! $this->isPastDue() || $this->allow_late;
    }

    /** Students this assignment is meant for (enrolled in the session, class and section). */
    public function audience(): Builder
    {
        $enrolled = StudentEnrollment::query()->where('academic_session_id', $this->academic_session_id)
            ->where('school_class_id', $this->school_class_id)->where('status', 'enrolled')
            ->when($this->section_id, fn ($q, $s) => $q->where('section_id', $s))->select('student_id');

        return Student::query()->whereIn('id', $enrolled);
    }

    public function kindLabel(): string
    {
        return __(ucfirst($this->kind));
    }
}
