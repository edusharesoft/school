<?php

namespace App\Models\Finance;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['name', 'phone', 'email', 'address', 'bin', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
