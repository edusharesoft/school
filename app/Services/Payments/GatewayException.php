<?php

namespace App\Services\Payments;

/** The gateway refused or could not be reached. The message is safe to show to the payer. */
class GatewayException extends \RuntimeException
{
    public function __construct(string $message, public readonly array $raw = [])
    {
        parent::__construct($message);
    }
}
