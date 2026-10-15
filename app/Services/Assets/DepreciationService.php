<?php

namespace App\Services\Assets;

use App\Models\Assets\Asset;
use App\Models\Assets\Depreciation;
use App\Models\Assets\DepreciationRun;
use App\Models\User;
use App\Services\Assets\Concerns\AssetHelpers;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly depreciation, whole-month convention: an asset is charged from the month it enters service (or the month chosen for an
 * opening-balance asset) up to the month being run. A run charges every month an asset has missed, so running October after skipping
 * September charges both. One posting per month: Dr Depreciation / Cr Accumulated depreciation, dated the last day of the month.
 *
 *  straight line      (cost − salvage) ÷ useful life, the last month takes the remainder (rounding never strands cents)
 *  declining balance  book value × yearly rate ÷ 12, never below the salvage value
 */
class DepreciationService
{
    use AssetHelpers;

    public function __construct(
        protected LedgerService $ledger,
        protected ChartOfAccounts $chart,
        protected SequenceService $sequences,
        protected TenantContext $tenant,
    ) {}

    /** The month an asset is charged next, or null when it is not depreciable. */
    public function firstPeriod(Asset $asset): ?string
    {
        if (! $asset->isDepreciable()) {
            return null;
        }
        $from = $asset->depreciation_from ?: self::periodOf($asset->in_service_on);
        if ($asset->depreciated_through && self::nextPeriod($asset->depreciated_through) > $from) {
            $from = self::nextPeriod($asset->depreciated_through);
        }

        return $from;
    }

    /**
     * What the asset would be charged for every month from its next one up to $upTo.
     *
     * @return array<int,array{period:string, amount:float, accumulated:float, book_value:float}>
     */
    public function plan(Asset $asset, string $upTo): array
    {
        $period = $this->firstPeriod($asset);
        if ($period === null || ! $asset->isLive()) {
            return [];
        }
        $out = [];
        $accumulated = round((float) $asset->accumulated_depreciation, 2);
        $base = round($asset->cost - $asset->salvage_value, 2);
        while ($period <= $upTo) {
            $remaining = round($base - $accumulated, 2);
            if ($remaining <= 0) {
                break;
            }
            if ($asset->depreciation_method === 'straight_line') {
                $monthly = round($base / max(1, $asset->useful_life_months), 2);
                $amount = $remaining <= $monthly + $asset->useful_life_months * 0.005 ? $remaining : $monthly;
            } else {
                $amount = min($remaining, round(($asset->cost - $accumulated) * $asset->declining_rate / 1200, 2));
            }
            if ($amount < 0.01) {
                break;
            }
            $accumulated = round($accumulated + $amount, 2);
            $out[] = ['period' => $period, 'amount' => round($amount, 2), 'accumulated' => $accumulated, 'book_value' => round($asset->cost - $accumulated, 2)];
            $period = self::nextPeriod($period);
        }

        return $out;
    }

    /** @return Collection<int,array{asset:Asset, months:array, total:float}> assets that would be charged by a run up to $period */
    public function preview(string $period, ?iterable $assets = null): Collection
    {
        $this->assertPeriod($period);
        $assets ??= Asset::query()->live()->whereIn('depreciation_method', ['straight_line', 'declining_balance'])->with('category:id,name')->orderBy('code')->get();

        return collect($assets)->map(function (Asset $a) use ($period) {
            $months = $this->plan($a, $period);

            return ['asset' => $a, 'months' => $months, 'total' => round(array_sum(array_column($months, 'amount')), 2)];
        })->filter(fn ($r) => $r['months'] !== [])->values();
    }

