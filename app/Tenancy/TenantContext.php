<?php

namespace App\Tenancy;

use App\Enums\TenantMode;
use App\Models\Branch;
use App\Models\Institution;
use Closure;

/**
 * Request-scoped holder of the active tenant.
 *
 * Registered as a scoped singleton (reset between requests / queue jobs under Octane
 * or queue:work). Everything tenant-aware (global scopes, creating hooks, settings,
 * storage paths, audit logs) reads from here — never from the request directly.
 */
class TenantContext
{
    protected TenantMode $mode = TenantMode::None;

    protected ?Institution $institution = null;

    protected ?Branch $branch = null;

    public function setBranch(Branch $branch): static
    {
        $this->mode = TenantMode::Branch;
        $this->branch = $branch;
        $this->institution = $branch->relationLoaded('institution')
            ? $branch->institution
            : $branch->institution()->first();

        return $this;
    }

    /** Central mode. A null institution means the platform owner sees every institution. */
    public function setCentral(?Institution $institution = null): static
    {
        $this->mode = TenantMode::Central;
        $this->branch = null;
        $this->institution = $institution;

        return $this;
    }

    /** Narrow central mode to one institution (used after login of an institution admin). */
    public function setInstitution(?Institution $institution): static
    {
        $this->institution = $institution;

        return $this;
    }

    public function clear(): static
    {
        $this->mode = TenantMode::None;
        $this->branch = null;
        $this->institution = null;

        return $this;
    }

    public function mode(): TenantMode
    {
        return $this->mode;
    }

    public function isBranch(): bool
    {
        return $this->mode === TenantMode::Branch && $this->branch !== null;
    }

    public function isCentral(): bool
    {
        return $this->mode === TenantMode::Central;
    }

    public function isResolved(): bool
    {
        return $this->mode !== TenantMode::None;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function branchId(): ?int
    {
        return $this->branch?->getKey();
    }

    public function institution(): ?Institution
    {
        return $this->institution;
    }

    public function institutionId(): ?int
    {
        return $this->institution?->getKey();
    }

    /**
     * Stable key that identifies the tenant a session/token was issued for.
     * "central" or "branch:12". (Central users are further isolated by their own institution_id.)
     */
    public function key(): string
    {
        return match ($this->mode) {
            TenantMode::Branch => 'branch:'.$this->branchId(),
            TenantMode::Central => 'central',
            TenantMode::None => 'none',
        };
    }

    /** Run a callback inside a branch context and restore the previous state afterwards. */
    public function runForBranch(Branch $branch, Closure $callback): mixed
    {
        return $this->preserving(function () use ($branch, $callback) {
            $this->setBranch($branch);

            return $callback($branch);
        });
    }

    /** Run a callback in central context (optionally narrowed to one institution). */
    public function runCentral(?Institution $institution, Closure $callback): mixed
    {
        return $this->preserving(function () use ($institution, $callback) {
            $this->setCentral($institution);

            return $callback();
        });
    }

    protected function preserving(Closure $callback): mixed
    {
        $previous = [$this->mode, $this->institution, $this->branch];

        try {
            return $callback();
        } finally {
            [$this->mode, $this->institution, $this->branch] = $previous;
        }
    }
}
