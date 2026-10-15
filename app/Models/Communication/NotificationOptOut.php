<?php

namespace App\Models\Communication;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/** A phone number or e-mail address that does not want messages on one channel (emergency notices excepted). */
class NotificationOptOut extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['channel', 'address', 'reason'];

    protected $guarded = ['created_by'];

    protected function casts(): array
    {
        return ['created_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }
}
