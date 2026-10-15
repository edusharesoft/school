<?php

namespace App\Http\Controllers\Branch\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel\Allocation;
use App\Models\Hostel\Bed;
use App\Models\Hostel\Hostel;
use App\Models\Hostel\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Rooms and their beds. Beds are numbered B1, B2 … when a room is created and can be added later. permission: hostel.* */
class RoomController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('hostel.view');
        $rooms = Room::query()->with('hostel:id,name')->withCount(['beds', 'beds as free_beds' => fn ($q) => $q->where('is_active', true)->whereDoesntHave('allocations', fn ($a) => $a->where('status', 'active'))])
            ->when($request->integer('hostel_id'), fn ($q, $h) => $q->where('hostel_id', $h))->orderBy('hostel_id')->orderBy('floor')->orderBy('room_no')->get();

        return view('branch.hostel.rooms.index', ['rooms' => $rooms, 'hostels' => Hostel::query()->orderBy('name')->pluck('name', 'id')->all()]);
    }

    public function create(): View
    {
        $this->authorize('hostel.create');

        return view('branch.hostel.rooms.form', ['room' => new Room(['type' => 'shared', 'is_active' => true]), 'hostels' => Hostel::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('hostel.create');
        $data = $this->validated($request);
        $beds = $request->validate(['beds' => ['required', 'integer', 'min:1', 'max:60']])['beds'];
        $room = Room::query()->create($data);
        for ($i = 1; $i <= $beds; $i++) {
            $room->beds()->create(['label' => 'B'.$i]);
        }

        return redirect()->route('branch.hostel.rooms.show', $room)->with('success', __('Room saved.'));
    }

    public function show(Room $room): View
    {
        $this->authorize('hostel.view');
        $room->load('hostel');
        $beds = $room->beds()->orderBy('id')->get();
        $residents = Allocation::query()->whereIn('hostel_bed_id', $beds->pluck('id'))->where('status', 'active')->with('student:id,name,student_code')->get()->keyBy('hostel_bed_id');

        return view('branch.hostel.rooms.show', ['room' => $room, 'beds' => $beds, 'residents' => $residents]);
    }

    public function edit(Room $room): View
    {
        $this->authorize('hostel.update');

        return view('branch.hostel.rooms.form', ['room' => $room, 'hostels' => Hostel::query()->orderBy('name')->pluck('name', 'id')->all()]);
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $this->authorize('hostel.update');
        $data = $this->validated($request, $room);
        if ((int) $data['hostel_id'] !== (int) $room->hostel_id && Allocation::query()->where('hostel_room_id', $room->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['hostel_id' => __('Students live in this room — move them before changing the hostel.')]);
        }
        $room->update($data);   // the fee of students already living here stays as allocated

        return redirect()->route('branch.hostel.rooms.show', $room)->with('success', __('Room saved.'));
    }

    public function destroy(Room $room): RedirectResponse
    {
        $this->authorize('hostel.delete');
        if (Allocation::query()->where('hostel_room_id', $room->id)->exists()) {
            return back()->with('warning', __('Students have lived in this room — mark it inactive instead of deleting it.'));
        }
        $room->beds()->delete();
        $room->delete();

        return redirect()->route('branch.hostel.rooms.index')->with('success', __('Room deleted.'));
    }

    public function addBeds(Request $request, Room $room): RedirectResponse
    {
        $this->authorize('hostel.update');
        $n = $request->validate(['count' => ['required', 'integer', 'min:1', 'max:20']])['count'];
        $next = (int) $room->beds()->count();
        for ($i = 1; $i <= $n; $i++) {
            $room->beds()->create(['label' => 'B'.($next + $i)]);
        }

        return back()->with('success', __(':n bed(s) added.', ['n' => $n]));
    }

    public function toggleBed(Bed $bed): RedirectResponse
    {
        $this->authorize('hostel.update');
        if ($bed->is_active && Allocation::query()->where('hostel_bed_id', $bed->id)->where('status', 'active')->exists()) {
            return back()->with('warning', __('A student is using this bed.'));
        }
        $bed->update(['is_active' => ! $bed->is_active]);

        return back()->with('success', __('Bed updated.'));
    }

    protected function validated(Request $request, ?Room $room = null): array
    {
        $data = $request->validate([
            'hostel_id' => ['required', 'integer', \App\Support\TenantRule::exists('hostels', 'id', true)], 'building' => ['nullable', 'string', 'max:60'], 'floor' => ['nullable', 'string', 'max:30'],
            'room_no' => ['required', 'string', 'max:30', Rule::unique('hostel_rooms', 'room_no')->where('hostel_id', $request->input('hostel_id'))->ignore($room?->id)],
            'type' => ['required', Rule::in(Room::TYPES)], 'monthly_fee' => ['required', 'numeric', 'min:0', 'max:999999'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
