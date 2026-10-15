<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payments\OnlinePayment;
use App\Services\Payments\GatewayException;
use App\Services\Payments\InitiateResult;
use App\Services\Payments\RefundResult;
use App\Services\Payments\VerifyResult;

/**
 * Bangla QR has no checkout and no verification API of its own: money goes straight from the payer's bank / wallet to the school's
 * merchant account. OnlinePaymentService therefore treats it as a "manual" gateway (config payments.gateways.banglaqr.manual):
 * it shows the QR, takes the payer's TrxID and lets the accounts office confirm it. The methods below exist only to satisfy
 * the interface and are never a source of truth — verify() never says "paid".
 */
class BanglaQrGateway implements PaymentGateway
{
    public function initiate(OnlinePayment $payment, array $customer, array $urls, array $config): InitiateResult
    {
        throw new GatewayException(__('Bangla QR has no online checkout; show the QR code instead.'));
    }

    public function verify(OnlinePayment $payment, array $input, array $config): VerifyResult
    {
        return VerifyResult::pending(__('Bangla QR payments are confirmed by the accounts office.'));
    }

    public function supportsRefund(): bool
    {
        return false;
    }

    public function refund(OnlinePayment $payment, float $amount, string $reason, array $config): RefundResult
    {
        return new RefundResult(false, null, __('Refund the payer from the bank or wallet, then record the refund reference here.'));
    }
}
