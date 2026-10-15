<?php

namespace App\Services\Payments;

/**
 * The gateway's server-side answer about one checkout.
 * status: paid | pending | failed | cancelled — "paid" only when the gateway itself confirms a completed payment.
 */
final class VerifyResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $txnId = null,
        public readonly ?string $method = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
        public readonly bool $risky = false,
        // Set when the gateway reports "paid" in a way we cannot confirm server-side: the office decides (status "review").
        public readonly ?string $reviewReason = null,
    ) {}

    public static function pending(?string $message = null, array $raw = []): self
    {
        return new self('pending', message: $message, raw: $raw);
    }
}
