<?php

namespace App\Models\Assets;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Asset category: also carries the depreciation defaults offered when an asset is registered. */
class Category extends Model
{
    use Auditable, BelongsToBranch;

    public const KINDS = ['general', 'laboratory'];

    public const METHODS = ['none', 'straight_line', 'declining_balance'];

    protected $table = 'asset_categories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'useful_life_months' => 'integer', 'salvage_percent' => 'float', 'declining_rate' => 'float',
            'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_category_id');
    }

    public function isLaboratory(): bool
    {
        return $this->kind === 'laboratory';
    }
}
