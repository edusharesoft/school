<?php

namespace App\Http\Controllers\Branch\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\Driver;
use App\Models\Transport\DriverAttendance;
use App\Models\Transport\TransportRoute;
use App\Services\Transport\TransportService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Drivers and helpers (with licence and emergency contacts) and their daily attendance. permission: transport.* */
class DriverController extends Controller
{
    public function index(): View
    {
        $this->authorize('transport.view');

        return view('branch.transport.drivers.index', ['drivers' => Driver::query()->orderByDesc('is_active')->orderBy('role')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        $this->authorize('transport.create');

        return view('branch.transport.drivers.form', ['driver' => new Driver(['role' => 'driver', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('transport.create');
        Driver::query()->create($this->validated($request));

        return redirect()->route('branch.transport.drivers.index')->with('success', __('Saved.'));
    }

    public function edit(Driver $driver): View
    {
        $this->authorize('transport.update');

        return view('branch.transport.drivers.form', ['driver' => $driver]);
    }

    public function update(Request $request, Driver $driver): RedirectResponse
    {
        $this->authorize('transport.update');
        $driver->update($this->validated($request));

        return redirect()->route('branch.transport.drivers.index')->with('success', __('Saved.'));
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        $this->authorize('transport.delete');
        if (TransportRoute::query()->where(fn ($q) => $q->where('driver_id', $driver->id)->orWhere('helper_id', $driver->id))->exists()) {
            return back()->with('warning', __('This person is assigned to a route — assign someone else first, or mark them inactive.'));
        }
        $driver->delete();

        return redirect()->route('branch.transport.drivers.index')->with('success', __('Deleted.'));
    }

    public function attendance(Request $request): View
    {
        $this->authorize('transport.view');
        $date = $request->filled('date') ? $request->date('date')->toDateString() : now()->toDateString();

        return view('branch.transport.drivers.attendance', [
            'date' => $date, 'drivers' => Driver::query()->where('is_active', true)->orderBy('role')->orderBy('name')->get(),
            'marks' => DriverAttendance::query()->whereDate('date', $date)->get()->keyBy('transport_driver_id'),
        ]);
    }

    public function saveAttendance(Request $request, TransportService $transport): RedirectResponse
    {
        $this->authorize('transport.update');
        $data = $request->validate(['date' => ['required', 'date', 'before_or_equal:today'], 'status' => ['required', 'array'], 'status.*' => [Rule::in(['present', 'absent', 'leave'])], 'note' => ['nullable', 'array'], 'note.*' => ['nullable', 'string', 'max:255']]);
        $n = $transport->markDrivers($data['date'], $data['status'], $data['note'] ?? []);

        return redirect()->route('branch.transport.attendance', ['date' => $data['date']])->with('success', __('Attendance saved for :n.', ['n' => $n]));
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(Driver::ROLES)], 'name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', 'max:30'],
            'license_no' => ['nullable', 'required_if:role,driver', 'string', 'max:50'], 'license_expiry' => ['nullable', 'date'], 'nid_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'], 'emergency_contact_name' => ['nullable', 'string', 'max:120'], 'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'employee_id' => ['nullable', 'integer', TenantRule::exists('employees', 'id', true)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
