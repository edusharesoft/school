<?php

namespace App\Http\Controllers\Branch\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel\Hostel;
use App\Models\Hostel\MaintenanceRequest;
use App\Models\Hostel\Room;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Repair requests for rooms and buildings: open → in progress → done, with cost. permission: hostel.* */
class HostelMaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('hostel.view');
        $requests = MaintenanceRequest::query()->with(['hostel:id,name', 'room:id,room_no'])
            ->when($request->input('status') === 'all', fn ($q) => $q, fn ($q) => $q->whereIn('status', ['open', 'in_progress']))
            ->orderByRaw("case priority when 'high' then 0 when 'normal' then 1 else 2 end")->latest('id')->paginate(30)->withQueryString();

        return view('branch.hostel.maintenance', ['requests' => $requests, 'hostels' => Hostel::query()->orderBy('name')->pluck('name', 'id')->all(), 'rooms' => Room::query()->orderBy('room_no')->get(['id', 'hostel_id', 'room_no'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('hostel.create');
        $data = $request->validate([
            'hostel_id' => ['required', 'integer', TenantRule::exists('hostels', 'id', true)], 'hostel_room_id' => ['nullable', 'integer', TenantRule::exists('hostel_rooms', 'id', true)],
            'title' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'], 'priority' => ['required', Rule::in(MaintenanceRequest::PRIORITIES)],
        ]);
        if (! empty($data['hostel_room_id']) && ! Room::query()->where('id', $data['hostel_room_id'])->where('hostel_id', $data['hostel_id'])->exists()) {
            return back()->withInput()->withErrors(['hostel_room_id' => __('This room is not in the chosen hostel.')]);
        }
        MaintenanceRequest::query()->create($data + ['created_by' => $request->user()->id]);

        return back()->with('success', __('Request logged.'));
    }

    public function update(Request $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        $this->authorize('hostel.update');
        $data = $request->validate(['status' => ['required', Rule::in(MaintenanceRequest::STATUSES)], 'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'resolution' => ['nullable', 'string', 'max:255']]);
        $done = in_array($data['status'], ['done', 'cancelled'], true);
        $maintenance->update(['status' => $data['status'], 'cost' => $data['cost'] ?? $maintenance->cost, 'resolution' => $data['resolution'] ?? $maintenance->resolution, 'resolved_on' => $done ? ($maintenance->resolved_on ?? now()) : null]);

        return back()->with('success', __('Request updated.'));
    }
}
