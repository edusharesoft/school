<?php

namespace App\Models\Assets;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One depreciation posting (Dr Depreciation / Cr Accumulated depreciation). */
class DepreciationRun extends Model
{
    use Auditable, BelongsToBranch;

    protected $table = 'asset_depreciation_runs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'total' => 'float', 'asset_count' => 'integer', 'run_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(Depreciation::class, 'asset_depreciation_run_id');
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }
}
