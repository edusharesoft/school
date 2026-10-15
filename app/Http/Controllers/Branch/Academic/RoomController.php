<?php

namespace App\Http\Controllers\Branch\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\RoomRequest;
use App\Models\Academic\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Room::class);

        $items = Room::query()
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('branch.academics.rooms.index', ['items' => $items]);
    }

    public function create(): View
    {
        $this->authorize('create', Room::class);

        return view('branch.academics.rooms.form', ['item' => new Room(['type' => 'classroom', 'capacity' => 40, 'is_active' => true])]);
    }

    public function store(RoomRequest $request): RedirectResponse
    {
        Room::query()->create($this->data($request));

        return redirect()->route('branch.academics.rooms.index')->with('success', __('Room created.'));
    }

    public function edit(Room $room): View
    {
        $this->authorize('update', $room);

        return view('branch.academics.rooms.form', ['item' => $room]);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $room->update($this->data($request));

        return redirect()->route('branch.academics.rooms.index')->with('success', __('Room updated.'));
    }

    public function destroy(Room $room): RedirectResponse
    {
        $this->authorize('delete', $room);

        $room->delete();

        return redirect()->route('branch.academics.rooms.index')->with('success', __('Room deleted.'));
    }

    protected function data(RoomRequest $request): array
    {
        return array_merge($request->validated(), [
            'code' => strtoupper((string) $request->validated('code')),
            'is_active' => $request->boolean('is_active'),
        ]);
    }
}
