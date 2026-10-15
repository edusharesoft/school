<?php

namespace App\Models\Transport;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    protected $table = 'transport_drivers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer', 'license_expiry' => 'date', 'is_active' => 'boolean', 'employee_id' => 'integer'];
    }


    public const ROLES = ['driver', 'helper'];

    public function licenseExpiring(int $days = 30): bool
    {
        return $this->role === 'driver' && $this->license_expiry && $this->license_expiry->lte(now()->addDays($days)->endOfDay());
    }
}
