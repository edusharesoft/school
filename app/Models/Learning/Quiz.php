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

/** An online quiz for a class (or one section of it), graded automatically. */
class Quiz extends Model
{
    use Auditable, BelongsToBranch;

    public const REVIEW = ['never', 'after_submit', 'after_close'];

    protected $fillable = ['school_class_id', 'section_id', 'subject_id', 'title', 'instructions', 'time_limit_minutes', 'max_attempts', 'available_from', 'available_until', 'shuffle_questions', 'show_answers', 'pass_percent'];

    protected function casts(): array
    {
        return [
            'available_from' => 'datetime', 'available_until' => 'datetime', 'published_at' => 'datetime', 'shuffle_questions' => 'boolean',
            'pass_percent' => 'decimal:2', 'time_limit_minutes' => 'integer', 'max_attempts' => 'integer',
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

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** Published or closed, and already available to students. */
    public function scopeVisibleToStudents(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'closed'])->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()));
    }

    public function scopeForEnrollment(Builder $query, StudentEnrollment $enrollment): Builder
    {
        return $query->where('academic_session_id', $enrollment->academic_session_id)
            ->where('school_class_id', $enrollment->school_class_id)
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $enrollment->section_id));
    }

    /** Students this quiz is meant for. */
    public function audience(): Builder
    {
        $enrolled = StudentEnrollment::query()->where('academic_session_id', $this->academic_session_id)
            ->where('school_class_id', $this->school_class_id)->where('status', 'enrolled')
            ->when($this->section_id, fn ($q, $s) => $q->where('section_id', $s))->select('student_id');

        return Student::query()->whereIn('id', $enrolled);
    }

    /** Can a student start a new attempt right now (ignoring how many they already used)? */
    public function isOpen(): bool
    {
        return $this->status === 'published'
            && (! $this->available_from || $this->available_from->isPast())
            && (! $this->available_until || $this->available_until->isFuture());
    }

    public function totalMarks(): float
    {
        return (float) ($this->relationLoaded('questions') ? $this->questions->sum('marks') : $this->questions()->sum('marks'));
    }

    /** May the student see the correct answers of a finished attempt? */
    public function revealsAnswers(): bool
    {
        return match ($this->show_answers) {
            'after_submit' => true,
            'after_close' => $this->status === 'closed' || ($this->available_until && $this->available_until->isPast()),
            default => false,
        };
    }
}
