<?php

namespace App\Http\Controllers\Branch\Assets;

use App\Http\Controllers\Controller;
use App\Models\Assets\Allocation;
use App\Models\Assets\Asset;
use App\Services\Assets\AssetReportService;
use App\Services\Assets\AssetService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Who holds what: open allocations, handing an asset out and taking it back. */
class AllocationController extends Controller
{
    use AssetLookups;

    public function __construct(protected AssetService $assets) {}

    public function index(Request $request, AssetReportService $reports): View
    {
        $this->authorize('assets.view');

        return view('branch.assets.allocations', ['allocations' => $reports->allocations($request->only(['q', 'employee', 'department', 'overdue'])),
            'employees' => $this->employees(), 'departments' => $this->departments()]);
    }

    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('assets.update');
        $data = $request->validate([
            'department_id' => ['nullable', TenantRule::exists('departments', 'id', true)], 'employee_id' => ['nullable', TenantRule::exists('employees', 'id', true)],
            'room_id' => ['nullable', TenantRule::exists('rooms', 'id', true)], 'assignee' => ['nullable', 'string', 'max:150'],
            'allocated_on' => ['required', 'date', 'before_or_equal:today'], 'expected_return_on' => ['nullable', 'date', 'after_or_equal:allocated_on'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->assets->allocate($asset, $data, $request->user());

        return redirect()->route('branch.assets.register.show', $asset)->with('success', __('Asset handed over.'));
    }

    public function return(Request $request, Allocation $allocation): RedirectResponse
    {
        $this->authorize('assets.update');
        $data = $request->validate([
            'returned_on' => ['required', 'date', 'before_or_equal:today'], 'return_condition' => ['nullable', Rule::in(Asset::CONDITIONS)], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->assets->takeBack($allocation, $data, $request->user());

        return back()->with('success', __('Asset taken back.'));
    }
}
