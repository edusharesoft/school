<?php

namespace App\Services\Assets;

use App\Models\Assets\Allocation;
use App\Models\Assets\Asset;
use App\Models\Assets\Category;
use App\Models\Finance\Account;
use App\Models\Inventory\Item;
use App\Models\User;
use App\Services\Assets\Concerns\AssetHelpers;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use App\Services\Inventory\InventoryService;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The asset register. Cost, status and the books change ONLY here and in the depreciation / disposal services.
 *
 *  purchase    Dr Fixed assets / Cr cash · bank · mobile account
 *  stock       Dr Fixed assets / Cr Inventory stock          (a durable inventory item moves to the register at its average cost)
 *  donation    Dr Fixed assets / Cr Other income
 *  opening     Dr Fixed assets / Cr Accumulated depreciation (already charged) + Cr Opening balance equity (the rest)
 *
 * Goods bought on credit are received through inventory (which tracks the supplier's bill) and then moved to the register from stock.
 */
class AssetService
{
    use AssetHelpers;

    public function __construct(
        protected LedgerService $ledger,
        protected ChartOfAccounts $chart,
        protected SequenceService $sequences,
        protected TenantContext $tenant,
        protected InventoryService $inventory,
    ) {}

    // ───────────────────────────── registration ─────────────────────────────

    /**
     * Register one asset bought, donated or already owned (opening balance).
     *
     * @param  array<string,mixed>  $data
     */
    public function register(array $data, User $user): Asset
    {
        $type = $data['acquisition_type'] ?? 'purchase';
        if (! in_array($type, ['purchase', 'donation', 'opening'], true)) {
            throw ValidationException::withMessages(['acquisition_type' => __('Choose how the asset was acquired.')]);
        }
        $category = $this->category($data['asset_category_id'] ?? null);
        $attrs = $this->attributes($data, $category);
        $opening = 0.0;
        $postDate = $attrs['acquired_on'];
        $from = null;

        if ($type === 'purchase') {
            $from = Account::query()->money()->find($data['paid_from_account_id'] ?? 0)
                ?? throw ValidationException::withMessages(['paid_from_account_id' => __('Choose the cash, bank or mobile account the asset was paid from.')]);
        }
        if ($type === 'opening') {
            $opening = round((float) ($data['opening_accumulated'] ?? 0), 2);
            if ($opening < 0 || $opening > round($attrs['cost'] - $attrs['salvage_value'], 2)) {
                throw ValidationException::withMessages(['opening_accumulated' => __('Depreciation already charged cannot be negative or more than cost less salvage value.')]);
            }
            $postDate = CarbonImmutable::parse($data['opening_date'] ?? now())->startOfDay();
            if ($postDate->isFuture()) {
                throw ValidationException::withMessages(['opening_date' => __('The opening date cannot be in the future.')]);
            }
        }

        return DB::transaction(function () use ($attrs, $data, $type, $user, $opening, $postDate, $from) {
            $asset = new Asset($this->fillables($attrs));
            $asset->forceFill([
                'code' => sprintf('AS-%06d', $this->sequences->next('asset')), 'acquisition_type' => $type, 'acquired_on' => $attrs['acquired_on'],
                'in_service_on' => $attrs['in_service_on'], 'cost' => $attrs['cost'], 'salvage_value' => $attrs['salvage_value'],
                'depreciation_method' => $attrs['depreciation_method'], 'useful_life_months' => $attrs['useful_life_months'], 'declining_rate' => $attrs['declining_rate'],
                'depreciation_from' => $type === 'opening' ? ($data['depreciation_from'] ?? self::periodOf(now())) : self::periodOf($attrs['in_service_on']),
                'accumulated_depreciation' => $opening, 'status' => 'in_stock', 'created_by' => $user->id,
            ]);
            if ($type === 'opening' && ! self::validPeriod((string) $asset->depreciation_from)) {
                throw ValidationException::withMessages(['depreciation_from' => __('Enter the month as YYYY-MM.')]);
            }
            $asset->save();

            $cost = $attrs['cost'];
            $text = __('Asset :code — :name', ['code' => $asset->code, 'name' => $asset->name]);
            $lines = [['account_id' => $this->chart->id('fixed_assets'), 'debit' => $cost, 'description' => $text]];
            match ($type) {
                'purchase' => $lines[] = ['account_id' => $from->id, 'credit' => $cost, 'description' => $text],
                'donation' => $lines[] = ['account_id' => $this->chart->id('other_income'), 'credit' => $cost, 'description' => __('Donated asset')],
                'opening' => array_push($lines,
                    ['account_id' => $this->chart->id('accumulated_depreciation'), 'credit' => $opening, 'description' => $text],
                    ['account_id' => $this->chart->id('opening_balance'), 'credit' => round($cost - $opening, 2), 'description' => $text]),
            };
            $this->ledger->post('journal', $postDate, __('Asset registered — :text', ['text' => $text]), $lines, $asset, $user);

            return $asset;
        });
    }

    /**
     * Move units of a durable inventory item into the register: one asset per unit, each at the item's average cost at that moment.
     *
     * @param  array<string,mixed>  $data  common details (category, dates, room, warranty …); `serials` = one serial number per line
     * @return Collection<int,Asset>
     */
    public function capitaliseFromStock(Item $item, int $quantity, array $data, User $user): Collection
    {
        if ($item->item_type !== 'durable') {
            throw ValidationException::withMessages(['inventory_item_id' => __(':item is a consumable — only durable equipment goes to the asset register.', ['item' => $item->name])]);
        }
        if ($quantity < 1 || $quantity > 200) {
            throw ValidationException::withMessages(['quantity' => __('Move between 1 and 200 units at a time.')]);
        }
        $category = $this->category($data['asset_category_id'] ?? null);
        $date = CarbonImmutable::parse($data['acquired_on'] ?? now())->startOfDay();
        $serials = collect(preg_split('/\R/u', (string) ($data['serials'] ?? '')))->map(fn ($s) => trim($s))->filter()->values();
        if ($serials->count() > $quantity) {
            throw ValidationException::withMessages(['serials' => __('You gave more serial numbers than units.')]);
        }

        return DB::transaction(function () use ($item, $quantity, $data, $user, $category, $date, $serials) {
            $locked = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if (! $locked->is_active) {
                throw ValidationException::withMessages(['inventory_item_id' => __(':item is inactive.', ['item' => $locked->name])]);
            }
            if ($quantity > $locked->quantity_on_hand + 0.0005) {
                throw ValidationException::withMessages(['quantity' => __('Not enough stock of :item: :have :unit on hand, :need needed.', [
                    'item' => $locked->name, 'have' => rtrim(rtrim(number_format($locked->quantity_on_hand, 3, '.', ''), '0'), '.'), 'unit' => $locked->unit, 'need' => $quantity,
                ])]);
            }
            $created = collect();
            for ($i = 0; $i < $quantity; $i++) {
                $attrs = $this->attributes(['name' => $data['name'] ?? $locked->name, 'name_bn' => $data['name_bn'] ?? $locked->name_bn, 'cost' => 0.01, 'serial_no' => $serials[$i] ?? null,
                    'acquired_on' => $date->toDateString()] + $data, $category, costFromStock: true);
                $asset = new Asset($this->fillables($attrs));
                $asset->forceFill([
                    'code' => sprintf('AS-%06d', $this->sequences->next('asset')), 'acquisition_type' => 'stock', 'inventory_item_id' => $locked->id,
                    'acquired_on' => $attrs['acquired_on'], 'in_service_on' => $attrs['in_service_on'], 'cost' => 0, 'salvage_value' => 0,
                    'depreciation_method' => $attrs['depreciation_method'], 'useful_life_months' => $attrs['useful_life_months'], 'declining_rate' => $attrs['declining_rate'],
                    'depreciation_from' => self::periodOf($attrs['in_service_on']), 'status' => 'in_stock', 'created_by' => $user->id,
                ])->save();

                $tx = $this->inventory->move($locked, 'issue', -1, 0, $date, $asset, $asset->code, __('Moved to the asset register as :code', ['code' => $asset->code]), $user);
                $cost = round(abs($tx->amount), 2);
                if ($cost <= 0) {
                    throw ValidationException::withMessages(['quantity' => __(':item has no stock value, so it cannot be capitalised. Post its cost through goods receiving or a stock adjustment first.', ['item' => $locked->name])]);
                }
                $salvage = round($cost * $category->salvage_percent / 100, 2);
                $asset->forceFill(['cost' => $cost, 'salvage_value' => $salvage])->save();

                $text = __('Asset :code — :name', ['code' => $asset->code, 'name' => $asset->name]);
                $this->ledger->post('journal', $date, __('Stock moved to assets — :text', ['text' => $text]), [
                    ['account_id' => $this->chart->id('fixed_assets'), 'debit' => $cost, 'description' => $text],
                    ['account_id' => $this->chart->id('inventory_stock'), 'credit' => $cost, 'description' => $text],
                ], $asset, $user);
                $created->push($asset);
            }

            return $created;
        });
    }

    /** Cancel a registration made by mistake — only while nothing has happened to the asset. */
    public function void(Asset $asset, string $reason, User $user): Asset
    {
        return DB::transaction(function () use ($asset, $reason, $user) {
            $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if (! $asset->isLive()) {
                throw ValidationException::withMessages(['asset' => __('This asset is already disposed of or cancelled.')]);
            }
            if ($asset->status === 'in_use' || $asset->allocations()->exists() || $asset->depreciations()->where('is_reversed', false)->exists()
                || $asset->logs()->where('status', 'completed')->exists() || $asset->disposal()->exists()) {
                throw ValidationException::withMessages(['asset' => __('This asset has already been used, allocated, depreciated or serviced — dispose of it instead of cancelling the registration.')]);
            }
            if ($asset->logs()->whereIn('status', ['open', 'in_progress'])->exists()) {
                throw ValidationException::withMessages(['asset' => __('Close its open work orders first.')]);
            }
            if ($asset->acquisition_type === 'stock' && $asset->stockItem) {
                $this->inventory->moveExact($asset->stockItem, 'reversal', 1, $asset->cost, now(), $asset, $asset->code, __('Registration cancelled — :reason', ['reason' => $reason]), $user);
            }
            $this->reverseEntries($asset, $reason, $user);
            $asset->forceFill(['status' => 'voided', 'is_under_repair' => false])->save();

            return $asset;
        });
    }

    // ───────────────────────────── details (no effect on the books) ─────────────────────────────

    /** Depreciation settings may change only while no depreciation has been posted for the asset. */
    public function depreciationLocked(Asset $asset): bool
    {
        return $asset->depreciations()->where('is_reversed', false)->exists();
    }

    public function updateDepreciation(Asset $asset, array $data): Asset
    {
        if ($this->depreciationLocked($asset)) {
            throw ValidationException::withMessages(['depreciation_method' => __('Depreciation has already been posted — reverse it first to change the settings.')]);
        }
        $method = $data['depreciation_method'] ?? $asset->depreciation_method;
        $life = (int) ($data['useful_life_months'] ?? $asset->useful_life_months);
        $rate = (float) ($data['declining_rate'] ?? $asset->declining_rate);
        $salvage = round((float) ($data['salvage_value'] ?? $asset->salvage_value), 2);
        $this->assertDepreciation($method, $life, $rate, $salvage, $asset->cost);
        $inService = CarbonImmutable::parse($data['in_service_on'] ?? $asset->in_service_on)->startOfDay();
        $from = $asset->acquisition_type === 'opening' ? ($data['depreciation_from'] ?? $asset->depreciation_from) : self::periodOf($inService);
        if (! self::validPeriod((string) $from)) {
            throw ValidationException::withMessages(['depreciation_from' => __('Enter the month as YYYY-MM.')]);
        }
        if ($salvage + $asset->accumulated_depreciation > $asset->cost + 0.005) {
            throw ValidationException::withMessages(['salvage_value' => __('Salvage value plus depreciation already charged cannot exceed the cost.')]);
        }
        $asset->forceFill(['depreciation_method' => $method, 'useful_life_months' => $method === 'straight_line' ? $life : 0,
            'declining_rate' => $method === 'declining_balance' ? $rate : 0, 'salvage_value' => $salvage, 'in_service_on' => $inService->toDateString(), 'depreciation_from' => $from])->save();

        return $asset;
    }

    // ───────────────────────────── allocation ─────────────────────────────

    /** @param array{department_id?:?int, employee_id?:?int, room_id?:?int, assignee?:?string, allocated_on:string, expected_return_on?:?string, note?:?string} $data */
    public function allocate(Asset $asset, array $data, User $user): Allocation
    {
        $on = CarbonImmutable::parse($data['allocated_on'])->startOfDay();
        $due = ! empty($data['expected_return_on']) ? CarbonImmutable::parse($data['expected_return_on'])->startOfDay() : null;
        if ($on->isFuture()) {
            throw ValidationException::withMessages(['allocated_on' => __('The allocation date cannot be in the future.')]);
        }
        if ($due && $due->lt($on)) {
            throw ValidationException::withMessages(['expected_return_on' => __('The return date cannot be before the allocation date.')]);
        }
        if (empty($data['department_id']) && empty($data['employee_id']) && empty($data['room_id']) && trim((string) ($data['assignee'] ?? '')) === '') {
            throw ValidationException::withMessages(['assignee' => __('Say who gets the asset — a department, an employee, a room or a name.')]);
        }

        return DB::transaction(function () use ($asset, $data, $user, $on, $due) {
            $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if (! $asset->isLive()) {
                throw ValidationException::withMessages(['asset' => __('A disposed or cancelled asset cannot be allocated.')]);
            }
            if ($asset->is_under_repair) {
                throw ValidationException::withMessages(['asset' => __('The asset is under repair — finish the work order first.')]);
            }
            if ($asset->is_missing) {
                throw ValidationException::withMessages(['asset' => __('The asset is marked missing.')]);
            }
            if ($asset->allocations()->whereNull('returned_on')->exists()) {
                throw ValidationException::withMessages(['asset' => __('The asset is already allocated — take it back first.')]);
            }
            if ($on->lt($asset->acquired_on)) {
                throw ValidationException::withMessages(['allocated_on' => __('The asset was acquired on :date.', ['date' => $asset->acquired_on->format('d M Y')])]);
            }
            $allocation = new Allocation(['asset_id' => $asset->id, 'department_id' => $data['department_id'] ?? null, 'employee_id' => $data['employee_id'] ?? null,
                'room_id' => $data['room_id'] ?? null, 'assignee' => $this->clip($data['assignee'] ?? null, 150), 'allocated_on' => $on->toDateString(),
                'expected_return_on' => $due?->toDateString(), 'note' => $this->clip($data['note'] ?? null)]);
            $allocation->forceFill(['allocated_by' => $user->id])->save();
            $asset->forceFill(['status' => 'in_use'] + (! empty($data['room_id']) ? ['room_id' => $data['room_id']] : []))->save();

            return $allocation;
        });
    }

    /** @param array{returned_on?:?string, return_condition?:?string, note?:?string} $data */
    public function takeBack(Allocation $allocation, array $data, User $user): Allocation
    {
        $on = CarbonImmutable::parse($data['returned_on'] ?? now())->startOfDay();
        $condition = $data['return_condition'] ?? null;
        if ($condition !== null && $condition !== '' && ! in_array($condition, Asset::CONDITIONS, true)) {
            throw ValidationException::withMessages(['return_condition' => __('Choose a condition.')]);
        }

        return DB::transaction(function () use ($allocation, $on, $condition, $data, $user) {
            $allocation = Allocation::query()->whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            if (! $allocation->isOpen()) {
                throw ValidationException::withMessages(['allocation' => __('This asset has already been taken back.')]);
            }
            if ($on->isFuture() || $on->lt($allocation->allocated_on)) {
                throw ValidationException::withMessages(['returned_on' => __('The return date must be between the allocation date and today.')]);
            }
            $allocation->forceFill(['returned_on' => $on->toDateString(), 'return_condition' => $condition ?: null, 'returned_by' => $user->id,
                'note' => $this->clip(trim(($allocation->note ? $allocation->note.' — ' : '').($data['note'] ?? '')))])->save();
            $asset = Asset::query()->whereKey($allocation->asset_id)->lockForUpdate()->first();
            if ($asset && $asset->status === 'in_use') {
                $asset->forceFill(['status' => 'in_stock'] + ($condition ? ['condition' => $condition] : []))->save();
            }

            return $allocation;
        });
    }

    // ───────────────────────────── helpers ─────────────────────────────

    protected function category(mixed $id): Category
    {
        $category = Category::query()->where('is_active', true)->find($id);

        return $category ?? throw ValidationException::withMessages(['asset_category_id' => __('Choose an active category.')]);
    }

    protected function assertDepreciation(string $method, int $life, float $rate, float $salvage, float $cost): void
    {
        if (! in_array($method, Category::METHODS, true)) {
            throw ValidationException::withMessages(['depreciation_method' => __('Choose a depreciation method.')]);
        }
        if ($method === 'straight_line' && ($life < 1 || $life > 1200)) {
            throw ValidationException::withMessages(['useful_life_months' => __('Straight-line depreciation needs a useful life of 1 to 1200 months.')]);
        }
        if ($method === 'declining_balance' && ($rate <= 0 || $rate > 100)) {
            throw ValidationException::withMessages(['declining_rate' => __('Enter the yearly rate (above 0, up to 100 %).')]);
        }
        if ($salvage < 0 || $salvage > $cost) {
            throw ValidationException::withMessages(['salvage_value' => __('Salvage value must be between 0 and the cost.')]);
        }
    }

    /** Clean, validated attributes of an asset (category defaults filled in). */
    protected function attributes(array $data, Category $category, bool $costFromStock = false): array
    {
        $cost = round((float) ($data['cost'] ?? 0), 2);
        if ($cost <= 0) {
            throw ValidationException::withMessages(['cost' => __('The cost must be more than zero.')]);
        }
        $acquired = CarbonImmutable::parse($data['acquired_on'] ?? now())->startOfDay();
        if ($acquired->isFuture()) {
            throw ValidationException::withMessages(['acquired_on' => __('The acquisition date cannot be in the future.')]);
        }
        $inService = CarbonImmutable::parse($data['in_service_on'] ?? $acquired)->startOfDay();
        if ($inService->lt($acquired)) {
            throw ValidationException::withMessages(['in_service_on' => __('It cannot enter service before it was acquired.')]);
        }
        $method = $data['depreciation_method'] ?? $category->depreciation_method;
        $life = (int) (($data['useful_life_months'] ?? '') !== '' ? $data['useful_life_months'] : $category->useful_life_months);
        $rate = (float) (($data['declining_rate'] ?? '') !== '' ? $data['declining_rate'] : $category->declining_rate);
        $salvage = ($data['salvage_value'] ?? '') !== '' && $data['salvage_value'] !== null ? round((float) $data['salvage_value'], 2) : round($cost * $category->salvage_percent / 100, 2);
        if (! $costFromStock) {
            $this->assertDepreciation($method, $life, $rate, $salvage, $cost);
        } else {
            $this->assertDepreciation($method, $life, $rate, 0, $cost);
        }
        $condition = $data['condition'] ?? 'new';
        if (! in_array($condition, Asset::CONDITIONS, true)) {
            throw ValidationException::withMessages(['condition' => __('Choose a condition.')]);
        }

        return [
            'name' => trim((string) ($data['name'] ?? '')) ?: throw ValidationException::withMessages(['name' => __('Enter a name.')]),
            'name_bn' => $this->clip($data['name_bn'] ?? null, 150), 'asset_category_id' => $category->id, 'serial_no' => $this->clip($data['serial_no'] ?? null, 80),
            'brand' => $this->clip($data['brand'] ?? null, 80), 'model' => $this->clip($data['model'] ?? null, 80), 'vendor_id' => $data['vendor_id'] ?? null,
            'bill_no' => $this->clip($data['bill_no'] ?? null, 60), 'condition' => $condition, 'room_id' => $data['room_id'] ?? null,
            'location' => $this->clip($data['location'] ?? null, 100), 'warranty_expires_on' => ! empty($data['warranty_expires_on']) ? $data['warranty_expires_on'] : null,
            'safety_notes' => $this->clip($data['safety_notes'] ?? null), 'description' => $this->clip($data['description'] ?? null),
            'cost' => $cost, 'salvage_value' => $salvage, 'acquired_on' => $acquired->toDateString(), 'in_service_on' => $inService->toDateString(),
            'depreciation_method' => $method, 'useful_life_months' => $method === 'straight_line' ? $life : 0, 'declining_rate' => $method === 'declining_balance' ? $rate : 0,
        ];
    }

    /** The mass-assignable subset of the attributes. */
    protected function fillables(array $attrs): array
    {
        return array_intersect_key($attrs, array_flip((new Asset)->getFillable()));
    }
}