    /** Post depreciation up to $period for every depreciable asset (or the given ones). */
    public function run(string $period, User $user, ?iterable $assets = null, string $kind = 'monthly', ?string $note = null): DepreciationRun
    {
        $this->assertPeriod($period);

        return DB::transaction(function () use ($period, $user, $assets, $kind, $note) {
            $ids = $assets === null ? null : collect($assets)->map(fn ($a) => $a instanceof Asset ? $a->id : (int) $a)->all();
            $locked = Asset::query()->live()->whereIn('depreciation_method', ['straight_line', 'declining_balance'])
                ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('code')->lockForUpdate()->get();
            $rows = $this->preview($period, $locked);
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['period' => __('Nothing to depreciate up to :period.', ['period' => $period])]);
            }

            $date = $this->postingDate($period);
            $run = new DepreciationRun(['period' => $period, 'date' => $date->toDateString(), 'kind' => $kind, 'note' => $this->clip($note)]);
            $run->forceFill(['run_no' => $this->docNo('DEP', $date), 'status' => 'posted', 'total' => 0, 'asset_count' => $rows->count(), 'run_by' => $user->id])->save();

            $perMonth = [];
            foreach ($rows as $row) {
                /** @var Asset $asset */
                $asset = $row['asset'];
                foreach ($row['months'] as $m) {
                    Depreciation::query()->create(['asset_id' => $asset->id, 'asset_depreciation_run_id' => $run->id, 'period' => $m['period'], 'amount' => $m['amount'],
                        'accumulated_after' => $m['accumulated'], 'book_value_after' => $m['book_value']]);
                    $perMonth[$m['period']] = round(($perMonth[$m['period']] ?? 0) + $m['amount'], 2);
                }
                $last = end($row['months']);
                $asset->forceFill(['accumulated_depreciation' => $last['accumulated'], 'depreciated_through' => $last['period'],
                    'months_depreciated' => $asset->months_depreciated + count($row['months'])])->save();
            }

            ksort($perMonth);
            foreach ($perMonth as $p => $amount) {
                $this->ledger->post('journal', $this->postingDate($p), __('Depreciation for :month (:no)', ['month' => CarbonImmutable::createFromFormat('!Y-m', $p)->format('F Y'), 'no' => $run->run_no]), [
                    ['account_id' => $this->chart->id('depreciation'), 'debit' => $amount],
                    ['account_id' => $this->chart->id('accumulated_depreciation'), 'credit' => $amount],
                ], $run, $user);
            }
            $run->forceFill(['total' => round(array_sum($perMonth), 2)])->save();

            return $run;
        });
    }

    /** Charge the months an asset has missed, up to $upTo — used just before a disposal. Returns null when there is nothing to charge. */
    public function catchUp(Asset $asset, string $upTo, User $user, string $note): ?DepreciationRun
    {
        if ($this->plan($asset, $upTo) === []) {
            return null;
        }

        return $this->run($upTo, $user, [$asset], 'disposal', $note);
    }

    /** Take a run back. Only the latest charge of every asset in it can be reversed, and never for an asset that has since been disposed of. */
    public function reverse(DepreciationRun $run, string $reason, User $user): DepreciationRun
    {
        return DB::transaction(function () use ($run, $reason, $user) {
            $run = DepreciationRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($run->status !== 'posted') {
                throw ValidationException::withMessages(['run' => __('This run has already been reversed.')]);
            }
            $rows = $run->rows()->where('is_reversed', false)->get();
            $assets = Asset::query()->whereIn('id', $rows->pluck('asset_id')->unique())->lockForUpdate()->get()->keyBy('id');
            foreach ($rows as $row) {
                $asset = $assets[$row->asset_id];
                if (! $asset->isLive()) {
                    throw ValidationException::withMessages(['run' => __(':asset has been disposed of — reverse the disposal first.', ['asset' => $asset->code])]);
                }
                $later = Depreciation::query()->where('asset_id', $row->asset_id)->where('is_reversed', false)->where('asset_depreciation_run_id', '!=', $run->id)->where('period', '>', $row->period)->exists();
                if ($later) {
                    throw ValidationException::withMessages(['run' => __(':asset was depreciated again for a later month — reverse the later run first.', ['asset' => $asset->code])]);
                }
            }

            $this->reverseEntries($run, $reason, $user);
            foreach ($rows->groupBy('asset_id') as $assetId => $assetRows) {
                $asset = $assets[$assetId];
                $asset->forceFill([
                    'accumulated_depreciation' => round($asset->accumulated_depreciation - $assetRows->sum('amount'), 2),
                    'months_depreciated' => max(0, $asset->months_depreciated - $assetRows->count()),
                ]);
                $assetRows->each(fn (Depreciation $r) => $r->forceFill(['is_reversed' => true])->save());
                $asset->depreciated_through = Depreciation::query()->where('asset_id', $assetId)->where('is_reversed', false)->max('period');
                $asset->save();
            }
            $run->forceFill(['status' => 'reversed', 'note' => $this->clip(trim(($run->note ? $run->note.' — ' : '').__('Reversed: :reason', ['reason' => $reason])))])->save();

            return $run;
        });
    }

    /**
     * Future charges of one asset: the next $months months, without posting anything.
     *
     * @return array<int,array{period:string, amount:float, accumulated:float, book_value:float}>
     */
    public function forecast(Asset $asset, int $months = 12): array
    {
        $first = $this->firstPeriod($asset);
        if ($first === null) {
            return [];
        }
        $upTo = CarbonImmutable::createFromFormat('!Y-m', max($first, self::periodOf(now())))->addMonthsNoOverflow(max(1, $months) - 1)->format('Y-m');

        return $this->plan($asset, $upTo);
    }

    /** Last day of the month, or today when the month is not over yet. */
    protected function postingDate(string $period): CarbonImmutable
    {
        $end = CarbonImmutable::createFromFormat('!Y-m', $period)->endOfMonth()->startOfDay();

        return $end->isFuture() ? CarbonImmutable::today() : $end;
    }

    protected function assertPeriod(string $period): void
    {
        if (! self::validPeriod($period)) {
            throw ValidationException::withMessages(['period' => __('Enter the month as YYYY-MM.')]);
        }
        if ($period > self::periodOf(now())) {
            throw ValidationException::withMessages(['period' => __('Depreciation cannot be posted for a future month.')]);
        }
    }
}
