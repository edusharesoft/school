<?php

namespace App\Http\Controllers\Central\Saas;

use App\Http\Controllers\Controller;
use App\Models\Saas\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Subscription plans (platform owner only: subscriptions.*). */
class PlanController extends Controller
{
    public function index(): View
    {
        $this->authorize('subscriptions.view');

        return view('central.saas.plans.index', ['plans' => Plan::query()->withCount('subscriptions')->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function create(): View
    {
        $this->authorize('subscriptions.create');

        return view('central.saas.plans.form', ['plan' => new Plan(['trial_days' => 14, 'grace_days' => 7, 'is_active' => true, 'sort_order' => 0, 'price_monthly' => 0, 'price_yearly' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('subscriptions.create');
        Plan::query()->create($this->data($request));

        return redirect()->route('central.plans.index')->with('success', __('Plan saved.'));
    }

    public function edit(Plan $plan): View
    {
        $this->authorize('subscriptions.update');

        return view('central.saas.plans.form', compact('plan'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('subscriptions.update');
        $plan->update($this->data($request, $plan));

        return redirect()->route('central.plans.index')->with('success', __('Plan saved.'));
    }

    /** A plan in use is switched off instead of deleted. */
    public function destroy(Plan $plan): RedirectResponse
    {
        $this->authorize('subscriptions.delete');
        if ($plan->subscriptions()->exists()) {
            $plan->update(['is_active' => false]);

            return back()->with('warning', __('The plan is in use, so it was switched off instead of deleted.'));
        }
        $plan->delete();

        return back()->with('success', __('Plan deleted.'));
    }

    protected function data(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('plans', 'code')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:191'], 'name_bn' => ['nullable', 'string', 'max:191'], 'description' => ['nullable', 'string', 'max:1000'],
            'price_monthly' => ['required', 'numeric', 'min:0', 'max:99999999'], 'price_yearly' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:100000'], 'max_students' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'], 'grace_days' => ['required', 'integer', 'min:0', 'max:90'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] ??= 0;

        return $data;
    }
}
