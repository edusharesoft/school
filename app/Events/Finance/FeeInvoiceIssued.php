<?php

namespace App\Events\Finance;

use App\Models\Finance\FeeInvoice;
use Illuminate\Foundation\Events\Dispatchable;

/** A fee invoice was created (monthly generation or one-off). The notification hub sends it to the guardians. */
class FeeInvoiceIssued
{
    use Dispatchable;

    public function __construct(public FeeInvoice $invoice) {}
}
