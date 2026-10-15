<?php

namespace App\Http\Controllers\Branch\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Models\Inventory\StockAudit;
use App\Services\Inventory\InventoryService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Stock audit: open a count sheet, enter what was counted, complete → differences become one "recount" adjustment. */
class AuditController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function index(): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.audits.index', [
            'audits' => StockAudit::query()->with(['category:id,name', 'starter:id,name'])->withCount('items')->withCount(['items as counted_count' => fn ($q) => $q->whereNotNull('counted_quantity')])
                ->latest('date')->latest('id')->paginate(30),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.create');
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'], 'title' => ['required', 'string', 'max:150'],
            'inventory_category_id' => ['nullable', TenantRule::exists('inventory_categories')], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $audit = $this->inventory->startAudit($data, $request->user());

        return redirect()->route('branch.inventory.audits.show', $audit)->with('success', __('Count sheet :no opened.', ['no' => $audit->audit_no]));
    }

    public function show(StockAudit $audit): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.audits.show', ['audit' => $audit->load(['category', 'starter', 'adjustment']), 'lines' => $audit->items()->with('item')->get()->sortBy(fn ($l) => $l->item?->name)->values()]);
    }

    public function counts(Request $request, StockAudit $audit): RedirectResponse
    {
        $this->authorize('inventory.update');
        $data = $request->validate(['counts' => ['required', 'array'], 'counts.*.counted' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'counts.*.note' => ['nullable', 'string', 'max:255']]);
        $saved = $this->inventory->saveCounts($audit, $data['counts']);

        return back()->with('success', __(':n lines saved.', ['n' => $saved]));
    }

    public function complete(Request $request, StockAudit $audit): RedirectResponse
    {
        $this->authorize('inventory.approve');
        $this->inventory->completeAudit($audit, $request->user());

        return redirect()->route('branch.inventory.audits.show', $audit)->with('success', __('Audit completed — differences were posted as a stock adjustment.'));
    }

    public function cancel(StockAudit $audit): RedirectResponse
    {
        $this->authorize('inventory.update');
        $this->inventory->cancelAudit($audit);

        return redirect()->route('branch.inventory.audits.index')->with('success', __('Audit cancelled.'));
    }
}
