<?php

namespace App\Http\Controllers\Branch\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\LeaveTypeRequest;
use App\Models\HR\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LeaveTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('leaves.view');

        return view('branch.hr.leave-types.index', [
            'types' => LeaveType::query()->withCount(['applications' => fn ($q) => $q->where('status', 'pending')])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('leaves.update');

        return view('branch.hr.leave-types.form', ['type' => new LeaveType(['days_per_year' => 10, 'is_paid' => true, 'is_active' => true])]);
    }

    public function store(LeaveTypeRequest $request): RedirectResponse
    {
        LeaveType::query()->create($request->validated() + [
            'is_paid' => $request->boolean('is_paid', true), 'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('branch.hr.leave-types.index')->with('success', __('Leave type created.'));
    }

    public function edit(LeaveType $leaveType): View
    {
        $this->authorize('leaves.update');

        return view('branch.hr.leave-types.form', ['type' => $leaveType]);
    }

    public function update(LeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($request->validated() + ['is_paid' => $request->boolean('is_paid'), 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('branch.hr.leave-types.index')->with('success', __('Leave type updated.'));
    }
}
