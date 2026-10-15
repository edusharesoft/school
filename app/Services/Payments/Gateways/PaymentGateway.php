<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payments\OnlinePayment;
use App\Services\Payments\InitiateResult;
use App\Services\Payments\RefundResult;
use App\Services\Payments\VerifyResult;

/**
 * One payment gateway. $config holds the branch's merged settings and secrets plus "sandbox" (bool).
 * $urls: return (browser comes back), cancel, fail, ipn — all carry ?tran={our tran_id}.
 * verify() must ask the gateway server-to-server; $input (the browser / IPN request) is only used to find ids.
 */
interface PaymentGateway
{
    public function initiate(OnlinePayment $payment, array $customer, array $urls, array $config): InitiateResult;

    public function verify(OnlinePayment $payment, array $input, array $config): VerifyResult;

    public function supportsRefund(): bool;

    public function refund(OnlinePayment $payment, float $amount, string $reason, array $config): RefundResult;
}
