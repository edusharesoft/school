<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when tenant-owned data is written without an active tenant context. */
class TenantContextMissing extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self("Cannot write [{$model}] without a tenant context. Wrap the call in tenant()->runForBranch() or tenant()->runCentral().");
    }
}
