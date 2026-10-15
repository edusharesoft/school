@extends('layouts.branch')
@section('title', __('Trial balance'))

@section('content')
<x-page-header :title="__('Trial balance')" :breadcrumbs="[__('Accounts') => route('branch.finance.accounts.index'), __('Trial balance') => null]" />
@include('branch.finance.reports._acc_nav')
@include('branch.finance.reports._range', ['mode' => 'asof'])

<div class="card" style="max-width: 900px">
    <div class="card-header bg-transparent fw-semibold">{{ tenant()->branch()?->name }} — {{ __('as of :date', ['date' => $asOf->format('d M Y')]) }}</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Account') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Debit (৳)') }}</th><th class="text-end">{{ __('Credit (৳)') }}</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr><td><code>{{ $r['account']->code }}</code></td><td><a href="{{ route('branch.finance.reports.ledger', ['account_id' => $r['account']->id, 'to' => $asOf->toDateString()]) }}">{{ $r['account']->name }}</a></td>
                    <td>{{ __(ucfirst($r['account']->type)) }}</td><td class="text-end">{{ $r['debit'] ? number_format($r['debit'], 2) : '' }}</td><td class="text-end">{{ $r['credit'] ? number_format($r['credit'], 2) : '' }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-body-secondary">{{ __('No postings yet.') }}</td></tr>
            @endforelse
            </tbody>
            <tfoot class="table-light"><tr><th colspan="3" class="text-end">{{ __('Total') }}</th><th class="text-end">{{ number_format($debit, 2) }}</th><th class="text-end">{{ number_format($credit, 2) }}</th></tr></tfoot>
        </table>
    </div>
    <div class="card-footer bg-transparent small {{ abs($debit - $credit) < 0.01 ? 'text-success' : 'text-danger' }}">
        {{ abs($debit - $credit) < 0.01 ? __('Debits equal credits — the books balance.') : __('Debits and credits differ by :diff.', ['diff' => number_format(abs($debit - $credit), 2)]) }}
    </div>
</div>
@endsection
