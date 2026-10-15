<?php

namespace App\Enums;

enum TenantMode: string
{
    /** No tenant resolved — tenant-owned queries return nothing (fail-closed). */
    case None = 'none';
    /** A single branch is active. */
    case Branch = 'branch';
    /** Central administration: one institution (or the whole platform when institution is null). */
    case Central = 'central';
}
