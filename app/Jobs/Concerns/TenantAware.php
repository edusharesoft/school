<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\WithTenantContext;
use App\Tenancy\TenantContext;

/**
 * Use on every queued job that touches tenant data:
 *
 *   class SendAbsenceAlerts implements ShouldQueue {
 *       use Queueable, TenantAware;
 *       public function __construct(...) { $this->captureTenant(); }
 *   }
 *
 * The branch id is serialized with the job and restored by WithTenantContext before handle().
 */
trait TenantAware
{
    public ?int $tenantBranchId = null;

    public ?int $tenantInstitutionId = null;

    public bool $tenantCentral = false;

    public function captureTenant(): static
    {
        $context = app(TenantContext::class);
        $this->tenantBranchId = $context->branchId();
        $this->tenantInstitutionId = $context->institutionId();
        $this->tenantCentral = $context->isCentral();

        return $this;
    }

    public function forBranch(int $branchId): static
    {
        $this->tenantBranchId = $branchId;
        $this->tenantCentral = false;

        return $this;
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new WithTenantContext];
    }
}
