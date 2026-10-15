@extends('layouts.base')
@section('title', __('Salary slips').' — '.$run->monthLabel())

@include('branch.exams.documents._print-styles', ['width' => '720px', 'page' => 'A4'])

@section('body')
@include('branch.exams.documents._toolbar', ['back' => route('branch.finance.payroll.show', $run)])
@foreach($items as $item)
    <div class="sheet {{ $loop->last ? '' : 'page-break' }}" style="margin-bottom:12px">
        @if($run->status === 'draft')<div class="stamp">{{ __('DRAFT') }}</div>@elseif($run->status === 'cancelled')<div class="stamp">{{ __('CANCELLED') }}</div>@endif
        @include('branch.exams.documents._letterhead')
        <div class="doc-title">{{ __('Salary slip') }} — {{ $run->monthLabel() }}</div>
        <table class="noborder mb-2" style="font-size:12.5px">
            <tr><td style="width:20%">{{ __('Name') }}</td><td><strong>{{ $item->employee?->name }}</strong></td><td style="width:18%">{{ __('Employee ID') }}</td><td>{{ $item->employee?->employee_code }}</td></tr>
            <tr><td>{{ __('Designation') }}</td><td>{{ $item->employee?->designation?->name }}</td><td>{{ __('Department') }}</td><td>{{ $item->employee?->department?->name }}</td></tr>
            <tr><td>{{ __('Paid by') }}</td><td>{{ __(ucfirst($item->employee?->salary?->payment_method ?? '')) }} {{ $item->employee?->salary?->account_number }}</td><td>{{ __('Paid on') }}</td><td>{{ $run->paid_on?->format('d M Y') ?? '—' }}</td></tr>
        </table>
        <div class="d-flex gap-3">
            <table>
                <thead><tr><th>{{ __('Earnings') }}</th><th class="text-end">৳</th></tr></thead>
                <tbody>
                    <tr><td>{{ __('Basic') }}</td><td class="text-end">{{ number_format($item->basic, 2) }}</td></tr>
                    @foreach($item->allowances ?? [] as $a)<tr><td>{{ $a['name'] }}</td><td class="text-end">{{ number_format($a['amount'], 2) }}</td></tr>@endforeach
                    <tr><th>{{ __('Gross') }}</th><th class="text-end">{{ number_format($item->gross, 2) }}</th></tr>
                </tbody>
            </table>
            <table>
                <thead><tr><th>{{ __('Deductions') }}</th><th class="text-end">৳</th></tr></thead>
                <tbody>
                    @foreach($item->deductions ?? [] as $d)<tr><td>{{ $d['name'] }}</td><td class="text-end">{{ number_format($d['amount'], 2) }}</td></tr>@endforeach
                    @if($item->unpaid_days > 0)<tr><td>{{ __('Unpaid days: :days', ['days' => $item->unpaid_days]) }}</td><td class="text-end">{{ number_format($item->unpaid_deduction, 2) }}</td></tr>@endif
                    <tr><th>{{ __('Total deductions') }}</th><th class="text-end">{{ number_format($item->total_deductions, 2) }}</th></tr>
                </tbody>
            </table>
        </div>
        <div class="mt-2" style="font-size:14px">{{ __('Net pay') }}: <strong>৳{{ number_format($item->net, 2) }}</strong> — <em>{{ \App\Support\AmountInWords::taka($item->net) }}</em></div>
        @if($item->remarks)<div class="mt-1" style="font-size:12px">{{ $item->remarks }}</div>@endif
        <div class="d-flex justify-content-between" style="margin-top:44px"><div class="sig">{{ __('Employee') }}</div><div class="sig">{{ __('Accounts office') }}</div><div class="sig">{{ __('Principal / Head Teacher') }}</div></div>
    </div>
@endforeach
@endsection
