<?php

namespace App\Http\Controllers\Branch\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\FuelLog;
use App\Models\Transport\Maintenance;
use App\Models\Transport\Vehicle;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Fleet: vehicles, their papers, maintenance and fuel logs. permission: transport.* */
class VehicleController extends Controller
{
    public function index(): View
    {
        $this->authorize('transport.view');

        return view('branch.transport.vehicles.index', ['vehicles' => Vehicle::query()->withCount('routes')->orderBy('registration_no')->get()]);
    }

    public function create(): View
    {
        $this->authorize('transport.create');

        return view('branch.transport.vehicles.form', ['vehicle' => new Vehicle(['type' => 'bus', 'capacity' => 40, 'fuel_type' => 'diesel', 'status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('transport.create');
        $vehicle = Vehicle::query()->create($this->validated($request));

        return redirect()->route('branch.transport.vehicles.show', $vehicle)->with('success', __('Vehicle saved.'));
    }

    public function show(Vehicle $vehicle): View
    {
        $this->authorize('transport.view');
        $fuel = $vehicle->fuelLogs()->with('driver:id,name')->latest('date')->latest('id')->limit(30)->get();

        return view('branch.transport.vehicles.show', [
            'vehicle' => $vehicle->load('routes'), 'maintenance' => $vehicle->maintenance()->latest('date')->latest('id')->limit(30)->get(), 'fuel' => $fuel,
            'drivers' => \App\Models\Transport\Driver::query()->where('is_active', true)->where('role', 'driver')->orderBy('name')->pluck('name', 'id'),
            'totals' => ['maintenance' => (float) $vehicle->maintenance()->sum('cost'), 'fuel' => (float) $vehicle->fuelLogs()->sum('total_cost'), 'litres' => (float) $vehicle->fuelLogs()->sum('litres')],
        ]);
    }

    public function edit(Vehicle $vehicle): View
    {
        $this->authorize('transport.update');

        return view('branch.transport.vehicles.form', ['vehicle' => $vehicle]);
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('transport.update');
        $vehicle->update($this->validated($request, $vehicle));

        return redirect()->route('branch.transport.vehicles.show', $vehicle)->with('success', __('Vehicle saved.'));
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('transport.delete');
        if ($vehicle->routes()->exists()) {
            return back()->with('warning', __('This vehicle is used by a route — assign another vehicle first, or mark it retired.'));
        }
        $vehicle->delete();

        return redirect()->route('branch.transport.vehicles.index')->with('success', __('Vehicle deleted.'));
    }

    public function storeMaintenance(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('transport.update');
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'], 'kind' => ['required', Rule::in(Maintenance::KINDS)], 'description' => ['required', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'min:0', 'max:99999999'], 'vendor' => ['nullable', 'string', 'max:120'], 'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'next_due_on' => ['nullable', 'date', 'after_or_equal:date'],
        ]);
        Maintenance::query()->create($data + ['transport_vehicle_id' => $vehicle->id, 'created_by' => $request->user()->id]);

        return back()->with('success', __('Maintenance recorded.'));
    }

    public function destroyMaintenance(Maintenance $maintenance): RedirectResponse
    {
        $this->authorize('transport.delete');
        $maintenance->delete();

        return back()->with('success', __('Record deleted.'));
    }

    public function storeFuel(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('transport.update');
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'], 'litres' => ['required', 'numeric', 'gt:0', 'max:9999'], 'total_cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'], 'transport_driver_id' => ['nullable', 'integer', TenantRule::exists('transport_drivers', 'id', true)],
        ]);
        FuelLog::query()->create($data + ['transport_vehicle_id' => $vehicle->id, 'created_by' => $request->user()->id]);

        return back()->with('success', __('Fuel recorded.'));
    }

    public function destroyFuel(FuelLog $fuel): RedirectResponse
    {
        $this->authorize('transport.delete');
        $fuel->delete();

        return back()->with('success', __('Record deleted.'));
    }

    protected function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        return $request->validate([
            'registration_no' => ['required', 'string', 'max:40', TenantRule::unique('transport_vehicles', 'registration_no', $vehicle?->id)], 'name' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(Vehicle::TYPES)], 'capacity' => ['required', 'integer', 'min:1', 'max:200'], 'fuel_type' => ['required', Rule::in(Vehicle::FUELS)],
            'insurance_expiry' => ['nullable', 'date'], 'fitness_expiry' => ['nullable', 'date'], 'tax_token_expiry' => ['nullable', 'date'], 'route_permit_expiry' => ['nullable', 'date'],
            'tracking_url' => ['nullable', 'url:https', 'max:255'], 'status' => ['required', Rule::in(Vehicle::STATUSES)], 'note' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
