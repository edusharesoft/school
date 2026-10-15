@extends('layouts.central')
@section('title', __('Plans'))

@section('content')
<x-page-header :title="__('Plans')" :breadcrumbs="[__('Subscriptions') => route('central.subscriptions.index'), __('Plans') => null]">
    <x-slot:actions>
        @can('subscriptions.create')<a href="{{ route('central.plans.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New plan') }}</a>@endcan
    </x-slot:actions>
</x-page-header>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>{{ __('Plan') }}</th><th class="text-end">{{ __('Monthly') }}</th><th class="text-end">{{ __('Yearly') }}</th><th>{{ __('Branches') }}</th><th>{{ __('Students') }}</th><th>{{ __('Trial') }}</th><th>{{ __('In use') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($plans as $plan)
        <tr>
            <td><div class="fw-semibold">{{ $plan->displayName() }}</div><code>{{ $plan->code }}</code></td>
            <td class="text-end">{{ number_format($plan->price_monthly, 2) }}</td><td class="text-end">{{ number_format($plan->price_yearly, 2) }}</td>
            <td>{{ $plan->limitLabel($plan->max_branches) }}</td><td>{{ $plan->limitLabel($plan->max_students) }}</td>
            <td>{{ $plan->trial_days ? $plan->trial_days.' '.__('days') : '—' }}</td><td>{{ $plan->subscriptions_count }}</td>
            <td><span class="badge text-bg-{{ $plan->is_active ? 'success' : 'secondary' }}">{{ $plan->is_active ? __('Active') : __('Inactive') }}</span></td>
            <td class="text-end text-nowrap">
                @can('subscriptions.update')<a href="{{ route('central.plans.edit', $plan) }}" class="btn btn-sm btn-outline-secondary">{{ __('Edit') }}</a>@endcan
                @can('subscriptions.delete')<form method="POST" action="{{ route('central.plans.destroy', $plan) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this plan?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button></form>@endcan
            </td>
        </tr>
    @empty
        <tr><td colspan="9" class="text-center text-body-secondary py-4">{{ __('No plans yet.') }}</td></tr>
    @endforelse
    </tbody></table></div></div>
@endsection
