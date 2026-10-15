<?php

namespace App\Events\Finance;

use App\Models\Finance\FeePayment;
use Illuminate\Foundation\Events\Dispatchable;

/** A fee receipt was recorded. The notification hub confirms it to the guardians. */
class FeePaymentReceived
{
    use Dispatchable;

    public function __construct(public FeePayment $payment) {}
}
