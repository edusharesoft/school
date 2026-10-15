<?php

namespace App\Models\Finance;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['period', 'notes'];

    protected $guarded = ['status', 'employees', 'gross', 'deductions', 'net', 'prepared_by', 'approved_by', 'approved_at', 'paid_from_account_id', 'paid_on'];

    protected function casts(): array
    {
        return ['gross' => 'float', 'deductions' => 'float', 'net' => 'float', 'employees' => 'integer', 'approved_at' => 'datetime', 'paid_on' => 'date',
            'paid_from_account_id' => 'integer', 'prepared_by' => 'integer', 'approved_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    public function monthLabel(): string
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d', $this->period.'-01')->format('F Y');
    }

    public function tone(): string
    {
        return match ($this->status) { 'paid' => 'success', 'approved' => 'primary', 'draft' => 'warning', default => 'secondary' };
    }
}
