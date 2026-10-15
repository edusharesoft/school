<?php

namespace App\Services\Tenancy;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Concurrency-safe per-branch counters (row lock inside a transaction).
 * Must be called inside the caller's DB transaction so a rolled-back operation
 * also rolls back the number (no gaps from failed saves).
 */
class SequenceService
{
    public function __construct(protected TenantContext $tenant) {}

    public function next(string $name, string $period = '', ?int $branchId = null): int
    {
        $branchId ??= $this->tenant->branchId() ?? throw new RuntimeException('Sequence requires a branch context.');

        if (DB::transactionLevel() === 0) {
            return DB::transaction(fn () => $this->next($name, $period, $branchId));
        }

        $row = DB::table('sequences')
            ->where(['branch_id' => $branchId, 'name' => $name, 'period' => $period])
            ->lockForUpdate()
            ->first();

        if (! $row) {
            DB::table('sequences')->insertOrIgnore([
                'branch_id' => $branchId, 'name' => $name, 'period' => $period, 'last_value' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $row = DB::table('sequences')
                ->where(['branch_id' => $branchId, 'name' => $name, 'period' => $period])
                ->lockForUpdate()
                ->first();
        }

        $value = (int) $row->last_value + 1;
        DB::table('sequences')->where('id', $row->id)->update(['last_value' => $value, 'updated_at' => now()]);

        return $value;
    }
}
