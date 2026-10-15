<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes append-only audit records. Never throws: a failing audit write is logged
 * but must not break the business operation (the DB transaction around critical
 * operations still guarantees consistency of the business data itself).
 */
class AuditLogger
{
    /** Attribute names whose values are never written to the audit trail. */
    protected array $masked = ['password', 'remember_token', 'token', 'secret', 'api_key', 'two_factor_secret', 'two_factor_recovery_codes'];

    public function __construct(protected TenantContext $tenant) {}

    public function log(
        string $event,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        ?string $description = null,
        ?int $userId = null,
        ?int $branchId = null,
        ?int $institutionId = null,
    ): ?AuditLog {
        try {
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

            return AuditLog::query()->create([
                'institution_id' => $institutionId ?? $subject?->getAttribute('institution_id') ?? $this->tenant->institutionId(),
                'branch_id' => $branchId ?? $subject?->getAttribute('branch_id') ?? $this->tenant->branchId(),
                'user_id' => $userId ?? Auth::id(),
                'event' => $event,
                'auditable_type' => $subject ? $subject->getMorphClass() : null,
                'auditable_id' => $subject?->getKey(),
                'old_values' => $this->clean($old) ?: null,
                'new_values' => $this->clean($new) ?: null,
                'description' => $description ? Str::limit($description, 250) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
                'url' => $request ? Str::limit($request->fullUrl(), 1000, '') : null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function clean(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($this->isMasked((string) $key)) {
                $values[$key] = '********';
            }
        }

        return Arr::except($values, ['updated_at', 'created_at']);
    }

    protected function isMasked(string $key): bool
    {
        $key = strtolower($key);
        if (in_array($key, $this->masked, true)) {
            return true;
        }

        return Str::endsWith($key, ['_secret', '_token', '_password', '_key']);
    }
}
