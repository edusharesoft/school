<?php

namespace App\Http\Controllers\Branch\Assets;

use App\Http\Controllers\Controller;
use App\Models\Assets\Asset;
use App\Models\Assets\MaintenanceLog;
use App\Models\Assets\MaintenancePlan;
use App\Services\Assets\MaintenanceService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Plans (service, calibration, safety checks) and work orders (including repairs). */
class MaintenanceController extends Controller
{
    use AssetLookups;

    public function __construct(protected MaintenanceService $maintenance) {}

    /** Work orders. */
    public function index(Request $request): View
    {
        $this->authorize('assets.view');

        return view('branch.assets.maintenance.orders', [
            'logs' => MaintenanceLog::query()->with(['asset:id,code,name,name_bn', 'vendor:id,name'])
                ->when($request->input('status') === 'open', fn ($q) => $q->whereIn('status', MaintenanceLog::OPEN))
                ->when(in_array($request->input('status'), ['completed', 'cancelled'], true), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->input('kind'), fn ($q, $k) => $q->where('kind', $k))
                ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('log_no', 'like', "%$t%")->orWhere('title', 'like', "%$t%")->orWhereHas('asset', fn ($a) => $a->search($t))))
                ->latest('reported_on')->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function plans(Request $request): View
    {
        $this->authorize('assets.view');
        $plans = MaintenancePlan::query()->with(['asset:id,code,name,name_bn,status', 'employee:id,name'])
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false), fn ($q) => $q->where('is_active', true))
            ->when($request->input('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereDate('next_due_on', '<', now()->toDateString()))
            ->orderBy('next_due_on')->paginate(40)->withQueryString();

        return view('branch.assets.maintenance.plans', ['plans' => $plans, 'employees' => $this->employees(), 'vendors' => $this->vendors()]);
    }

    public function storePlan(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('assets.update');
        $data = $request->validate($this->planRules() + ['start_on' => ['required', 'date'], 'last_done_on' => ['nullable', 'date', 'before_or_equal:today']]);
        $this->maintenance->createPlan($asset, $data);

        return back()->with('success', __('Task scheduled.'));
    }

    public function updatePlan(Request $request, MaintenancePlan $plan): RedirectResponse
    {
        $this->authorize('assets.update');
        $data = $request->validate($this->planRules() + ['next_due_on' => ['nullable', 'date']]);
        $data['is_active'] = $request->boolean('is_active', true);
        $this->maintenance->updatePlan($plan, $data);

        return back()->with('success', __('Task saved.'));
    }

    public function donePlan(Request $request, MaintenancePlan $plan): RedirectResponse
    {
        $this->authorize('assets.update');
        $data = $request->validate([
            'completed_on' => ['required', 'date', 'before_or_equal:today'], 'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'reference' => ['nullable', 'string', 'max:100'], 'outcome' => ['nullable', 'string', 'max:255'], 'vendor_id' => ['nullable', TenantRule::exists('vendors')],
            'condition_after' => ['nullable', Rule::in(Asset::CONDITIONS)],
        ]);
        $log = $this->maintenance->markPlanDone($plan->load('asset'), $data, $request->user());

        return back()->with('success', __('Done — :no recorded, next due :date.', ['no' => $log->log_no, 'date' => $plan->fresh()->next_due_on->format('d M Y')]));
    }

    public function storeOrder(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('assets.update');
        $data = $request->validate([
            'kind' => ['required', Rule::in(MaintenanceLog::KINDS)], 'title' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'],
            'reported_on' => ['required', 'date', 'before_or_equal:today'], 'employee_id' => ['nullable', TenantRule::exists('employees', 'id', true)],
            'vendor_id' => ['nullable', TenantRule::exists('vendors')],
        ]);
        $data['takes_offline'] = $request->boolean('takes_offline');
        $log = $this->maintenance->createLog($asset, $data, $request->user());

        return redirect()->route('branch.assets.maintenance.orders.show', $log)->with('success', __('Work order :no opened.', ['no' => $log->log_no]));
    }

    public function showOrder(MaintenanceLog $log): View
    {
        $this->authorize('assets.view');

        return view('branch.assets.maintenance.show', ['log' => $log->load(['asset.category', 'plan', 'vendor', 'employee', 'creator']), 'vendors' => $this->vendors()]);
    }

    public function start(MaintenanceLog $log): RedirectResponse
    {
        $this->authorize('assets.update');
        $this->maintenance->start($log);

        return back()->with('success', __('Work started.'));
    }

    public function complete(Request $request, MaintenanceLog $log): RedirectResponse
    {
        $this->authorize('assets.update');
        $data = $request->validate([
            'completed_on' => ['required', 'date', 'before_or_equal:today'], 'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'reference' => ['nullable', 'string', 'max:100'],
            'outcome' => ['nullable', 'string', 'max:255'], 'condition_after' => ['nullable', Rule::in(Asset::CONDITIONS)], 'vendor_id' => ['nullable', TenantRule::exists('vendors')],
        ]);
        $this->maintenance->complete($log, $data, $request->user());

        return back()->with('success', __('Work order completed.'));
    }

    public function cancel(Request $request, MaintenanceLog $log): RedirectResponse
    {
        $this->authorize('assets.update');
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->maintenance->cancel($log, $data['reason']);

        return back()->with('success', __('Work order cancelled.'));
    }

    protected function planRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'], 'kind' => ['required', Rule::in(MaintenancePlan::KINDS)], 'interval_months' => ['required', 'integer', 'min:1', 'max:120'],
            'employee_id' => ['nullable', TenantRule::exists('employees', 'id', true)], 'vendor_id' => ['nullable', TenantRule::exists('vendors')], 'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
