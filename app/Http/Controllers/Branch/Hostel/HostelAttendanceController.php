<?php

namespace App\Http\Controllers\Branch\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel\Allocation;
use App\Models\Hostel\Attendance;
use App\Models\Hostel\Hostel;
use App\Services\Hostel\HostelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Daily roll call of the residents of a hostel. permission: hostel.* */
class HostelAttendanceController extends Controller
{
    public function __construct(protected HostelService $hostels) {}

    public function index(Request $request): View
    {
        $this->authorize('hostel.view');
        $date = $request->filled('date') ? $request->date('date')->toDateString() : now()->toDateString();
        $hostels = Hostel::query()->where('is_active', true)->orderBy('name')->get();
        $hostel = $hostels->firstWhere('id', $request->integer('hostel_id')) ?? $hostels->first();
        $residents = $hostel ? Allocation::query()->where('hostel_id', $hostel->id)->where('status', 'active')->with('student:id,name,student_code', 'room:id,room_no', 'bed:id,label')->get()->sortBy(fn ($a) => $a->room?->room_no.$a->bed?->label)->values() : collect();
        $marks = $hostel ? Attendance::query()->where('hostel_id', $hostel->id)->whereDate('date', $date)->get()->keyBy('student_id') : collect();

        return view('branch.hostel.attendance', ['hostels' => $hostels, 'hostel' => $hostel, 'date' => $date, 'residents' => $residents, 'marks' => $marks]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('hostel.update');
        $data = $request->validate([
            'hostel_id' => ['required', 'integer', \App\Support\TenantRule::exists('hostels', 'id', true)], 'date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', 'array'], 'status.*' => [Rule::in(Attendance::STATUSES)], 'note' => ['nullable', 'array'], 'note.*' => ['nullable', 'string', 'max:255'],
        ]);
        $n = $this->hostels->markAttendance(Hostel::query()->findOrFail($data['hostel_id']), $data['date'], $data['status'], $data['note'] ?? [], $request->user());

        return redirect()->route('branch.hostel.attendance', ['hostel_id' => $data['hostel_id'], 'date' => $data['date']])->with('success', __('Roll call saved for :n students.', ['n' => $n]));
    }
}
