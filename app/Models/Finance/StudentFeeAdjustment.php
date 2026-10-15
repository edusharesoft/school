<?php

namespace App\Models\Finance;

use App\Models\Academic\AcademicSession;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Student-specific discount (scholarship, waiver, sibling, staff child, merit) or extra fee for a session. */
class StudentFeeAdjustment extends Model
{
    use Auditable, BelongsToBranch;

    public const DISCOUNT_TYPES = ['scholarship', 'waiver', 'sibling', 'staff_child', 'merit', 'discount'];

    public const TYPES = [...self::DISCOUNT_TYPES, 'extra_fee'];

    protected $fillable = ['student_id', 'academic_session_id', 'fee_head_id', 'type', 'mode', 'value', 'months', 'reason', 'is_active'];

    protected $guarded = ['approved_by'];

    protected function casts(): array
    {
        return ['value' => 'float', 'months' => 'array', 'is_active' => 'boolean', 'student_id' => 'integer', 'academic_session_id' => 'integer',
            'fee_head_id' => 'integer', 'approved_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class, 'fee_head_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDiscount(): bool
    {
        return $this->type !== 'extra_fee';
    }

    public function appliesIn(int $month): bool
    {
        return $this->months === null || $this->months === [] || in_array($month, array_map('intval', $this->months), true);
    }

    public function describe(): string
    {
        $what = $this->isDiscount() ? ($this->mode === 'percent' ? rtrim(rtrim(number_format($this->value, 2), '0'), '.').'%' : '৳'.number_format($this->value, 2)) : '৳'.number_format($this->value, 2);

        return __(ucfirst(str_replace('_', ' ', $this->type))).' '.$what.' — '.($this->head?->name ?? __('all heads'));
    }
}
