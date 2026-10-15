<?php

namespace App\Http\Controllers\Branch\Transport;

use App\Http\Controllers\Controller;
use App\Models\Finance\FeeInvoice;
use App\Models\Transport\Allocation;
use App\Models\Transport\Driver;
use App\Models\Transport\TransportRoute;
use App\Models\Transport\Vehicle;
use App\Services\Transport\TransportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Transport home (fleet, seats, expiring papers) and the monthly fee run + payment report. permission: transport.* */
class TransportController extends Controller
{
    public function __construct(protected TransportService $transport) {}

    public function index(): View
    {
        $this->authorize('transport.view');
        $vehicles = Vehicle::query()->where('status', '!=', 'retired')->get();
        $expiring = $vehicles->map(fn (Vehicle $v) => ['vehicle' => $v, 'docs' => $v->expiring(30)])->filter(fn ($r) => $r['docs'] !== [])->values();
        $licences = Driver::query()->where('is_active', true)->where('role', 'driver')->get()->filter(fn (Driver $d) => $d->licenseExpiring(30))->values();
        $routes = TransportRoute::query()->with('vehicle:id,registration_no,capacity')->withCount(['allocations as seats' => fn ($q) => $q->where('status', 'active')])->where('is_active', true)->orderBy('code')->get();

        return view('branch.transport.index', [
            'stats' => [
                'vehicles' => $vehicles->count(), 'routes' => $routes->count(), 'riders' => Allocation::query()->where('status', 'active')->count(),
                'drivers' => Driver::query()->where('is_active', true)->count(),
            ],
            'expiring' => $expiring, 'licences' => $licences, 'routes' => $routes,
        ]);
    }

    /** Fee run + who has paid. */
    public function fees(Request $request): View
    {
        $this->authorize('transport.view');
        $period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->input('period')) ? $request->input('period') : CarbonImmutable::today()->format('Y-m');
        $invoices = FeeInvoice::query()->with('student:id,name,student_code')->where('generated_key', 'like', 'transport:%:'.$period)->orderBy('id')->get();

        return view('branch.transport.fees', [
            'period' => $period, 'invoices' => $invoices,
            'totals' => ['billed' => (float) $invoices->sum('total'), 'paid' => (float) $invoices->sum('paid'), 'due' => (float) $invoices->whereNotIn('status', ['cancelled', 'waived'])->sum('balance')],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $this->authorize('transport.create');
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $stats = $this->transport->generateFees($data['period'], $request->user());

        return redirect()->route('branch.transport.fees', ['period' => $data['period']])
            ->with('success', __(':c invoice(s) created, :s already existed.', ['c' => $stats['created'], 's' => $stats['skipped']]));
    }
}
