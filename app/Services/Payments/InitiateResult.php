<?php

namespace App\Services\Payments;

/** Where to send the payer, and the gateway's own reference for the checkout (session key, paymentID …). */
final class InitiateResult
{
    public function __construct(public readonly string $redirectUrl, public readonly ?string $gatewayRef = null, public readonly array $raw = []) {}
}
