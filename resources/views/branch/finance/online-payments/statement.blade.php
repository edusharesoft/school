@extends('layouts.branch')
@section('title', __('Match a bank statement'))

@section('content')
<x-page-header :title="__('Match a bank statement')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Online payments') => route('branch.finance.online-payments.index'), __('Bangla QR statement') => null]" />

<div class="alert alert-info small" style="max-width: 880px">
    <div class="fw-semibold mb-1"><i class="fa-solid fa-circle-info me-1"></i>{{ __('How it works') }}</div>
    {{ __('Download the statement of the account that receives the Bangla QR payments (CSV) and upload it here. A row is accepted automatically when its transaction ID and amount match a payment the payer has reported, or when its text contains the payment reference of an unreported payment with the same amount. Everything else is listed below and nothing is guessed.') }}
    <div class="mt-1">{{ __('The first row must name the columns, for example: TrxID, Amount, Reference, Date.') }}</div>
</div>

<form method="POST" action="{{ route('branch.finance.online-payments.statement.match') }}" enctype="multipart/form-data" class="card mb-3" style="max-width: 880px">
    @csrf
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-8">
            <label class="form-label" for="statement">{{ __('Statement (CSV)') }}</label>
            <input id="statement" type="file" name="statement" accept=".csv,.txt,text/csv" class="form-control @error('statement') is-invalid @enderror" required>
            @error('statement')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4"><button class="btn btn-primary w-100"><i class="fa-solid fa-link me-1"></i>{{ __('Match and accept') }}</button></div>
        <div class="col-12 small text-body-secondary">{{ trans_choice(':count Bangla QR payment is waiting for confirmation.|:count Bangla QR payments are waiting for confirmation.', $waiting, ['count' => $waiting]) }}</div>
    </div>
</form>

@if($result)
    @php($sections = ['accepted' => ['success', __('Accepted')], 'already' => ['secondary', __('Already recorded')], 'mismatch' => ['warning', __('Amount or status differs — check by hand')], 'unmatched' => ['danger', __('No payment found')]])
    <div class="small text-body-secondary mb-2">{{ trans_choice(':count statement row was read.|:count statement rows were read.', $result['rows'], ['count' => $result['rows']]) }}</div>
    @foreach($sections as $key => [$tone, $title])
        @if(! empty($result[$key]))
            <div class="card mb-3" style="max-width: 880px">
                <div class="card-header bg-transparent fw-semibold d-flex gap-2"><span class="badge text-bg-{{ $tone }}">{{ count($result[$key]) }}</span>{{ $title }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 small align-middle">
                        <thead><tr><th>{{ __('TrxID') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Payment reference') }}</th><th>{{ __('Note') }}</th></tr></thead>
                        <tbody>
                        @foreach($result[$key] as $row)
                            <tr>
                                <td class="font-monospace">{{ $row['trx'] }}</td>
                                <td class="text-end">৳{{ number_format($row['amount'], 2) }}</td>
                                <td class="font-monospace">{{ $row['tran_id'] ?? '—' }}</td>
                                <td>
                                    @if($key === 'accepted'){{ $row['by'] === 'trx' ? __('Matched by transaction ID') : __('Matched by payment reference') }}
                                    @elseif($key === 'mismatch'){{ __('Expected ৳:amount (:status)', ['amount' => number_format((float) ($row['expected'] ?? 0), 2), 'status' => __(ucfirst($row['status'] ?? ''))]) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endforeach
@endif
@endsection
