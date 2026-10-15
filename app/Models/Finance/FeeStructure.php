<?php

namespace App\Models\Finance;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\StudyGroup;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Amount of a fee head for a class (and group) in a session, and the months (1–12) it is billed in. */
class FeeStructure extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['academic_session_id', 'school_class_id', 'study_group_id', 'fee_head_id', 'amount', 'months'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'months' => 'array', 'academic_session_id' => 'integer', 'school_class_id' => 'integer', 'study_group_id' => 'integer',
            'fee_head_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class, 'fee_head_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function billsIn(int $month): bool
    {
        return in_array($month, array_map('intval', $this->months ?? []), true);
    }
}
