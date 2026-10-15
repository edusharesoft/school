<?php

namespace App\Models\HR;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only career event. Rows are never updated or deleted by the application. */
class EmploymentHistory extends Model
{
    use BelongsToBranch;

    public const EVENTS = [
        'joined', 'designation_changed', 'department_changed', 'type_changed', 'salary_revised',
        'resigned', 'terminated', 'retired', 'suspended', 'reinstated',
    ];

    protected $fillable = ['employee_id', 'event', 'effective_on', 'from_value', 'to_value', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return ['effective_on' => 'date', 'employee_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function label(): string
    {
        return __(ucfirst(str_replace('_', ' ', $this->event)));
    }
}
