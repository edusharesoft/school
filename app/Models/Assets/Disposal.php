<?php

namespace App\Models\Assets;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Finance\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disposal extends Model
{
    use Auditable, BelongsToBranch;

    public const TYPES = ['sale', 'scrap', 'donated', 'lost'];

    protected $table = 'asset_disposals';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'cost' => 'float', 'accumulated' => 'float', 'book_value' => 'float', 'proceeds' => 'float', 'gain_loss' => 'float',
            'asset_id' => 'integer', 'account_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function disposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }
}
