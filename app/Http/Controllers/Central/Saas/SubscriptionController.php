<?php

namespace App\Http\Controllers\Central\Saas;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Saas\Plan;
use App\Models\Saas\Subscription;
use App\Services\Saas\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Who is on which plan, payments and the on/off switches (platform owner only). */
class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $this->authorize('subscriptions.view');
        $phase = $request->query('phase');

        $rows = Institution::query()->orderBy('name')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
            ->get()
            ->map(fn (Institution $i) => ['institution' => $i, 'subscription' => $this->subscriptions->of($i), 'usage' => $this->subscriptions->usage($i)])
            ->when(in_array($phase, ['trial', 'active', 'grace', 'expired', 'suspended'], true), fn ($c) => $c->filter(fn ($r) => $r['subscription']?->phase() === $phase))
            ->when($phase === 'none', fn ($c) => $c->filter(fn ($r) => $r['subscription'] === null))
            ->values();

        return view('central.saas.subscriptions.index', compact('rows', 'phase'));
    }

    public function show(Institution $institution): View
    {
        $this->authorize('subscriptions.view');

        return view('central.saas.subscriptions.show', [
            'institution' => $institution, 'subscription' => $this->subscriptions->of($institution)?->load('payments.recorder'),
            'usage' => $this->subscriptions->usage($institution), 'plans' => Plan::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorize('subscriptions.update');
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')], 'billing_cycle' => ['required', Rule::in(Subscription::CYCLES)],
            'trial_ends_on' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $existing = $this->subscriptions->of($institution);
        $plan = Plan::query()->findOrFail($data['plan_id']);
        if (! $existing) {
            $this->subscriptions->startTrial($institution, $plan);
        }
        $this->subscriptions->save($institution, $plan, array_filter([
            'billing_cycle' => $data['billing_cycle'], 'notes' => $data['notes'] ?? null, 'trial_ends_on' => $data['trial_ends_on'] ?? null,
        ], fn ($v) => $v !== null));

        return back()->with('success', __('Subscription saved.'));
    }

    public function payment(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorize('subscriptions.update');
        $subscription = $this->subscriptions->of($institution);
        abort_unless($subscription, 404);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'], 'method' => ['required', 'string', 'max:30'], 'reference' => ['nullable', 'string', 'max:100'],
            'periods' => ['required', 'integer', 'min:1', 'max:36'], 'paid_on' => ['nullable', 'date'],
        ]);
        $this->subscriptions->recordPayment($subscription, (float) $data['amount'], $data['method'], $data['reference'] ?? null, (int) $data['periods'], $request->user(), $data['paid_on'] ?? null);

        return back()->with('success', __('Payment recorded and the period extended.'));
    }

    public function action(Request $request, Institution $institution, string $action): RedirectResponse
    {
        $this->authorize('subscriptions.update');
        $subscription = $this->subscriptions->of($institution);
        abort_unless($subscription && in_array($action, ['suspend', 'reactivate', 'cancel'], true), 404);
        match ($action) {
            'suspend' => $this->subscriptions->suspend($subscription, $request->input('note')),
            'reactivate' => $this->subscriptions->reactivate($subscription),
            'cancel' => $this->subscriptions->cancel($subscription),
        };

        return back()->with('success', __('Subscription updated.'));
    }
}
