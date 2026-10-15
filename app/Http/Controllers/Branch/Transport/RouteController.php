<?php

namespace App\Http\Controllers\Branch\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\Allocation;
use App\Models\Transport\Driver;
use App\Models\Transport\Stop;
use App\Models\Transport\TransportRoute;
use App\Models\Transport\Vehicle;
use App\Services\Transport\TransportService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Routes with their stops, vehicle, driver and helper; printable route sheet with guardian phones. permission: transport.* */
class RouteController extends Controller
{
    public function __construct(protected TransportService $transport) {}

    public function index(): View
    {
        $this->authorize('transport.view');
        $routes = TransportRoute::query()->with(['vehicle:id,registration_no,capacity', 'driver:id,name,phone'])->withCount(['stops', 'allocations as seats' => fn ($q) => $q->where('status', 'active')])->orderBy('code')->get();

        return view('branch.transport.routes.index', ['routes' => $routes]);
    }

    public function create(): View
    {
        $this->authorize('transport.create');

        return view('branch.transport.routes.form', $this->formData(new TransportRoute(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('transport.create');
        $route = TransportRoute::query()->create($this->validated($request));

        return redirect()->route('branch.transport.routes.show', $route)->with('success', __('Route saved.'));
    }

    public function show(TransportRoute $route): View
    {
        $this->authorize('transport.view');
        $route->load(['vehicle', 'driver', 'helper']);
        $riders = Allocation::query()->where('transport_route_id', $route->id)->where('status', 'active')->with('student:id,name,student_code', 'stop:id,name')->get();

        return view('branch.transport.routes.show', ['route' => $route, 'stops' => $route->stops()->orderBy('sequence')->orderBy('id')->get(), 'riders' => $riders, 'seatsLeft' => $this->transport->seatsLeft($route)]);
    }

    /** Printable sheet: stops in order, riders per stop with guardian phone. */
    public function sheet(TransportRoute $route): View
    {
        $this->authorize('transport.view');
        $route->load(['vehicle', 'driver', 'helper']);
        $stops = $route->stops()->orderBy('sequence')->orderBy('id')->get();
        $riders = Allocation::query()->where('transport_route_id', $route->id)->where('status', 'active')
            ->with(['student' => fn ($q) => $q->with('guardians', 'currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name')])->get()->groupBy('transport_stop_id');

        return view('branch.transport.routes.sheet', ['route' => $route, 'stops' => $stops, 'riders' => $riders]);
    }

    public function edit(TransportRoute $route): View
    {
        $this->authorize('transport.update');

        return view('branch.transport.routes.form', $this->formData($route));
    }

    public function update(Request $request, TransportRoute $route): RedirectResponse
    {
        $this->authorize('transport.update');
        $data = $this->validated($request, $route);
        if (! empty($data['vehicle_id']) && Allocation::query()->where('transport_route_id', $route->id)->where('status', 'active')->count() > Vehicle::query()->whereKey($data['vehicle_id'])->value('capacity')) {
            return back()->withInput()->withErrors(['vehicle_id' => __('This vehicle has fewer seats than the students already on the route.')]);
        }
        $route->update($data);

        return redirect()->route('branch.transport.routes.show', $route)->with('success', __('Route saved.'));
    }

    public function destroy(TransportRoute $route): RedirectResponse
    {
        $this->authorize('transport.delete');
        if (Allocation::query()->where('transport_route_id', $route->id)->exists()) {
            return back()->with('warning', __('Students have used this route — mark it inactive instead of deleting it.'));
        }
        $route->stops()->delete();
        $route->delete();

        return redirect()->route('branch.transport.routes.index')->with('success', __('Route deleted.'));
    }

    public function storeStop(Request $request, TransportRoute $route): RedirectResponse
    {
        $this->authorize('transport.update');
        $route->stops()->create($this->stopData($request) + ['sequence' => ($route->stops()->max('sequence') ?? 0) + 1]);

        return back()->with('success', __('Stop added.'));
    }

    public function updateStop(Request $request, Stop $stop): RedirectResponse
    {
        $this->authorize('transport.update');
        $stop->update($this->stopData($request) + ['sequence' => $request->integer('sequence', $stop->sequence)]);

        return back()->with('success', __('Stop saved.'));
    }

    public function destroyStop(Stop $stop): RedirectResponse
    {
        $this->authorize('transport.delete');
        if (Allocation::query()->where('transport_stop_id', $stop->id)->exists()) {
            return back()->with('warning', __('Students have used this stop, so it cannot be deleted.'));
        }
        $stop->delete();

        return back()->with('success', __('Stop deleted.'));
    }

    protected function stopData(Request $request): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'pickup_time' => ['nullable', 'date_format:H:i'], 'drop_time' => ['nullable', 'date_format:H:i'], 'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:999999']]);
        $data['monthly_fee'] = $data['monthly_fee'] ?? null;

        return $data;
    }

    protected function formData(TransportRoute $route): array
    {
        return [
            'route' => $route, 'vehicles' => Vehicle::query()->where('status', '!=', 'retired')->orderBy('registration_no')->get()->mapWithKeys(fn ($v) => [$v->id => $v->label().' ('.$v->capacity.')'])->all(),
            'drivers' => Driver::query()->where('is_active', true)->where('role', 'driver')->orderBy('name')->pluck('name', 'id')->all(),
            'helpers' => Driver::query()->where('is_active', true)->where('role', 'helper')->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    protected function validated(Request $request, ?TransportRoute $route = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', TenantRule::unique('transport_routes', 'code', $route?->id)], 'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'],
            'vehicle_id' => ['nullable', 'integer', TenantRule::exists('transport_vehicles', 'id', true)], 'driver_id' => ['nullable', 'integer', TenantRule::exists('transport_drivers', 'id', true)],
            'helper_id' => ['nullable', 'integer', TenantRule::exists('transport_drivers', 'id', true)], 'monthly_fee' => ['required', 'numeric', 'min:0', 'max:999999'],
            'morning_start' => ['nullable', 'date_format:H:i'], 'afternoon_start' => ['nullable', 'date_format:H:i'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
