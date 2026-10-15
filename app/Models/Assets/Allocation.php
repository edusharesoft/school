<?php

namespace App\Models\Assets;

use App\Models\Academic\Room;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Department;
use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An asset handed to a department / employee / room / named user. At most one open allocation (returned_on is null) per asset. */
class Allocation extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'asset_allocations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['allocated_on' => 'date', 'expected_return_on' => 'date', 'returned_on' => 'date', 'asset_id' => 'integer', 'department_id' => 'integer',
            'employee_id' => 'integer', 'room_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function allocator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function isOpen(): bool
    {
        return $this->returned_on === null;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->expected_return_on && $this->expected_return_on->isBefore(now()->startOfDay());
    }

    public function holderLabel(): string
    {
        return collect([$this->employee?->name, $this->department?->name, $this->room?->name, $this->assignee])->filter()->implode(' · ') ?: '—';
    }
}
