<?php

namespace App\Http\Controllers\Branch\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel\Allocation;
use App\Models\Hostel\Bed;
use App\Models\Hostel\Hostel;
use App\Models\Hostel\MealPlan;
use App\Models\Students\Student;
use App\Services\Hostel\HostelService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Who sleeps where: allocate, change bed, change meal plan, vacate. permission: hostel.* */
class HostelAllocationController extends Controller
{
    public function __construct(protected HostelService $hostels) {}

    public function index(Request $request): View
    {
        $this->authorize('hostel.view');
        $status = $request->input('status') === 'ended' ? 'ended' : 'active';
        $allocations = Allocation::query()->with(['student:id,name,student_code,gender', 'hostel:id,name', 'room:id,room_no,floor', 'bed:id,label', 'mealPlan:id,name'])
            ->when($status === 'active', fn ($q) => $q->where('status', 'active'), fn ($q) => $q->where('status', '!=', 'active'))
            ->when($request->integer('hostel_id'), fn ($q, $h) => $q->where('hostel_id', $h))
            ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->whereHas('student', fn ($s) => $s->search($t)))
            ->latest('id')->paginate(30)->withQueryString();

        $freeBeds = Bed::query()->where('is_active', true)->whereDoesntHave('allocations', fn ($a) => $a->where('status', 'active'))
            ->whereHas('room', fn ($r) => $r->where('is_active', true)->whereHas('hostel', fn ($h) => $h->where('is_active', true)))->with('room.hostel:id,name,type')->get()
            ->sortBy(fn ($b) => $b->room->hostel->name.'|'.$b->room->room_no.'|'.$b->label)->values()
            ->map(fn ($b) => ['id' => $b->id, 'label' => $b->room->hostel->name.' · '.__('Room').' '.$b->room->room_no.' · '.$b->label.' (৳'.number_format((float) $b->room->monthly_fee, 0).')', 'type' => $b->room->hostel->type]);

        return view('branch.hostel.allocations', [
            'allocations' => $allocations, 'status' => $status, 'hostels' => Hostel::query()->orderBy('name')->pluck('name', 'id')->all(), 'freeBeds' => $freeBeds,
            'plans' => MealPlan::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('hostel.create');
        $data = $request->validate([
            'student_id' => ['required', 'integer', TenantRule::exists('students', 'id', true)], 'hostel_bed_id' => ['required', 'integer', TenantRule::exists('hostel_beds')],
            'hostel_meal_plan_id' => ['nullable', 'integer', TenantRule::exists('hostel_meal_plans')], 'starts_on' => ['nullable', 'date'],
        ]);
        $this->hostels->allocate(Student::query()->findOrFail($data['student_id']), Bed::query()->findOrFail($data['hostel_bed_id']),
            isset($data['hostel_meal_plan_id']) ? MealPlan::query()->findOrFail($data['hostel_meal_plan_id']) : null, $data['starts_on'] ?? null, $request->user());

        return back()->with('success', __('Bed allocated.'));
    }

    public function transfer(Request $request, Allocation $allocation): RedirectResponse
    {
        $this->authorize('hostel.update');
        $data = $request->validate(['hostel_bed_id' => ['required', 'integer', TenantRule::exists('hostel_beds')], 'reason' => ['nullable', 'string', 'max:255']]);
        $this->hostels->transfer($allocation, Bed::query()->findOrFail($data['hostel_bed_id']), $request->user(), $data['reason'] ?? null);

        return back()->with('success', __('Student moved to the new bed.'));
    }

    public function mealPlan(Request $request, Allocation $allocation): RedirectResponse
    {
        $this->authorize('hostel.update');
        $data = $request->validate(['hostel_meal_plan_id' => ['nullable', 'integer', TenantRule::exists('hostel_meal_plans')]]);
        $this->hostels->setMealPlan($allocation, filled($data['hostel_meal_plan_id'] ?? null) ? MealPlan::query()->findOrFail($data['hostel_meal_plan_id']) : null);

        return back()->with('success', __('Meal plan updated — it applies from the next invoice.'));
    }

    public function vacate(Request $request, Allocation $allocation): RedirectResponse
    {
        $this->authorize('hostel.update');
        $data = $request->validate(['ends_on' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:255']]);
        $this->hostels->vacate($allocation, $data['ends_on'] ?? null, $data['reason'] ?? null);

        return back()->with('success', __('Bed vacated.'));
    }
}
