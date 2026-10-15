<?php

namespace App\Services\Assets;

use App\Models\Assets\Asset;
use App\Models\Assets\Disposal;
use App\Models\Finance\Account;
use App\Models\User;
use App\Services\Assets\Concerns\AssetHelpers;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Selling, scrapping, giving away or writing off a lost asset. Depreciation is brought up to the month before the disposal first, then
 *
 *   Dr cash / bank (proceeds) · Dr Accumulated depreciation · Dr Loss on disposal (if book value > proceeds)
 *   Cr Fixed assets (cost) · Cr Gain on disposal (if proceeds > book value)
 */
class DisposalService
{
    use AssetHelpers;

    public function __construct(
        protected LedgerService $ledger,
        protected ChartOfAccounts $chart,
        protected SequenceService $sequences,
        protected TenantContext $tenant,
        protected DepreciationService $depreciation,
    ) {}

    /** @param array{type:string, date:string, proceeds?:mixed, account_id?:mixed, buyer?:?string, reason?:?string} $data */
    public function dispose(Asset $asset, array $data, User $user): Disposal
    {
        $type = $data['type'] ?? '';
        if (! in_array($type, Disposal::TYPES, true)) {
            throw ValidationException::withMessages(['type' => __('Choose how the asset leaves.')]);
        }
        $date = CarbonImmutable::parse($data['date'])->startOfDay();
        if ($date->isFuture()) {
            throw ValidationException::withMessages(['date' => __('The disposal date cannot be in the future.')]);
        }
        $proceeds = in_array($type, ['sale', 'scrap'], true) ? round(max(0.0, (float) ($data['proceeds'] ?? 0)), 2) : 0.0;
        if ($type === 'sale' && $proceeds <= 0) {
            throw ValidationException::withMessages(['proceeds' => __('Enter the sale amount.')]);
        }
        $account = null;
        if ($proceeds > 0) {
            $account = Account::query()->money()->find($data['account_id'] ?? 0)
                ?? throw ValidationException::withMessages(['account_id' => __('Choose the account the money was received into.')]);
        }

        return DB::transaction(function () use ($asset, $data, $user, $type, $date, $proceeds, $account) {
            $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if (! $asset->isLive()) {
                throw ValidationException::withMessages(['asset' => __('This asset is already disposed of or cancelled.')]);
            }
            if ($date->lt($asset->acquired_on)) {
                throw ValidationException::withMessages(['date' => __('The asset was acquired on :date.', ['date' => $asset->acquired_on->format('d M Y')])]);
            }
            if ($asset->logs()->whereIn('status', ['open', 'in_progress'])->exists()) {
                throw ValidationException::withMessages(['asset' => __('Complete or cancel the open work orders of this asset first.')]);
            }

            // Charge the months not yet depreciated, up to the month before the disposal.
            $this->depreciation->catchUp($asset, self::previousPeriod(self::periodOf($date)), $user, __('Before disposal of :code', ['code' => $asset->code]));
            $asset->refresh();

            $cost = round($asset->cost, 2);
            $accumulated = round($asset->accumulated_depreciation, 2);
            $book = round($cost - $accumulated, 2);
            $gainLoss = round($proceeds - $book, 2);

            $disposal = new Disposal(['asset_id' => $asset->id, 'type' => $type, 'date' => $date->toDateString(), 'cost' => $cost, 'accumulated' => $accumulated,
                'book_value' => $book, 'proceeds' => $proceeds, 'gain_loss' => $gainLoss, 'account_id' => $account?->id, 'buyer' => $this->clip($data['buyer'] ?? null, 150),
                'reason' => $this->clip($data['reason'] ?? null)]);
            $disposal->forceFill(['disposal_no' => $this->docNo('DSP', $date), 'status' => 'posted', 'disposed_by' => $user->id])->save();

            $text = __('Asset :code — :name', ['code' => $asset->code, 'name' => $asset->name]);
            $lines = [['account_id' => $this->chart->id('fixed_assets'), 'credit' => $cost, 'description' => $text]];
            if ($proceeds > 0) {
                $lines[] = ['account_id' => $account->id, 'debit' => $proceeds, 'description' => $text];
            }
            if ($accumulated > 0) {
                $lines[] = ['account_id' => $this->chart->id('accumulated_depreciation'), 'debit' => $accumulated, 'description' => $text];
            }
            if ($gainLoss > 0) {
                $lines[] = ['account_id' => $this->chart->id('asset_disposal_gain'), 'credit' => $gainLoss, 'description' => $text];
            } elseif ($gainLoss < 0) {
                $lines[] = ['account_id' => $this->chart->id('asset_disposal_loss'), 'debit' => abs($gainLoss), 'description' => $text];
            }
            $this->ledger->post('journal', $date, __('Asset disposal :no — :text', ['no' => $disposal->disposal_no, 'text' => $text]), $lines, $disposal, $user);

            // The open allocation (if any) ends with the disposal.
            $asset->allocations()->whereNull('returned_on')->get()->each(function ($allocation) use ($date, $user) {
                $allocation->forceFill(['returned_on' => $date->lt($allocation->allocated_on) ? $allocation->allocated_on : $date->toDateString(), 'returned_by' => $user->id,
                    'note' => $this->clip(trim(($allocation->note ? $allocation->note.' — ' : '').__('Asset disposed of')))])->save();
            });
            $asset->forceFill(['status' => 'disposed', 'disposed_on' => $date->toDateString(), 'is_under_repair' => false, 'is_missing' => false])->save();

            return $disposal;
        });
    }

    public function reverse(Disposal $disposal, string $reason, User $user): Disposal
    {
        return DB::transaction(function () use ($disposal, $reason, $user) {
            $disposal = Disposal::query()->whereKey($disposal->id)->lockForUpdate()->firstOrFail();
            if ($disposal->status !== 'posted') {
                throw ValidationException::withMessages(['status' => __('This disposal has already been reversed.')]);
            }
            $asset = Asset::query()->whereKey($disposal->asset_id)->lockForUpdate()->firstOrFail();
            $this->reverseEntries($disposal, $reason, $user);
            $asset->forceFill(['status' => 'in_stock', 'disposed_on' => null])->save();
            $disposal->forceFill(['status' => 'reversed', 'reason' => $this->clip(trim(($disposal->reason ? $disposal->reason.' — ' : '').__('Reversed: :reason', ['reason' => $reason])))])->save();

            return $disposal;
        });
    }
}
