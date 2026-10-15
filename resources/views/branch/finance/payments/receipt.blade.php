{{-- Money receipt (two copies). --}}
@extends('layouts.base')
@section('title', $payment->receipt_no)

@include('branch.exams.documents._print-styles', ['width' => '720px', 'page' => 'A4'])

@section('body')
@include('branch.exams.documents._toolbar', ['back' => $back ?? route('branch.finance.students.show', [$payment->student_id, 'tab' => 'payments'])])
@foreach([__('Payer copy'), __('Office copy')] as $copy)
<div class="sheet" style="margin-bottom:12px">
    @if($payment->isReversed())<div class="stamp">{{ __('REVERSED') }}</div>@endif
    @include('branch.exams.documents._letterhead')
    <div class="d-flex justify-content-between align-items-center"><div class="doc-title m-0">{{ __('Money receipt') }}</div><small>{{ $copy }}</small></div>
    <table class="noborder my-2" style="font-size:12.5px">
        <tr><td style="width:20%">{{ __('Receipt no.') }}</td><td><strong>{{ $payment->receipt_no }}</strong></td><td style="width:12%">{{ __('Date') }}</td><td>{{ $payment->date->format('d M Y') }}</td></tr>
        <tr><td>{{ __('Received from') }}</td><td><strong>{{ $payment->student?->name }}</strong> ({{ $payment->student?->student_code }})</td>
            <td>{{ __('Method') }}</td><td>{{ __(ucfirst($payment->method)) }}@if($payment->reference) — {{ $payment->reference }}@endif</td></tr>
    </table>
    <table>
        <thead><tr><th>{{ __('Against') }}</th><th class="text-end">{{ __('Amount (৳)') }}</th></tr></thead>
        <tbody>
        @foreach($payment->allocations->where('from_credit', false) as $a)
            <tr><td>{{ $a->invoice?->invoice_no }} — {{ $a->invoice?->title }}</td><td class="text-end">{{ number_format($a->amount, 2) }}</td></tr>
        @endforeach
        @php($advance = round($payment->amount - $payment->allocations->where('from_credit', false)->sum('amount'), 2))
        @if($advance > 0)<tr><td>{{ __('Advance (kept as credit for coming invoices)') }}</td><td class="text-end">{{ number_format($advance, 2) }}</td></tr>@endif
        <tr><th class="text-end">{{ __('Total received') }}</th><th class="text-end">{{ number_format($payment->amount, 2) }}</th></tr>
        </tbody>
    </table>
    <div class="mt-1" style="font-size:12px">{{ __('In words') }}: <em>{{ \App\Support\AmountInWords::taka($payment->amount) }}</em></div>
    <div class="mt-1" style="font-size:12px">{{ __('Remaining dues') }}: <strong>৳{{ number_format($balance, 2) }}</strong> · {{ __('Advance credit') }}: ৳{{ number_format($credit, 2) }}</div>
    @if($note)<div class="mt-1" style="font-size:11px;color:#475569">{{ $note }}</div>@endif
    <div class="d-flex justify-content-between align-items-end" style="margin-top:36px"><small>{{ __('Received by') }}: {{ $payment->receiver?->name }}</small><div class="sig">{{ __('Signature & seal') }}</div></div>
</div>
@endforeach
@endsection
