<?php

namespace App\Models\Documents;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\HR\Employee;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One printed ID card of a student or a staff member. At most one card per holder is "active". */
class IdCard extends Model
{
    use Auditable, BelongsToBranch;

    public const HOLDERS = ['student', 'employee'];

    public const STATUSES = ['active', 'lost', 'revoked', 'replaced'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date', 'valid_until' => 'date', 'last_printed_at' => 'datetime', 'printed_count' => 'integer',
            'student_id' => 'integer', 'employee_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->valid_until->isPast() && ! $this->valid_until->isToday();
    }

    /** Valid = active and not past its date. */
    public function isValid(): bool
    {
        return $this->isActive() && ! $this->isExpired();
    }

    public function holderName(): string
    {
        return (string) ($this->holder_type === 'student' ? $this->student?->name : $this->employee?->name);
    }
}
