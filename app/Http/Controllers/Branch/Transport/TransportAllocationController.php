<?php

namespace App\Http\Controllers\Branch\Transport;

use App\Http\Controllers\Controller;
use App\Models\Students\Student;
use App\Models\Transport\Allocation;
use App\Models\Transport\Stop;
use App\Models\Transport\TransportRoute;
use App\Services\Transport\TransportService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Student seats: who rides which route and stop, at what monthly fee. permission: transport.* */
class TransportAllocationController extends Controller
{
    public function __construct(protected TransportService $transport) {}

    public function index(Request $request): View
    {
        $this->authorize('transport.view');
        $status = $request->input('status') === 'ended' ? 'ended' : 'active';
        $allocations = Allocation::query()->with(['student:id,name,student_code', 'route:id,name,code', 'stop:id,name'])->where('status', $status)
            ->when($request->integer('route_id'), fn ($q, $r) => $q->where('transport_route_id', $r))
            ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->whereHas('student', fn ($s) => $s->search($t)))
            ->latest('id')->paginate(30)->withQueryString();
        $routes = TransportRoute::query()->where('is_active', true)->with(['stops' => fn ($q) => $q->orderBy('sequence'), 'vehicle:id,capacity'])
            ->withCount(['allocations as seats' => fn ($q) => $q->where('status', 'active')])->orderBy('code')->get();

        return view('branch.transport.allocations', ['allocations' => $allocations, 'routes' => $routes, 'status' => $status]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('transport.create');
        $data = $request->validate([
            'student_id' => ['required', 'integer', TenantRule::exists('students', 'id', true)], 'transport_stop_id' => ['required', 'integer', TenantRule::exists('transport_stops')],
            'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:999999'], 'starts_on' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->transport->allocate(Student::query()->findOrFail($data['student_id']), Stop::query()->findOrFail($data['transport_stop_id']), isset($data['monthly_fee']) ? (float) $data['monthly_fee'] : null, $data['starts_on'] ?? null, $request->user(), $data['note'] ?? null);

        return back()->with('success', __('Seat allocated.'));
    }

    public function update(Request $request, Allocation $allocation): RedirectResponse
    {
        $this->authorize('transport.update');
        $data = $request->validate(['monthly_fee' => ['required', 'numeric', 'min:0', 'max:999999']]);
        abort_unless($allocation->status === 'active', 404);
        $allocation->update(['monthly_fee' => $data['monthly_fee']]);

        return back()->with('success', __('Fee updated — it applies from the next invoice.'));
    }

    public function transfer(Request $request, Allocation $allocation): RedirectResponse
    {
        $this->authorize('transport.update');
        $data = $request->validate(['transport_stop_id' => ['required', 'integer', TenantRule::exists('transport_stops')], 'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:999999']]);
        $this->transport->transfer($allocation, Stop::query()->findOrFail($data['transport_stop_id']), isset($data['monthly_fee']) ? (float) $data['monthly_fee'] : null, $request->user());

        return back()->with('success', __('Student moved.'));
    }

    public function end(Request $request, Allocation $allocation): RedirectResponse
    {
        $this->authorize('transport.update');
        $data = $request->validate(['ends_on' => ['nullable', 'date']]);
        $this->transport->end($allocation, $data['ends_on'] ?? null);

        return back()->with('success', __('Seat ended.'));
    }
}
