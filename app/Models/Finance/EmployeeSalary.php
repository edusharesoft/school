<?php

namespace App\Models\Finance;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\HR\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Salary structure of one employee (confidential: employees.view-salary / payroll.*). */
class EmployeeSalary extends Model
{
    use Auditable, BelongsToBranch;

    /** Amounts stay out of the audit trail (confidential); the change itself is still logged. */
    protected array $auditExclude = ['basic', 'allowances', 'deductions', 'account_number'];

    protected $fillable = ['employee_id', 'basic', 'allowances', 'deductions', 'payment_method', 'account_number', 'effective_from'];

    protected function casts(): array
    {
        return ['basic' => 'float', 'allowances' => 'array', 'deductions' => 'array', 'effective_from' => 'date', 'employee_id' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function allowanceTotal(): float
    {
        return round(array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), $this->allowances ?? [])), 2);
    }

    public function deductionTotal(): float
    {
        return round(array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), $this->deductions ?? [])), 2);
    }

    public function gross(): float
    {
        return round($this->basic + $this->allowanceTotal(), 2);
    }
}
