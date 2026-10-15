<?php

namespace App\Services\Notifications;

/** Outcome of one provider call. $retryable = a later attempt may succeed (network, 5xx, rate limit). */
final class SendResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $providerId = null,
        public readonly ?string $error = null,
        public readonly bool $retryable = false,
        public readonly string $status = 'sent',
    ) {}

    public static function sent(?string $providerId = null, string $status = 'sent'): self
    {
        return new self(true, $providerId, null, false, $status);
    }

    public static function failed(string $error, bool $retryable = false): self
    {
        return new self(false, null, mb_substr($error, 0, 255), $retryable);
    }
}
