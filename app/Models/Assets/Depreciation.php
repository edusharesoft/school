<?php

namespace App\Models\Assets;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Depreciation of one asset for one month. Immutable — a mistake is corrected by reversing its run. */
class Depreciation extends Model
{
    use BelongsToBranch;

    protected $table = 'asset_depreciations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'accumulated_after' => 'float', 'book_value_after' => 'float', 'is_reversed' => 'boolean',
            'asset_id' => 'integer', 'asset_depreciation_run_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DepreciationRun::class, 'asset_depreciation_run_id');
    }
}
