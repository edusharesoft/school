<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Thrown when a request tries to read or write data that belongs to another branch/institution. */
class CrossTenantViolation extends HttpException
{
    public function __construct(string $message = 'This record belongs to another branch.')
    {
        parent::__construct(403, $message);
    }
}
