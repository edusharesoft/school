<?php

namespace App\Models\Inventory;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Department;
use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockIssue extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'stock_issues';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'total' => 'float', 'department_id' => 'integer', 'employee_id' => 'integer', 'issued_by' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockIssueItem::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** Who received the goods, for display. */
    public function recipientLabel(): string
    {
        return collect([$this->employee?->name, $this->department?->name, $this->recipient])->filter()->implode(' · ') ?: '—';
    }
}
