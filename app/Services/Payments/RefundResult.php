<?php

namespace App\Services\Payments;

final class RefundResult
{
    public function __construct(public readonly bool $ok, public readonly ?string $reference = null, public readonly ?string $message = null, public readonly array $raw = []) {}
}
