@extends('layouts.branch')
@section('title', __('Day book'))

@section('content')
<x-page-header :title="__('Day book')" :breadcrumbs="[__('Accounts') => route('branch.finance.accounts.index'), __('Day book') => null]">
    <x-slot:actions>
        @can('accounting.post')<a href="{{ route('branch.finance.journal.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New voucher') }}</a>@endcan
    </x-slot:actions>
</x-page-header>
@include('branch.finance.reports._acc_nav')

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><select name="type" class="form-select form-select-sm" aria-label="{{ __('Type') }}"><option value="">{{ __('All types') }}</option>
                @foreach(App\Models\Finance\JournalEntry::TYPES as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ __(ucfirst($t)) }}</option>@endforeach</select></div>
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Voucher no. or narration') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($entries->isEmpty())
        <x-empty-state icon="journal-text" :title="__('No vouchers in this period')" />
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Particulars') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
                <tbody>
                @foreach($entries as $e)
                    <tr class="table-light">
                        <td class="text-nowrap">{{ $e->date->format('d M Y') }}</td>
                        <td class="text-nowrap"><a href="{{ route('branch.finance.journal.show', $e) }}"><code>{{ $e->entry_no }}</code></a> <span class="badge text-bg-light border">{{ __(ucfirst($e->type)) }}</span>
                            @if($e->isReversed())<span class="badge text-bg-secondary">{{ __('Reversed') }}</span>@endif</td>
                        <td colspan="3" class="fw-semibold">{{ $e->narration }}</td>
                    </tr>
                    @foreach($e->lines as $l)
                        <tr><td></td><td></td><td class="ps-4">{{ $l->account?->label() }}@if($l->description) <span class="text-body-secondary">— {{ $l->description }}</span>@endif</td>
                            <td class="text-end">{{ $l->debit > 0 ? number_format($l->debit, 2) : '' }}</td><td class="text-end">{{ $l->credit > 0 ? number_format($l->credit, 2) : '' }}</td></tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $entries->links() }}</div>
    @endif
</div>
@endsection
