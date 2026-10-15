<?php

namespace App\Http\Controllers\Branch\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Inventory\Item;
use App\Models\Inventory\Requisition;
use App\Services\Inventory\ProcurementService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Purchase requests: anyone with inventory.create asks, inventory.approve decides (never on their own request). */
class RequisitionController extends Controller
{
    public function __construct(protected ProcurementService $procurement) {}

    public function index(Request $request): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.requisitions.index', [
            'requisitions' => Requisition::query()->with(['requester:id,name', 'department:id,name'])->withCount('items')
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('requisition_no', 'like', "%$t%")->orWhere('purpose', 'like', "%$t%")))
                ->latest('date')->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('inventory.create');

        return view('branch.inventory.requisitions.create', [
            'items' => $this->itemOptions(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'lines' => old('lines', [['inventory_item_id' => '', 'quantity' => '', 'estimated_unit_cost' => '']]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.create');
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:date'],
            'department_id' => ['nullable', TenantRule::exists('departments')],
            'purpose' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:80'],
            'lines.*.inventory_item_id' => ['nullable', TenantRule::exists('inventory_items', 'id', true)],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ]);
        $req = $this->procurement->createRequisition($data, $data['lines'], $request->user());

        return redirect()->route('branch.inventory.requisitions.show', $req)->with('success', __('Purchase request :no sent for approval.', ['no' => $req->requisition_no]));
    }

    public function show(Requisition $requisition): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.requisitions.show', ['requisition' => $requisition->load(['items.item', 'requester', 'approver', 'department']), 'orders' => $requisition->orders()->get()]);
    }

    public function approve(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('inventory.approve');
        $this->procurement->approveRequisition($requisition, $request->user(), $request->input('review_note'));

        return back()->with('success', __('Purchase request approved.'));
    }

    public function reject(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('inventory.approve');
        $data = $request->validate(['review_note' => ['required', 'string', 'max:255']]);
        $this->procurement->rejectRequisition($requisition, $data['review_note'], $request->user());

        return back()->with('success', __('Purchase request rejected.'));
    }

    public function cancel(Request $request, Requisition $requisition): RedirectResponse
    {
        abort_unless($request->user()->can('inventory.approve') || ((int) $requisition->requested_by === (int) $request->user()->id && $request->user()->can('inventory.create')), 403);
        $this->procurement->cancelRequisition($requisition);

        return back()->with('success', __('Purchase request cancelled.'));
    }

    protected function itemOptions(): array
    {
        return Item::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'unit', 'quantity_on_hand', 'stock_value'])
            ->map(fn ($i) => ['id' => $i->id, 'label' => $i->code.' — '.$i->name, 'unit' => $i->unit, 'stock' => $i->quantity_on_hand, 'cost' => $i->averageCost()])->all();
    }
}
