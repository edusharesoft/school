@extends('layouts.branch')
@section('title', $run->run_no)

@section('content')
<x-page-header :title="$run->run_no" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Depreciation') => route('branch.assets.depreciation.index'), $run->run_no => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
        <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Asset') }}</th><th class="text-end">{{ __('Charge (৳)') }}</th><th class="text-end">{{ __('Accumulated (৳)') }}</th><th class="text-end">{{ __('Book value (৳)') }}</th></tr></thead>
        <tbody>@foreach($rows as $r)<tr class="@if($r->is_reversed) text-decoration-line-through text-body-secondary @endif"><td>{{ $r->period }}</td><td><a href="{{ route('branch.assets.register.show', $r->asset_id) }}" class="text-decoration-none">{{ $r->asset?->displayName() }}</a> <span class="text-body-secondary">{{ $r->asset?->code }}</span></td>
            <td class="text-end">{{ number_format($r->amount, 2) }}</td><td class="text-end">{{ number_format($r->accumulated_after, 2) }}</td><td class="text-end">{{ number_format($r->book_value_after, 2) }}</td></tr>@endforeach</tbody>
        <tfoot><tr class="fw-semibold"><td colspan="2" class="text-end">{{ __('Total') }}</td><td class="text-end">{{ number_format($rows->sum('amount'), 2) }}</td><td colspan="2"></td></tr></tfoot></table></div></div></div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body small"><div class="mb-2"><x-inv-status :status="$run->status" /></div>
            <dl class="row mb-0"><dt class="col-5">{{ __('Up to') }}</dt><dd class="col-7">{{ $run->period }}</dd><dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $run->date->format('d M Y') }}</dd>
                <dt class="col-5">{{ __('Assets') }}</dt><dd class="col-7">{{ $run->asset_count }}</dd><dt class="col-5">{{ __('Posted by') }}</dt><dd class="col-7">{{ $run->runner?->name }}</dd>
                @if($run->note)<dt class="col-5">{{ __('Note') }}</dt><dd class="col-7">{{ $run->note }}</dd>@endif</dl></div>
            @if($entries->isNotEmpty())<ul class="list-group list-group-flush small">@foreach($entries as $e)<li class="list-group-item d-flex justify-content-between"><span>{{ $e->entry_no }}<div class="text-body-secondary">{{ $e->date->format('d M Y') }}</div></span><span>{{ number_format($e->amount, 2) }}</span></li>@endforeach</ul>@endif</div>
        @if($run->status === 'posted')@can('assets.approve')
        <form method="POST" action="{{ route('branch.assets.depreciation.reverse', $run) }}" class="card" data-confirm="{{ __('Reverse this depreciation run?') }}">@csrf
            <div class="card-body"><label class="form-label small" for="rv">{{ __('Reverse this run') }}</label><input id="rv" name="reason" class="form-control form-control-sm mb-2" placeholder="{{ __('Reason') }}" required maxlength="200"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('Reverse') }}</button>
                <div class="form-text">{{ __('Only the latest charge of every asset can be reversed.') }}</div></div></form>
        @endcan @endif
    </div>
</div>
@endsection
