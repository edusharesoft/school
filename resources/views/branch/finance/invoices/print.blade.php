{{-- Printable fee invoice (A5, two copies: student & office). --}}
@extends('layouts.base')
@section('title', $invoice->invoice_no)

@include('branch.exams.documents._print-styles', ['width' => '720px', 'page' => 'A4'])

@section('body')
@include('branch.exams.documents._toolbar', ['back' => $back ?? route('branch.finance.invoices.show', $invoice)])
@foreach([__('Student copy'), __('Office copy')] as $copy)
<div class="sheet" style="margin-bottom:12px">
    @if($invoice->status === 'cancelled')<div class="stamp">{{ __('CANCELLED') }}</div>@elseif($invoice->status === 'paid')<div class="stamp" style="color:rgba(22,163,74,.18)">{{ __('PAID') }}</div>@endif
    @include('branch.exams.documents._letterhead')
    <div class="d-flex justify-content-between align-items-center"><div class="doc-title m-0">{{ __('Fee invoice') }}</div><small>{{ $copy }}</small></div>
    <table class="noborder my-2" style="font-size:12.5px">
        <tr><td style="width:18%">{{ __('Invoice no.') }}</td><td><strong>{{ $invoice->invoice_no }}</strong></td><td style="width:15%">{{ __('Issued') }}</td><td>{{ $invoice->issue_date->format('d M Y') }}</td></tr>
        <tr><td>{{ __('Student') }}</td><td><strong>{{ $invoice->student?->name }}</strong> ({{ $invoice->student?->student_code }})</td><td>{{ __('Due date') }}</td><td><strong>{{ $invoice->due_date->format('d M Y') }}</strong></td></tr>
        <tr><td>{{ __('Class') }}</td><td>{{ $invoice->schoolClass?->name }} {{ $invoice->section?->name }}</td><td>{{ __('For') }}</td><td>{{ $invoice->title }}</td></tr>
    </table>
    <table>
        <thead><tr><th>#</th><th>{{ __('Particulars') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Net (৳)') }}</th></tr></thead>
        <tbody>
        @foreach($invoice->items as $i => $item)
            <tr><td>{{ $i + 1 }}</td><td>{{ $item->description }}@if($item->discount_note) <small>({{ $item->discount_note }})</small>@endif</td>
                <td class="text-end">{{ number_format($item->amount, 2) }}</td><td class="text-end">{{ $item->discount > 0 ? number_format($item->discount, 2) : '' }}</td><td class="text-end">{{ number_format($item->net(), 2) }}</td></tr>
        @endforeach
        <tr><th colspan="4" class="text-end">{{ __('Total') }}</th><th class="text-end">{{ number_format($invoice->total, 2) }}</th></tr>
        <tr><td colspan="4" class="text-end">{{ __('Paid') }}</td><td class="text-end">{{ number_format($invoice->paid, 2) }}</td></tr>
        @if($invoice->waived > 0)<tr><td colspan="4" class="text-end">{{ __('Waived') }}</td><td class="text-end">{{ number_format($invoice->waived, 2) }}</td></tr>@endif
        <tr><th colspan="4" class="text-end">{{ __('Balance due') }}</th><th class="text-end">{{ number_format($invoice->balance, 2) }}</th></tr>
        </tbody>
    </table>
    @if($invoice->notes)<div class="mt-1" style="font-size:12px">{{ $invoice->notes }}</div>@endif
    <div class="d-flex justify-content-between align-items-end" style="margin-top:36px"><small>{{ __('Please pay by the due date to avoid a late fee.') }}</small><div class="sig">{{ __('Accounts office') }}</div></div>
</div>
@endforeach
@endsection
