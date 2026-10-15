<?php

namespace App\Models\Exams;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\StudyGroup;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Processed result of one student in one exam. Rows are rebuilt by ResultService::process (not audited
 * row by row — processing, approval and publication are audited on the exam).
 * subjects = [{paper_id, subject, code, full, components:{…}, marks:{…}, total, percent, grade, point, status, is_optional, counts_in_gpa}]
 */
class ExamResult extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'examination_id', 'student_id', 'student_enrollment_id', 'school_class_id', 'section_id', 'study_group_id', 'roll_no',
        'total_marks', 'full_marks', 'percentage', 'gpa', 'gpa_without_optional', 'grade', 'status', 'failed_subjects',
        'class_position', 'section_position', 'attendance_pct', 'subjects', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'subjects' => 'array',
            'total_marks' => 'float',
            'full_marks' => 'float',
            'percentage' => 'float',
            'gpa' => 'float',
            'gpa_without_optional' => 'float',
            'attendance_pct' => 'float',
            'failed_subjects' => 'integer',
            'class_position' => 'integer',
            'section_position' => 'integer',
            'roll_no' => 'integer',
            'examination_id' => 'integer',
            'student_id' => 'integer',
            'school_class_id' => 'integer',
            'section_id' => 'integer',
            'study_group_id' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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

    /** Only results of published exams (portals, public lookup, API for students & parents). */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereHas('examination', fn ($q) => $q->where('status', 'published'));
    }

    public function tone(): string
    {
        return match ($this->status) { 'pass' => 'success', 'fail' => 'danger', default => 'secondary' };
    }

    public function gpaLabel(): string
    {
        return $this->gpa === null ? '—' : number_format($this->gpa, 2);
    }
}
