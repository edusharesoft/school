@extends('layouts.branch')
@section('title', __('Allocations'))

@section('content')
<x-page-header :title="__('Allocations')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Allocations') => null]" />
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Asset name, tag or serial') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-3"><select name="employee" class="form-select form-select-sm" aria-label="{{ __('Employee') }}"><option value="">{{ __('All employees') }}</option>@foreach($employees as $id => $name)<option value="{{ $id }}" @selected((int) request('employee') === $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="department" class="form-select form-select-sm" aria-label="{{ __('Department') }}"><option value="">{{ __('All departments') }}</option>@foreach($departments as $id => $name)<option value="{{ $id }}" @selected((int) request('department') === $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="col-auto form-check ms-2"><input type="checkbox" class="form-check-input" id="ov" name="overdue" value="1" @checked(request()->boolean('overdue'))><label class="form-check-label small" for="ov">{{ __('Overdue only') }}</label></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($allocations->isEmpty())<x-empty-state icon="hand-index" :title="__('Nothing is handed out')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Asset') }}</th><th>{{ __('Held by') }}</th><th>{{ __('Since') }}</th><th>{{ __('Expected back') }}</th><th></th></tr></thead>
        <tbody>@foreach($allocations as $a)<tr>
            <td><a href="{{ route('branch.assets.register.show', $a->asset_id) }}" class="fw-semibold text-decoration-none">{{ $a->asset?->displayName() }}</a><div class="text-body-secondary">{{ $a->asset?->code }} · {{ $a->asset?->category?->name }}</div></td>
            <td>{{ $a->holderLabel() }}</td><td class="text-nowrap">{{ $a->allocated_on->format('d M Y') }}</td>
            <td class="text-nowrap @if($a->isOverdue()) text-danger fw-semibold @endif">{{ $a->expected_return_on?->format('d M Y') ?? '—' }}@if($a->isOverdue()) <i class="bi bi-exclamation-circle"></i>@endif</td>
            <td class="text-end">@can('assets.update')
                <form method="POST" action="{{ route('branch.assets.allocations.return', $a) }}" class="d-inline-flex gap-1">@csrf
                    <input type="hidden" name="returned_on" value="{{ now()->toDateString() }}">
                    <select name="return_condition" class="form-select form-select-sm" style="width:110px" aria-label="{{ __('Condition') }}"><option value="">{{ __('Condition') }}</option>@foreach(App\Models\Assets\Asset::CONDITIONS as $c)<option value="{{ $c }}">{{ __(ucfirst($c)) }}</option>@endforeach</select>
                    <button class="btn btn-sm btn-outline-secondary">{{ __('Take back') }}</button></form>@endcan</td></tr>@endforeach</tbody></table></div>
        <div class="card-body">{{ $allocations->links() }}</div>@endif
</div>
@endsection
