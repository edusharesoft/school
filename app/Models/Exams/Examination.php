<?php

namespace App\Models\Exams;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Term;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Examination extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    public const TYPES = ['class_test', 'term', 'half_yearly', 'annual', 'pre_test', 'test', 'model'];

    /** Lifecycle; marks can be entered in "scheduled" and "marks_entry", results only published after approval. */
    public const STATUSES = ['draft', 'scheduled', 'marks_entry', 'processing', 'approved', 'published'];

    protected $fillable = [
        'academic_session_id', 'term_id', 'grading_scale_id', 'name', 'type', 'starts_on', 'ends_on', 'min_attendance', 'status', 'instructions',
    ];

    protected $guarded = ['approved_by', 'approved_at', 'published_at'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'min_attendance' => 'integer',
            'academic_session_id' => 'integer',
            'term_id' => 'integer',
            'grading_scale_id' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ExaminationSubject::class)->orderBy('school_class_id')->orderBy('sort_order');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(ExamMark::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Marks may be entered / changed directly only before results are processed. */
    public function acceptsMarks(): bool
    {
        return in_array($this->status, ['scheduled', 'marks_entry'], true);
    }

    /** Marks can only be corrected through a request once results processing started. */
    public function needsCorrectionRequest(): bool
    {
        return in_array($this->status, ['processing', 'approved', 'published'], true);
    }

    public function tone(): string
    {
        return match ($this->status) {
            'published' => 'success', 'approved' => 'primary', 'processing' => 'info', 'marks_entry' => 'warning', 'scheduled' => 'secondary', default => 'light',
        };
    }
}
