<?php

namespace App\Http\Controllers\Branch\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\DesignationRequest;
use App\Models\HR\Designation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DesignationController extends Controller
{
    public function index(): View
    {
        $this->authorize('employees.view');

        return view('branch.hr.designations.index', [
            'designations' => Designation::query()->withCount(['employees' => fn ($q) => $q->where('status', 'active')])->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('employees.update');

        return view('branch.hr.designations.form', ['designation' => new Designation(['category' => 'teaching', 'rank' => 100, 'is_active' => true])]);
    }

    public function store(DesignationRequest $request): RedirectResponse
    {
        Designation::query()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('branch.hr.designations.index')->with('success', __('Designation created.'));
    }

    public function edit(Designation $designation): View
    {
        $this->authorize('employees.update');

        return view('branch.hr.designations.form', compact('designation'));
    }

    public function update(DesignationRequest $request, Designation $designation): RedirectResponse
    {
        $designation->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('branch.hr.designations.index')->with('success', __('Designation updated.'));
    }

    /** Archive (soft delete) — only when no active employee holds it. */
    public function destroy(Designation $designation): RedirectResponse
    {
        $this->authorize('employees.update');

        if ($designation->employees()->where('status', 'active')->exists()) {
            return back()->with('error', __('This designation is held by active employees. Deactivate it instead.'));
        }

        $designation->delete();

        return redirect()->route('branch.hr.designations.index')->with('success', __('Designation archived.'));
    }
}
