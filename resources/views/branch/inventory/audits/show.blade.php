@extends('layouts.branch')
@section('title', $audit->audit_no)

@section('content')
<x-page-header :title="$audit->audit_no.' — '.$audit->title" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Stock audits') => route('branch.inventory.audits.index'), $audit->audit_no => null]" />
<div class="row g-3">
    <div class="col-lg-9">
        @php($open = $audit->status === 'open')
        <form method="POST" action="{{ route('branch.inventory.audits.counts', $audit) }}" class="card">@csrf @method('PUT')
            <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
                <thead class="table-light"><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Book stock') }}</th><th style="width:150px">{{ __('Counted') }}</th><th class="text-end">{{ __('Difference') }}</th><th>{{ __('Note') }}</th></tr></thead>
                <tbody>@foreach($lines as $l)@php($v = $l->variance())
                    <tr><td>{{ $l->item?->name }}<div class="text-body-secondary">{{ $l->item?->code }} · {{ $l->item?->location }}</div></td>
                        <td class="text-end">{{ rtrim(rtrim(number_format($l->expected_quantity, 3, '.', ''), '0'), '.') ?: '0' }} {{ $l->item?->unit }}</td>
                        <td>@if($open)<input type="number" step="0.001" min="0" name="counts[{{ $l->id }}][counted]" value="{{ old("counts.{$l->id}.counted", $l->counted_quantity) }}" class="form-control form-control-sm text-end" aria-label="{{ __('Counted') }}">@else{{ $l->counted_quantity === null ? '—' : rtrim(rtrim(number_format($l->counted_quantity, 3, '.', ''), '0'), '.') }}@endif</td>
                        <td class="text-end fw-semibold @if($v !== null && $v < 0) text-danger @elseif($v) text-success @endif">{{ $v === null ? '' : (($v > 0 ? '+' : '').rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.') ?: '0') }}</td>
                        <td>@if($open)<input name="counts[{{ $l->id }}][note]" value="{{ old("counts.{$l->id}.note", $l->note) }}" class="form-control form-control-sm" maxlength="255" aria-label="{{ __('Note') }}">@else{{ $l->note }}@endif</td></tr>@endforeach</tbody></table></div>
            @if($open)@can('inventory.update')<div class="card-footer bg-transparent"><button class="btn btn-brand btn-sm"><i class="bi bi-save me-1"></i>{{ __('Save counts') }}</button></div>@endcan @endif
        </form>
    </div>
    <div class="col-lg-3">
        <div class="card mb-3"><div class="card-body small"><div class="mb-2"><x-inv-status :status="$audit->status" /></div>
            <dl class="row mb-0"><dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $audit->date->format('d M Y') }}</dd>
                <dt class="col-5">{{ __('Scope') }}</dt><dd class="col-7">{{ $audit->category?->name ?? __('All items') }}</dd>
                <dt class="col-5">{{ __('Opened by') }}</dt><dd class="col-7">{{ $audit->starter?->name }}</dd>
                @if($audit->adjustment)<dt class="col-5">{{ __('Adjustment') }}</dt><dd class="col-7"><a href="{{ route('branch.inventory.adjustments.show', $audit->adjustment) }}">{{ $audit->adjustment->adjustment_no }}</a></dd>@endif</dl></div></div>
        @if($open)
            @can('inventory.approve')<form method="POST" action="{{ route('branch.inventory.audits.complete', $audit) }}" class="mb-2" data-confirm="{{ __('Complete the audit? Differences are posted to stock and the books.') }}">@csrf<button class="btn btn-success btn-sm w-100"><i class="bi bi-check2-all me-1"></i>{{ __('Complete & post differences') }}</button>
                <div class="form-text">{{ __('Save the counts first. Lines left empty are not adjusted. The difference is counted − stock on hand now.') }}</div></form>@endcan
            @can('inventory.update')<form method="POST" action="{{ route('branch.inventory.audits.cancel', $audit) }}" data-confirm="{{ __('Cancel this audit?') }}">@csrf<button class="btn btn-outline-secondary btn-sm w-100">{{ __('Cancel audit') }}</button></form>@endcan
        @endif
    </div>
</div>
@endsection
