<?php

namespace App\Services\Assets;

use App\Models\Assets\Asset;
use App\Models\Assets\Audit;
use App\Models\Assets\AuditItem;
use App\Models\User;
use App\Services\Assets\Concerns\AssetHelpers;
use App\Services\Finance\LedgerService;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Physical verification. A count sheet lists every live asset (of a category and/or room); the checker marks each one found, damaged or
 * missing. Completing stamps verified assets, updates condition and location, and flags the missing ones — it never writes anything off by
 * itself: a missing asset is disposed of as "lost" by someone with the approve permission.
 */
class AssetAuditService
{
    use AssetHelpers;

    public function __construct(protected SequenceService $sequences, protected TenantContext $tenant, protected LedgerService $ledger) {}

    public function start(array $data, User $user): Audit
    {
        $date = CarbonImmutable::parse($data['date'])->startOfDay();
        if ($date->isFuture()) {
            throw ValidationException::withMessages(['date' => __('The audit date cannot be in the future.')]);
        }

        return DB::transaction(function () use ($data, $user, $date) {
            $assets = Asset::query()->live()->with('room:id,name')
                ->when(! empty($data['asset_category_id']), fn ($q) => $q->where('asset_category_id', $data['asset_category_id']))
                ->when(! empty($data['room_id']), fn ($q) => $q->where('room_id', $data['room_id']))->orderBy('code')->get();
            if ($assets->isEmpty()) {
                throw ValidationException::withMessages(['asset_category_id' => __('There is no asset to verify with this filter.')]);
            }
            $audit = new Audit(['title' => mb_substr(trim($data['title']), 0, 150), 'date' => $date->toDateString(), 'asset_category_id' => $data['asset_category_id'] ?? null,
                'room_id' => $data['room_id'] ?? null, 'note' => $this->clip($data['note'] ?? null)]);
            $audit->forceFill(['audit_no' => $this->docNo('AAU', $date), 'status' => 'open', 'started_by' => $user->id])->save();
            foreach ($assets as $asset) {
                AuditItem::query()->create(['asset_audit_id' => $audit->id, 'asset_id' => $asset->id,
                    'expected_location' => $this->clip(collect([$asset->room?->name, $asset->location])->filter()->implode(' · '), 150)]);
            }

            return $audit;
        });
    }

    /** @param array<int,array{result?:?string, found_location?:?string, note?:?string}> $results keyed by audit item id */
    public function saveResults(Audit $audit, array $results): int
    {
        $this->assertOpen($audit);
        $saved = 0;
        foreach ($audit->items()->get() as $line) {
            if (! isset($results[$line->id])) {
                continue;
            }
            $result = $results[$line->id]['result'] ?? null;
            if ($result !== null && $result !== '' && ! in_array($result, AuditItem::RESULTS, true)) {
                throw ValidationException::withMessages(['results' => __('Unknown result.')]);
            }
            $line->result = $result ?: null;
            $line->found_location = $this->clip($results[$line->id]['found_location'] ?? null, 150);
            $line->note = $this->clip($results[$line->id]['note'] ?? null);
            $line->checked_at = $line->result ? ($line->checked_at ?? now()) : null;
            if ($line->isDirty()) {
                $line->save();
                $saved++;
            }
        }

        return $saved;
    }

    public function complete(Audit $audit, User $user): Audit
    {
        $this->assertOpen($audit);

        return DB::transaction(function () use ($audit, $user) {
            $lines = $audit->items()->whereNotNull('result')->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['results' => __('Check at least one asset before completing the audit.')]);
            }
            foreach ($lines as $line) {
                $asset = Asset::query()->whereKey($line->asset_id)->lockForUpdate()->first();
                if (! $asset || ! $asset->isLive()) {
                    continue;
                }
                if ($line->result === 'missing') {
                    $asset->forceFill(['is_missing' => true])->save();

                    continue;
                }
                $asset->forceFill(['is_missing' => false, 'last_verified_on' => $audit->date->toDateString()]);
                if ($line->result === 'damaged') {
                    $asset->condition = 'damaged';
                }
                if ($line->found_location) {
                    $asset->location = $line->found_location;
                }
                $asset->save();
            }
            $audit->forceFill(['status' => 'completed', 'completed_by' => $user->id, 'completed_at' => now()])->save();

            return $audit;
        });
    }

    public function cancel(Audit $audit): Audit
    {
        $this->assertOpen($audit);
        $audit->forceFill(['status' => 'cancelled'])->save();

        return $audit;
    }

    protected function assertOpen(Audit $audit): void
    {
        if ($audit->status !== 'open') {
            throw ValidationException::withMessages(['status' => __('This audit is already closed.')]);
        }
    }
}
