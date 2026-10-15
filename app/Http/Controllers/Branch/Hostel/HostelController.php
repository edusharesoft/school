<?php

namespace App\Http\Controllers\Branch\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Finance\FeeInvoice;
use App\Models\HR\Employee;
use App\Models\Hostel\Allocation;
use App\Models\Hostel\Hostel;
use App\Models\Hostel\MaintenanceRequest;
use App\Models\Hostel\Visitor;
use App\Services\Hostel\HostelService;
use App\Support\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Hostels (buildings), the home overview, and the monthly fee run + payment report. permission: hostel.* */
class HostelController extends Controller
{
    public function __construct(protected HostelService $hostels) {}

    public function index(): View
    {
        $this->authorize('hostel.view');
        $hostels = Hostel::query()->with('warden:id,name,phone')->orderBy('name')->get()->each(fn (Hostel $h) => $h->setAttribute('occupancy', $this->hostels->occupancy($h)));

        return view('branch.hostel.index', [
            'hostels' => $hostels,
            'stats' => [
                'beds' => $hostels->sum(fn ($h) => $h->occupancy['beds']), 'occupied' => $hostels->sum(fn ($h) => $h->occupancy['occupied']),
                'visitors' => Visitor::query()->whereNull('out_at')->count(), 'requests' => MaintenanceRequest::query()->whereIn('status', ['open', 'in_progress'])->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('hostel.create');

        return view('branch.hostel.form', ['hostel' => new Hostel(['type' => 'boys', 'is_active' => true]), 'wardens' => $this->wardens()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('hostel.create');
        Hostel::query()->create($this->validated($request));

        return redirect()->route('branch.hostel.index')->with('success', __('Hostel saved.'));
    }

    public function edit(Hostel $hostel): View
    {
        $this->authorize('hostel.update');

        return view('branch.hostel.form', ['hostel' => $hostel, 'wardens' => $this->wardens()]);
    }

    public function update(Request $request, Hostel $hostel): RedirectResponse
    {
        $this->authorize('hostel.update');
        $hostel->update($this->validated($request, $hostel));

        return redirect()->route('branch.hostel.index')->with('success', __('Hostel saved.'));
    }

    public function destroy(Hostel $hostel): RedirectResponse
    {
        $this->authorize('hostel.delete');
        if (Allocation::query()->where('hostel_id', $hostel->id)->exists()) {
            return back()->with('warning', __('Students have lived here — mark the hostel inactive instead of deleting it.'));
        }
        $hostel->rooms()->each(fn ($r) => $r->beds()->delete());
        $hostel->rooms()->delete();
        $hostel->delete();

        return redirect()->route('branch.hostel.index')->with('success', __('Hostel deleted.'));
    }

    public function fees(Request $request): View
    {
        $this->authorize('hostel.view');
        $period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->input('period')) ? $request->input('period') : CarbonImmutable::today()->format('Y-m');
        $invoices = FeeInvoice::query()->with('student:id,name,student_code')->where('generated_key', 'like', 'hostel:%:'.$period)->orderBy('id')->get();

        return view('branch.hostel.fees', [
            'period' => $period, 'invoices' => $invoices,
            'totals' => ['billed' => (float) $invoices->sum('total'), 'paid' => (float) $invoices->sum('paid'), 'due' => (float) $invoices->whereNotIn('status', ['cancelled', 'waived'])->sum('balance')],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $this->authorize('hostel.create');
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $stats = $this->hostels->generateFees($data['period'], $request->user());

        return redirect()->route('branch.hostel.fees', ['period' => $data['period']])->with('success', __(':c invoice(s) created, :s already existed.', ['c' => $stats['created'], 's' => $stats['skipped']]));
    }

    protected function wardens(): array
    {
        return Employee::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function validated(Request $request, ?Hostel $hostel = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', TenantRule::unique('hostels', 'name', $hostel?->id)], 'type' => ['required', Rule::in(Hostel::TYPES)],
            'address' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'warden_id' => ['nullable', 'integer', TenantRule::exists('employees', 'id', true)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
