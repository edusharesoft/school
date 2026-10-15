<?php

namespace App\Models\HR;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Issued experience / service certificate of an employee. The data column is a snapshot taken at issue time. */
class StaffCertificate extends Model
{
    use Auditable, BelongsToBranch;

    public const TYPES = ['experience', 'service'];

    public const CODES = ['experience' => 'EX', 'service' => 'SV'];

    protected $fillable = ['employee_id', 'type', 'issued_on', 'data', 'issued_by'];

    protected $guarded = ['serial_no', 'verification_code', 'revoked_at'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'data' => 'array', 'revoked_at' => 'datetime', 'employee_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function title(): string
    {
        return __($this->type === 'service' ? 'Service Certificate' : 'Experience Certificate');
    }
}
