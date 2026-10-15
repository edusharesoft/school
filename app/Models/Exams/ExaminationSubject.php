<?php

namespace App\Models\Exams;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\StudyGroup;
use App\Models\Academic\Subject;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subject paper of an exam for one class. components = {"theory":{"full":70,"pass":23}, "practical":{…}, "ca":{…}};
 * a component with pass 0 has no separate pass requirement.
 */
class ExaminationSubject extends Model
{
    use Auditable, BelongsToBranch;

    public const COMPONENTS = ['theory', 'practical', 'ca'];

    protected $fillable = [
        'examination_id', 'school_class_id', 'subject_id', 'study_group_id', 'exam_date', 'starts_at', 'ends_at',
        'components', 'full_marks', 'pass_marks', 'is_optional', 'counts_in_gpa', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'components' => 'array',
            'full_marks' => 'float',
            'pass_marks' => 'float',
            'is_optional' => 'boolean',
            'counts_in_gpa' => 'boolean',
            'sort_order' => 'integer',
            'examination_id' => 'integer',
            'school_class_id' => 'integer',
            'subject_id' => 'integer',
            'study_group_id' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
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

    /** Components actually used (full > 0), in canonical order. */
    public function activeComponents(): array
    {
        return array_filter(array_intersect_key($this->components ?? [], array_flip(self::COMPONENTS)), fn ($c) => ($c['full'] ?? 0) > 0);
    }
}
