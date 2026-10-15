<?php

namespace App\Models\Exams;

use App\Models\Academic\Section;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Marks of one student in one paper. marks = {"theory":55,"practical":20,"ca":null}; absent ⇒ marks null. */
class ExamMark extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['examination_id', 'examination_subject_id', 'student_id', 'section_id', 'marks', 'is_absent', 'total'];

    protected $guarded = ['entered_by'];

    protected function casts(): array
    {
        return [
            'marks' => 'array',
            'is_absent' => 'boolean',
            'total' => 'float',
            'examination_id' => 'integer',
            'examination_subject_id' => 'integer',
            'student_id' => 'integer',
            'section_id' => 'integer',
            'entered_by' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function paper(): BelongsTo
    {
        return $this->belongsTo(ExaminationSubject::class, 'examination_subject_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(MarkCorrection::class)->latest('id');
    }

    /** "55 + 20" style summary for lists. */
    public function summary(): string
    {
        if ($this->is_absent) {
            return __('Absent');
        }

        return collect($this->marks ?? [])->filter(fn ($v) => $v !== null)->map(fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'))->implode(' + ') ?: '—';
    }
}
