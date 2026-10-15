<?php

namespace App\Http\Controllers\Branch\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\HR\Employee;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockIssue;
use App\Services\Inventory\InventoryService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Issue consumables from the store to a department, an employee or a named recipient. */
class IssueController extends Controller
{
    public function __construct(protected InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $this->authorize('inventory.view');
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();

        return view('branch.inventory.issues.index', [
            'issues' => StockIssue::query()->with(['department:id,name', 'employee:id,name', 'issuer:id,name'])->withCount('items')
                ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
                ->when($request->integer('department_id'), fn ($q, $d) => $q->where('department_id', $d))
                ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('issue_no', 'like', "%$t%")->orWhere('recipient', 'like', "%$t%")))
                ->latest('date')->latest('id')->paginate(30)->withQueryString(),
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id'), 'from' => $from, 'to' => $to,
        ]);
    }

    public function create(): View
    {
        $this->authorize('inventory.create');

        return view('branch.inventory.issues.create', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'employees' => Employee::query()->active()->orderBy('name')->pluck('name', 'id'),
            'items' => Item::query()->where('is_active', true)->where('item_type', 'consumable')->where('quantity_on_hand', '>', 0)->orderBy('name')->get()
                ->map(fn ($i) => ['id' => $i->id, 'label' => $i->code.' — '.$i->name, 'unit' => $i->unit, 'stock' => $i->quantity_on_hand])->all(),
            'lines' => old('lines', [['inventory_item_id' => '', 'quantity' => '']]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.create');
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'department_id' => ['nullable', TenantRule::exists('departments')],
            'employee_id' => ['nullable', TenantRule::exists('employees')],
            'recipient' => ['nullable', 'string', 'max:150'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:80'],
            'lines.*.inventory_item_id' => ['nullable', TenantRule::exists('inventory_items', 'id', true)],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
        $issue = $this->inventory->issue($data, $data['lines'], $request->user());

        return redirect()->route('branch.inventory.issues.show', $issue)->with('success', __('Stock issue :no posted.', ['no' => $issue->issue_no]));
    }

    public function show(StockIssue $issue): View
    {
        $this->authorize('inventory.view');

        return view('branch.inventory.issues.show', ['issue' => $issue->load(['items.item', 'department', 'employee', 'issuer'])]);
    }

    public function reverse(Request $request, StockIssue $issue): RedirectResponse
    {
        $this->authorize('inventory.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->inventory->reverseIssue($issue, $data['reason'], $request->user());

        return back()->with('success', __('Issue reversed — the goods are back in stock.'));
    }
}
