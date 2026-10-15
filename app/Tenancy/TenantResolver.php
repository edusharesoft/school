<?php

namespace App\Tenancy;

use App\Models\Branch;
use App\Models\Domain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Maps an HTTP host to the central area or to a branch.
 *
 * Order: central domains → exact match in `domains` (custom domains) → "<subdomain>.<base_domain>".
 * Results are cached per host; BranchObserver/DomainObserver call forget() on change.
 */
class TenantResolver
{
    public const CENTRAL = 'central';

    public const MISSING = 'missing';

    public const REDIRECT_APEX = 'redirect-apex';

    public function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        // Strip port if present (the Request::getHost() value never has one, but be defensive).
        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $host = Str::before($host, ':');
        }

        return rtrim($host, '.');
    }

    public function isCentralHost(string $host): bool
    {
        return in_array($this->normalizeHost($host), config('tenancy.central_domains', []), true);
    }

    /**
     * @return string|int CENTRAL | MISSING | REDIRECT_APEX | branch id
     */
    public function resolveHost(string $host): string|int
    {
        $host = $this->normalizeHost($host);

        if ($this->isCentralHost($host)) {
            return self::CENTRAL;
        }

        $base = strtolower((string) config('tenancy.base_domain'));

        if ($host === 'www.'.$base) {
            return self::REDIRECT_APEX;
        }

        $ttl = (int) config('tenancy.resolver_cache_ttl', 600);

        return Cache::remember($this->cacheKey($host), $ttl, function () use ($host, $base) {
            $domain = Domain::query()->where('domain', $host)->whereNotNull('branch_id')->first();
            if ($domain) {
                return (int) $domain->branch_id;
            }

            $suffix = '.'.$base;
            if ($base !== '' && str_ends_with($host, $suffix)) {
                $label = substr($host, 0, -strlen($suffix));

                // Only single-level labels map to branches (no a.b.base).
                if ($label !== '' && ! str_contains($label, '.') && ! $this->isReserved($label)) {
                    $branchId = Branch::query()->where('subdomain', $label)->value('id');
                    if ($branchId) {
                        return (int) $branchId;
                    }
                }
            }

            return self::MISSING;
        });
    }

    public function findBranch(int $branchId): ?Branch
    {
        return Branch::query()->with('institution')->find($branchId);
    }

    public function isReserved(string $label): bool
    {
        return in_array(strtolower($label), config('tenancy.reserved_subdomains', []), true);
    }

    public function forget(string $host): void
    {
        Cache::forget($this->cacheKey($this->normalizeHost($host)));
    }

    /** Forget every cached host of a branch (subdomain host + custom domains). */
    public function forgetBranch(Branch $branch, ?string $previousSubdomain = null): void
    {
        $base = config('tenancy.base_domain');
        $this->forget($branch->subdomain.'.'.$base);
        if ($previousSubdomain) {
            $this->forget($previousSubdomain.'.'.$base);
        }
        foreach ($branch->domains()->pluck('domain') as $domain) {
            $this->forget($domain);
        }
    }

    protected function cacheKey(string $host): string
    {
        return 'tenancy:host:'.$host;
    }
}
