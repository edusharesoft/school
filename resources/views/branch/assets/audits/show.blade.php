@extends('layouts.branch')
@section('title', $audit->audit_no)

@section('content')
@php $open = $audit->status === 'open'; $canUpdate = auth()->user()->can('assets.update'); @endphp
<x-page-header :title="$audit->audit_no.' — '.$audit->title" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Verification') => route('branch.assets.audits.index'), $audit->audit_no => null]" />
<div class="alert alert-light border small d-flex flex-wrap gap-3 align-items-center">
    <span><x-inv-status :status="$audit->status" /></span><span>{{ $audit->date->format('d M Y') }}</span><span>{{ collect([$audit->category?->name, $audit->room?->name])->filter()->implode(' · ') ?: __('All assets') }}</span>
    <span class="ms-auto">{{ __(':a of :b checked · :m missing', ['a' => $items->whereNotNull('result')->count(), 'b' => $items->count(), 'm' => $items->where('result', 'missing')->count()]) }}</span>
</div>
@error('results')<div class="alert alert-danger small">{{ $message }}</div>@enderror
<form method="POST" action="{{ route('branch.assets.audits.results', $audit) }}" id="results">@csrf @method('PUT')</form>
<div class="card">
    <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
        <thead><tr><th>{{ __('Tag') }}</th><th>{{ __('Asset') }}</th><th>{{ __('Should be at') }}</th><th style="width:150px">{{ __('Result') }}</th><th>{{ __('Found at') }}</th><th>{{ __('Note') }}</th></tr></thead>
        <tbody>@foreach($items as $i)<tr>
            <td class="text-nowrap text-body-secondary">{{ $i->asset?->code }}</td>
            <td>{{ $i->asset?->displayName() }}@if($i->asset?->serial_no)<div class="text-body-secondary">{{ $i->asset->serial_no }}</div>@endif</td>
            <td>{{ $i->expected_location ?: '—' }}</td>
            <td>@if($open && $canUpdate)<select form="results" name="results[{{ $i->id }}][result]" class="form-select form-select-sm" aria-label="{{ __('Result') }}"><option value="">—</option>@foreach(App\Models\Assets\AuditItem::RESULTS as $r)<option value="{{ $r }}" @selected($i->result === $r)>{{ __(ucfirst($r)) }}</option>@endforeach</select>
                @else<span class="@if($i->result === 'missing') text-danger fw-semibold @elseif($i->result === 'damaged') text-warning @endif">{{ $i->result ? __(ucfirst($i->result)) : '—' }}</span>@endif</td>
            <td>@if($open && $canUpdate)<input form="results" name="results[{{ $i->id }}][found_location]" value="{{ $i->found_location }}" class="form-control form-control-sm" maxlength="150" aria-label="{{ __('Found at') }}">@else{{ $i->found_location ?: '—' }}@endif</td>
            <td>@if($open && $canUpdate)<input form="results" name="results[{{ $i->id }}][note]" value="{{ $i->note }}" class="form-control form-control-sm" maxlength="255" aria-label="{{ __('Note') }}">@else{{ $i->note ?: '—' }}@endif</td></tr>@endforeach</tbody></table></div>
    @if($open && $canUpdate)
    <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
        <button form="results" class="btn btn-outline-secondary"><i class="bi bi-save me-1"></i>{{ __('Save counts') }}</button>
        <div class="form-check align-self-center"><input form="results" type="checkbox" class="form-check-input" id="cmp" name="complete" value="1"><label class="form-check-label" for="cmp">{{ __('Complete the audit after saving (updates the register)') }}</label></div>
        <form method="POST" action="{{ route('branch.assets.audits.cancel', $audit) }}" class="ms-auto" data-confirm="{{ __('Cancel this audit?') }}">@csrf <button class="btn btn-outline-danger">{{ __('Cancel audit') }}</button></form>
        <div class="form-text w-100">{{ __('Found assets are stamped as verified, damaged ones change condition, missing ones are flagged — nothing is written off automatically.') }}</div>
    </div>@endif
</div>
@endsection
