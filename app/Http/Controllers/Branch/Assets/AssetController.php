<?php

namespace App\Http\Controllers\Branch\Assets;

use App\Http\Controllers\Controller;
use App\Models\Assets\Asset;
use App\Models\Assets\Category;
use App\Models\Inventory\Item;
use App\Services\Assets\AssetReportService;
use App\Services\Assets\AssetService;
use App\Services\Assets\DepreciationService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The register: list, registration, stock → asset, details, tags, CSV. */
class AssetController extends Controller
{
    use AssetLookups;

    public function __construct(protected AssetService $assets) {}

    public function index(Request $request, AssetReportService $reports): View
    {
        $this->authorize('assets.view');
        $filters = $request->only(['q', 'category', 'status', 'room', 'condition', 'kind', 'warranty']);

        return view('branch.assets.register.index', [
            'assets' => $reports->register($filters), 'totals' => $reports->registerTotals($filters),
            'categories' => Category::query()->orderBy('name')->pluck('name', 'id'), 'rooms' => $this->rooms(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('assets.create');

        return view('branch.assets.register.form', ['asset' => null, 'locked' => false] + $this->lookups());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('assets.create');
        $data = $this->validated($request);
        $quantity = max(1, (int) $request->input('quantity', 1));
        if (($data['acquisition_type'] === 'opening') && ! $request->user()->can('assets.approve')) {
            abort(403);
        }
        if ($quantity > 1 && $data['acquisition_type'] === 'opening') {
            $quantity = 1;
        }
        $created = DB::transaction(fn () => collect(range(1, min($quantity, 50)))->map(fn ($i) => $this->assets->register($data, $request->user())));

        return $created->count() === 1
            ? redirect()->route('branch.assets.register.show', $created->first())->with('success', __('Asset :code registered.', ['code' => $created->first()->code]))
            : redirect()->route('branch.assets.register.index')->with('success', __(':n assets registered (:first – :last).', ['n' => $created->count(), 'first' => $created->first()->code, 'last' => $created->last()->code]));
    }

    public function fromStock(Request $request): View
    {
        $this->authorize('assets.create');
        $items = Item::query()->where('is_active', true)->where('item_type', 'durable')->where('quantity_on_hand', '>', 0)->orderBy('name')->get();

        return view('branch.assets.register.from-stock', ['items' => $items, 'selected' => $request->integer('item')] + $this->lookups());
    }

    public function storeFromStock(Request $request): RedirectResponse
    {
        $this->authorize('assets.create');
        $data = $request->validate([
            'inventory_item_id' => ['required', TenantRule::exists('inventory_items', 'id', true)],
            'quantity' => ['required', 'integer', 'min:1', 'max:200'],
            'asset_category_id' => ['required', TenantRule::exists('asset_categories')],
            'name' => ['nullable', 'string', 'max:150'], 'acquired_on' => ['required', 'date', 'before_or_equal:today'],
            'in_service_on' => ['nullable', 'date', 'after_or_equal:acquired_on'],
            'serials' => ['nullable', 'string', 'max:5000'], 'condition' => ['required', Rule::in(Asset::CONDITIONS)],
            'room_id' => ['nullable', TenantRule::exists('rooms', 'id', true)], 'location' => ['nullable', 'string', 'max:100'],
            'warranty_expires_on' => ['nullable', 'date'], 'safety_notes' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:255'],
            'depreciation_method' => ['nullable', Rule::in(Category::METHODS)], 'useful_life_months' => ['nullable', 'integer', 'min:0', 'max:1200'], 'declining_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $item = Item::query()->findOrFail($data['inventory_item_id']);
        $created = $this->assets->capitaliseFromStock($item, (int) $data['quantity'], $data, $request->user());

        return $created->count() === 1
            ? redirect()->route('branch.assets.register.show', $created->first())->with('success', __('Asset :code registered.', ['code' => $created->first()->code]))
            : redirect()->route('branch.assets.register.index')->with('success', __(':n assets registered (:first – :last).', ['n' => $created->count(), 'first' => $created->first()->code, 'last' => $created->last()->code]));
    }

    public function show(Asset $asset, DepreciationService $depreciation): View
    {
        $this->authorize('assets.view');
        $asset->load(['category', 'vendor', 'room', 'stockItem', 'creator', 'currentAllocation.employee', 'currentAllocation.department', 'currentAllocation.room',
            'allocations.employee', 'allocations.department', 'allocations.room', 'plans.employee', 'logs.vendor', 'disposal']);

        return view('branch.assets.register.show', [
            'asset' => $asset, 'depreciations' => $asset->depreciations()->with('run:id,run_no,status')->limit(36)->get(), 'forecast' => array_slice($depreciation->forecast($asset, 12), 0, 12),
            'employees' => $this->employees(), 'departments' => $this->departments(),
            'locked' => $this->assets->depreciationLocked($asset), 'journal' => app(\App\Services\Finance\LedgerService::class)->entriesFor($asset),
        ] + $this->lookups());
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('assets.update');

        return view('branch.assets.register.form', ['asset' => $asset, 'locked' => $this->assets->depreciationLocked($asset)] + $this->lookups());
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('assets.update');
        abort_unless($asset->isLive(), 403);
        $data = $request->validate($this->detailRules($asset) + [
            'condition' => ['required', Rule::in(Asset::CONDITIONS)],
        ]);
        $asset->update(collect($data)->only((new Asset)->getFillable())->all());
        if ($request->hasAny(['depreciation_method', 'salvage_value', 'useful_life_months', 'declining_rate', 'in_service_on', 'depreciation_from']) && $request->user()->can('assets.approve')
            && ! $this->assets->depreciationLocked($asset)) {
            $this->assets->updateDepreciation($asset, $request->only(['depreciation_method', 'salvage_value', 'useful_life_months', 'declining_rate', 'in_service_on', 'depreciation_from']));
        }

        return redirect()->route('branch.assets.register.show', $asset)->with('success', __('Asset saved.'));
    }

    public function void(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('assets.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->assets->void($asset, $data['reason'], $request->user());

        return redirect()->route('branch.assets.register.index')->with('success', __('Registration cancelled.'));
    }

    /** Printable asset tags with a Code 128 barcode (the code is also what the audit sheet shows). */
    public function labels(Request $request): View
    {
        $this->authorize('assets.view');
        $assets = Asset::query()->with('category:id,name')->live()
            ->when($request->input('ids'), fn ($q, $ids) => $q->whereIn('id', array_filter(array_map('intval', explode(',', (string) $ids)))))
            ->when($request->integer('category'), fn ($q, $c) => $q->where('asset_category_id', $c))
            ->orderBy('code')->limit(300)->get();

        return view('branch.assets.register.labels', ['assets' => $assets, 'branch' => tenant()->branch()]);
    }

    public function export(Request $request, AssetReportService $reports): StreamedResponse
    {
        $this->authorize('assets.view');
        $query = $reports->registerQuery($request->only(['q', 'category', 'status', 'room', 'condition', 'kind', 'warranty']))->orderBy('code');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Code', 'Name', 'Category', 'Serial', 'Brand', 'Model', 'Status', 'Condition', 'Location', 'Held by', 'Acquired', 'Cost', 'Depreciation', 'Book value', 'Warranty until']);
            $query->lazy(200)->each(function (Asset $a) use ($out) {
                fputcsv($out, [$a->code, $a->name, $a->category?->name, $a->serial_no, $a->brand, $a->model, $a->status.($a->is_under_repair ? ' (repair)' : '').($a->is_missing ? ' (missing)' : ''),
                    $a->condition, collect([$a->room?->name, $a->location])->filter()->implode(' · '), $a->currentAllocation?->holderLabel(), $a->acquired_on->toDateString(),
                    number_format($a->cost, 2, '.', ''), number_format($a->accumulated_depreciation, 2, '.', ''), number_format($a->bookValue(), 2, '.', ''), $a->warranty_expires_on?->toDateString()]);
            });
            fclose($out);
        }, 'asset-register-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function lookups(): array
    {
        return ['categories' => $this->categories(), 'rooms' => $this->rooms(), 'vendors' => $this->vendors(), 'moneyAccounts' => $this->moneyAccounts()];
    }

    protected function detailRules(?Asset $asset = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], 'name_bn' => ['nullable', 'string', 'max:150'],
            'asset_category_id' => ['required', TenantRule::exists('asset_categories')],
            'serial_no' => ['nullable', 'string', 'max:80'], 'brand' => ['nullable', 'string', 'max:80'], 'model' => ['nullable', 'string', 'max:80'],
            'vendor_id' => ['nullable', TenantRule::exists('vendors')], 'bill_no' => ['nullable', 'string', 'max:60'],
            'room_id' => ['nullable', TenantRule::exists('rooms', 'id', true)], 'location' => ['nullable', 'string', 'max:100'],
            'warranty_expires_on' => ['nullable', 'date'], 'safety_notes' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validated(Request $request): array
    {
        return $request->validate($this->detailRules() + [
            'acquisition_type' => ['required', Rule::in(['purchase', 'donation', 'opening'])],
            'acquired_on' => ['required', 'date', 'before_or_equal:today'], 'in_service_on' => ['nullable', 'date', 'after_or_equal:acquired_on'],
            'cost' => ['required', 'numeric', 'min:0.01', 'max:99999999'], 'salvage_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'paid_from_account_id' => ['required_if:acquisition_type,purchase', 'nullable', 'integer'],
            'condition' => ['required', Rule::in(Asset::CONDITIONS)],
            'depreciation_method' => ['nullable', Rule::in(Category::METHODS)], 'useful_life_months' => ['nullable', 'integer', 'min:0', 'max:1200'], 'declining_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'opening_accumulated' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'opening_date' => ['nullable', 'date', 'before_or_equal:today'],
            'depreciation_from' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
    }
}
