<?php

namespace App\Http\Controllers\Branch\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel\Allocation;
use App\Models\Hostel\MealPlan;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Meal plans (e.g. "3 meals", "Breakfast + dinner") with a monthly fee. permission: hostel.* */
class MealPlanController extends Controller
{
    public function index(): View
    {
        $this->authorize('hostel.view');

        return view('branch.hostel.meal-plans', ['plans' => MealPlan::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('hostel.create');
        MealPlan::query()->create($this->validated($request));

        return back()->with('success', __('Meal plan saved.'));
    }

    public function update(Request $request, MealPlan $plan): RedirectResponse
    {
        $this->authorize('hostel.update');
        $plan->update($this->validated($request, $plan));

        return back()->with('success', __('Meal plan saved.'));
    }

    public function destroy(MealPlan $plan): RedirectResponse
    {
        $this->authorize('hostel.delete');
        if (Allocation::query()->where('hostel_meal_plan_id', $plan->id)->exists()) {
            return back()->with('warning', __('Students use this plan — mark it inactive instead.'));
        }
        $plan->delete();

        return back()->with('success', __('Meal plan deleted.'));
    }

    protected function validated(Request $request, ?MealPlan $plan = null): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', TenantRule::unique('hostel_meal_plans', 'name', $plan?->id)], 'monthly_fee' => ['required', 'numeric', 'min:0', 'max:999999'], 'description' => ['nullable', 'string', 'max:255']]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
