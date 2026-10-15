<?php

namespace App\Models\Learning;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Subject;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Class material: a document (private file), a video lesson, a recorded lesson or a link. */
class LearningMaterial extends Model
{
    use Auditable, BelongsToBranch;

    public const TYPES = ['document', 'video', 'recorded_lesson', 'link'];

    protected $fillable = ['school_class_id', 'section_id', 'subject_id', 'type', 'title', 'description', 'url'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean', 'published_at' => 'datetime', 'file_size' => 'integer', 'created_by' => 'integer',
            'school_class_id' => 'integer', 'section_id' => 'integer', 'subject_id' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer',
        ];
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** Materials meant for this enrollment: the whole class, or the student's own section. */
    public function scopeForEnrollment(Builder $query, StudentEnrollment $enrollment): Builder
    {
        return $query->where('school_class_id', $enrollment->school_class_id)
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $enrollment->section_id));
    }

    public function hasFile(): bool
    {
        return (bool) $this->file_path;
    }
}
