<?php

namespace App\Models\Academic;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Subject combination: which subjects a class (optionally a group within it) studies, with marks. */
class ClassSubject extends Model
{
    use Auditable, BelongsToBranch;

    public const CATEGORIES = ['compulsory', 'optional', 'elective'];

    protected $fillable = ['school_class_id', 'subject_id', 'study_group_id', 'category', 'full_marks', 'pass_marks', 'sort_order'];

    protected function casts(): array
    {
        return [
            'school_class_id' => 'integer',
            'subject_id' => 'integer',
            'study_group_id' => 'integer',
            'full_marks' => 'integer',
            'pass_marks' => 'integer',
            'sort_order' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class);
    }
}
