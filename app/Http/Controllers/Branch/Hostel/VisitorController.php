<?php

namespace App\Http\Controllers\Branch\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel\Allocation;
use App\Models\Hostel\Hostel;
use App\Models\Hostel\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Visitor register: who visited which resident, check-in and check-out. permission: hostel.* */
class VisitorController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('hostel.view');
        $visitors = Visitor::query()->with(['student:id,name,student_code', 'hostel:id,name'])
            ->when($request->input('inside'), fn ($q) => $q->whereNull('out_at'))
            ->when($request->integer('hostel_id'), fn ($q, $h) => $q->where('hostel_id', $h))->latest('in_at')->paginate(30)->withQueryString();
        $residents = Allocation::query()->where('status', 'active')->with('student:id,name,student_code', 'hostel:id,name')->get()->sortBy(fn ($a) => $a->student?->name)->values();

        return view('branch.hostel.visitors', ['visitors' => $visitors, 'residents' => $residents, 'hostels' => Hostel::query()->orderBy('name')->pluck('name', 'id')->all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('hostel.create');
        $data = $request->validate(['allocation_id' => ['required', 'integer'], 'visitor_name' => ['required', 'string', 'max:120'], 'relation' => ['nullable', 'string', 'max:60'], 'phone' => ['nullable', 'string', 'max:30'], 'purpose' => ['nullable', 'string', 'max:255']]);
        $allocation = Allocation::query()->where('status', 'active')->find($data['allocation_id']) ?? throw ValidationException::withMessages(['allocation_id' => __('Choose a student who lives in the hostel.')]);
        Visitor::query()->create(['hostel_id' => $allocation->hostel_id, 'student_id' => $allocation->student_id, 'in_at' => now(), 'created_by' => $request->user()->id] + collect($data)->except('allocation_id')->all());

        return back()->with('success', __('Visitor checked in.'));
    }

    public function checkout(Visitor $visitor): RedirectResponse
    {
        $this->authorize('hostel.update');
        if ($visitor->out_at) {
            return back()->with('warning', __('Already checked out.'));
        }
        $visitor->update(['out_at' => now()]);

        return back()->with('success', __('Visitor checked out.'));
    }
}
