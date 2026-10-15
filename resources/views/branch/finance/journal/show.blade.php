@extends('layouts.branch')
@section('title', $entry->entry_no)

@section('content')
<x-page-header :title="__('Voucher').' '.$entry->entry_no" :breadcrumbs="[__('Day book') => route('branch.finance.journal.index'), $entry->entry_no => null]">
    <x-slot:actions><button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button></x-slot:actions>
</x-page-header>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-4 small border-bottom">
        <div><div class="text-body-secondary">{{ __('Date') }}</div>{{ $entry->date->format('d M Y') }}</div>
        <div><div class="text-body-secondary">{{ __('Type') }}</div>{{ __(ucfirst($entry->type)) }}</div>
        <div><div class="text-body-secondary">{{ __('Posted by') }}</div>{{ $entry->creator?->name ?? __('System') }} · {{ $entry->created_at->format('d M Y H:i') }}</div>
        <div><div class="text-body-secondary">{{ __('Status') }}</div>
            @if($entry->isReversed())<span class="badge text-bg-secondary">{{ __('Reversed') }}</span> <a href="{{ route('branch.finance.journal.show', $entry->reversed_by_id) }}">{{ $entry->reversedBy?->entry_no }}</a>
            @else<span class="badge text-bg-success">{{ __('Posted') }}</span>@endif
            @if($entry->reversalOf)<div>{{ __('Reverses') }} <a href="{{ route('branch.finance.journal.show', $entry->reversal_of_id) }}">{{ $entry->reversalOf->entry_no }}</a></div>@endif</div>
    </div>
    <div class="card-body fw-semibold">{{ $entry->narration }}</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Note') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
            <tbody>
            @foreach($entry->lines as $l)
                <tr><td>{{ $l->account?->label() }}@if($l->student)<div class="small text-body-secondary">{{ $l->student->name }} ({{ $l->student->student_code }})</div>@endif</td>
                    <td class="small">{{ $l->description }}</td>
                    <td class="text-end">{{ $l->debit > 0 ? number_format($l->debit, 2) : '' }}</td><td class="text-end">{{ $l->credit > 0 ? number_format($l->credit, 2) : '' }}</td></tr>
            @endforeach
            </tbody>
            <tfoot><tr><th colspan="2" class="text-end">{{ __('Total') }}</th><th class="text-end">{{ number_format($entry->lines->sum('debit'), 2) }}</th><th class="text-end">{{ number_format($entry->lines->sum('credit'), 2) }}</th></tr></tfoot>
        </table>
    </div>
</div>

@if(! $entry->isReversed() && ! $entry->reversal_of_id && ! $entry->source_type && auth()->user()->can('accounting.reverse'))
    <form method="POST" action="{{ route('branch.finance.journal.reverse', $entry) }}" class="card no-print" style="max-width:560px" data-confirm="{{ __('Post a reversal of this voucher?') }}">
        @csrf
        <div class="card-body"><x-form.input name="reason" :label="__('Reason for reversal')" required col="col-12" minlength="5" maxlength="255" /></div>
        <div class="card-footer bg-transparent"><button class="btn btn-outline-danger">{{ __('Reverse voucher') }}</button></div>
    </form>
@elseif($entry->source_type)
    <div class="small text-body-secondary no-print">{{ __('This voucher was posted by a fee, expense or payroll document and is corrected from that document.') }}</div>
@endif
@endsection
