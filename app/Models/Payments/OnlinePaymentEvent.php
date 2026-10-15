<?php

namespace App\Models\Payments;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/** Audit trail of gateway traffic for one online payment (secrets are removed before saving). */
class OnlinePaymentEvent extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    protected $fillable = ['online_payment_id', 'gateway', 'type', 'result', 'payload', 'ip'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'online_payment_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }
}
