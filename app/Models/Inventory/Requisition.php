<?php

namespace App\Models\Inventory;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Purchase request. Status changes only through ProcurementService. */
class Requisition extends Model
{
    use Auditable, BelongsToBranch;

    public const STATUSES = ['pending', 'approved', 'rejected', 'ordered', 'cancelled'];

    protected $table = 'inventory_requisitions';

    protected $fillable = ['date', 'needed_by', 'department_id', 'purpose'];

    protected $guarded = ['requisition_no', 'status', 'review_note', 'requested_by', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'needed_by' => 'date', 'approved_at' => 'datetime', 'department_id' => 'integer', 'requested_by' => 'integer',
            'approved_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class, 'inventory_requisition_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'inventory_requisition_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function estimatedTotal(): float
    {
        return round($this->items->sum(fn ($i) => $i->quantity * $i->estimated_unit_cost), 2);
    }
}
