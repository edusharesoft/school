<?php

namespace App\Services\Tenancy;

use App\Enums\RecordStatus;
use App\Models\Branch;
use App\Models\Domain;
use App\Models\Institution;
use App\Services\Audit\AuditLogger;
use App\Tenancy\TenantResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating / changing branches. Adding a branch is pure data — no code or config change.
 */
class BranchService
{
    public function __construct(
        protected TenantResolver $resolver,
        protected AuditLogger $audit,
    ) {}

    public function create(Institution $institution, array $data): Branch
    {
        app(\App\Services\Saas\SubscriptionService::class)->assertCanAddBranch($institution);

        return DB::transaction(function () use ($institution, $data) {
            $branch = new Branch(Arr::except($data, ['custom_domain']));
            $branch->subdomain = strtolower($data['subdomain']);
            $branch->institution()->associate($institution);
            $branch->timezone ??= $institution->timezone ?? config('app.timezone');
            $branch->currency ??= $institution->currency ?? config('app.currency');
            $branch->save();

            if (! empty($data['custom_domain'])) {
                $this->attachDomain($branch, $data['custom_domain'], true);
            }

            $this->resolver->forgetBranch($branch);

            return $branch;
        });
    }

    public function update(Branch $branch, array $data): Branch
    {
        return DB::transaction(function () use ($branch, $data) {
            $previousSubdomain = $branch->subdomain;
            $branch->fill(Arr::except($data, ['custom_domain']));
            if (isset($data['subdomain'])) {
                $branch->subdomain = strtolower($data['subdomain']);
            }
            $branch->save();

            $this->resolver->forgetBranch($branch, $previousSubdomain);

            return $branch;
        });
    }

    public function setStatus(Branch $branch, RecordStatus $status): Branch
    {
        $branch->update(['status' => $status]);
        $this->resolver->forgetBranch($branch);

        return $branch;
    }

    public function attachDomain(Branch $branch, string $host, bool $primary = false): Domain
    {
        $host = strtolower(trim($host));

        if (in_array($host, config('tenancy.central_domains'), true)) {
            throw ValidationException::withMessages(['domain' => __('A central domain cannot be assigned to a branch.')]);
        }

        return DB::transaction(function () use ($branch, $host, $primary) {
            if ($primary) {
                $branch->domains()->update(['is_primary' => false]);
            }

            $domain = new Domain(['domain' => $host, 'type' => 'custom', 'is_primary' => $primary]);
            $domain->institution()->associate($branch->institution_id);
            $domain->branch()->associate($branch);
            $domain->save();

            $this->resolver->forget($host);

            return $domain;
        });
    }

    public function detachDomain(Domain $domain): void
    {
        $host = $domain->domain;
        $domain->delete();
        $this->resolver->forget($host);
    }
}
