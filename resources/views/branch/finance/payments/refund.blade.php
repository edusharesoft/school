@extends('layouts.base')
@section('title', $refund->refund_no)

@include('branch.exams.documents._print-styles', ['width' => '720px', 'page' => 'A4'])

@section('body')
@include('branch.exams.documents._toolbar', ['back' => route('branch.finance.students.show', [$refund->student_id, 'tab' => 'payments'])])
<div class="sheet">
    @include('branch.exams.documents._letterhead')
    <div class="doc-title">{{ __('Refund voucher') }}</div>
    <table class="noborder my-2" style="font-size:13px">
        <tr><td style="width:22%">{{ __('Voucher no.') }}</td><td><strong>{{ $refund->refund_no }}</strong></td><td style="width:12%">{{ __('Date') }}</td><td>{{ $refund->date->format('d M Y') }}</td></tr>
        <tr><td>{{ __('Paid to') }}</td><td>{{ $refund->student?->name }} ({{ $refund->student?->student_code }})</td><td>{{ __('Method') }}</td><td>{{ __(ucfirst($refund->method)) }} · {{ $refund->account?->name }}</td></tr>
        <tr><td>{{ __('Amount') }}</td><td colspan="3"><strong>৳{{ number_format($refund->amount, 2) }}</strong></td></tr>
        <tr><td>{{ __('Reason') }}</td><td colspan="3">{{ $refund->reason }}</td></tr>
    </table>
    <div class="d-flex justify-content-between" style="margin-top:50px"><div class="sig">{{ __('Received by (guardian)') }}</div><div class="sig">{{ __('Paid by') }}: {{ $refund->refunder?->name }}</div><div class="sig">{{ __('Approved by') }}</div></div>
</div>
@endsection
