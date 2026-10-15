@extends('layouts.branch')
@section('title', __('Leave types'))

@section('content')
<x-page-header :title="__('Leave types')" :breadcrumbs="[__('Staff & HR') => route('branch.leaves.index'), __('Leave types') => null]">
    <x-slot:actions>
        @can('leaves.update')
            <a href="{{ route('branch.hr.leave-types.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New leave type') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    @if($types->isEmpty())
        <x-empty-state icon="calendar-x" :title="__('No leave types yet')" :message="__('Examples: Casual leave (10 days), Sick leave (14 days), Earned leave, Maternity leave, Leave without pay (0 = unlimited).')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Days per year') }}</th><th>{{ __('With pay') }}</th><th>{{ __('Pending') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($types as $t)
                    <tr>
                        <td><div class="fw-semibold">{{ $t->name }}</div><div class="small text-body-secondary bn">{{ $t->name_bn }}</div></td>
                        <td><code>{{ $t->code }}</code></td>
                        <td>{{ $t->isUnlimited() ? __('Unlimited') : bn_digits($t->days_per_year) }}</td>
                        <td>{{ $t->is_paid ? __('Yes') : __('No') }}</td>
                        <td>{{ bn_digits($t->applications_count) }}</td>
                        <td><x-status-badge :status="$t->is_active ? 'active' : 'inactive'" /></td>
                        <td><x-row-actions :edit="route('branch.hr.leave-types.edit', $t)" :can-edit="auth()->user()->can('leaves.update')" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
