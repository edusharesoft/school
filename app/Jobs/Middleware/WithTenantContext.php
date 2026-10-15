<?php

namespace App\Jobs\Middleware;

use App\Models\Branch;
use App\Models\Institution;
use App\Tenancy\TenantContext;
use Closure;
use RuntimeException;

/** Restores the tenant a job was dispatched for; fails the job if it cannot be determined. */
class WithTenantContext
{
    public function handle(object $job, Closure $next): void
    {
        $context = app(TenantContext::class);

        if (! empty($job->tenantBranchId)) {
            $branch = Branch::query()->with('institution')->find($job->tenantBranchId);
            if (! $branch) {
                throw new RuntimeException("Branch {$job->tenantBranchId} no longer exists; job aborted.");
            }
            $context->runForBranch($branch, fn () => $next($job));

            return;
        }

        if (! empty($job->tenantCentral)) {
            $institution = $job->tenantInstitutionId ? Institution::query()->find($job->tenantInstitutionId) : null;
            $context->runCentral($institution, fn () => $next($job));

            return;
        }

        throw new RuntimeException(class_basename($job).' was dispatched without a tenant context.');
    }
}
