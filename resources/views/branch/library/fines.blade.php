@extends('layouts.branch')
@section('title', __('Library fines'))

@section('content')
<x-page-header :title="__('Library fines')" :breadcrumbs="[__('Library') => route('branch.library.index'), __('Fines') => null]" />
<ul class="nav nav-pills mb-3">@foreach(['unpaid' => __('Unpaid'), 'paid' => __('Paid'), 'waived' => __('Waived')] as $key => $label)
    <li class="nav-item"><a class="nav-link @if($status === $key) active @endif" href="{{ route('branch.library.fines.index', ['status' => $key]) }}">{{ $label }} <span class="badge text-bg-light">৳{{ number_format((float) ($totals[$key] ?? 0), 2) }}</span></a></li>
@endforeach</ul>

<div class="row g-3">
    <div class="col-lg-9"><div class="card">
        @if($fines->isEmpty())<x-empty-state icon="coin" :title="__('No fines here')" />
        @else<div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Reason') }}</th><th class="text-end">{{ __('Amount') }}</th><th></th></tr></thead>
            <tbody>@foreach($fines as $f)
                <tr>
                    <td><a href="{{ route('branch.library.member', [$f->member_type, $f->member_id]) }}">{{ $f->memberModel()?->name ?? '—' }}</a><div class="text-body-secondary">{{ $f->created_at->format('d M Y') }}</div></td>
                    <td>{{ __(ucfirst($f->kind)) }}@if($f->transaction?->book) — {{ $f->transaction->book->displayTitle() }}@endif<div class="text-body-secondary">{{ $f->note }}</div></td>
                    <td class="text-end fw-semibold">৳{{ number_format((float) $f->amount, 2) }}</td>
                    <td class="text-end text-nowrap">
                        @if($f->status === 'unpaid')
                            @can('library.issue')
                            <form method="POST" action="{{ route('branch.library.fines.pay', $f) }}" class="d-inline-flex gap-1">@csrf
                                <select name="method" class="form-select form-select-sm" aria-label="{{ __('Method') }}">@foreach($methods as $m)<option value="{{ $m }}">{{ __(ucfirst($m)) }}</option>@endforeach</select>
                                <button class="btn btn-sm btn-brand">{{ __('Collect') }}</button>
                            </form>
                            @endcan
                            @can('library.delete')
                            <form method="POST" action="{{ route('branch.library.fines.waive', $f) }}" class="d-inline-flex gap-1 mt-1" onsubmit="this.reason.value = prompt(@js(__('Reason for waiving'))) || ''; return this.reason.value !== ''">@csrf<input type="hidden" name="reason"><button class="btn btn-sm btn-outline-secondary">{{ __('Waive') }}</button></form>
                            @endcan
                        @elseif($f->status === 'paid')
                            <a href="{{ route('branch.library.fines.receipt', $f) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>{{ $f->receipt_no }}</a>
                        @endif
                    </td>
                </tr>
            @endforeach</tbody>
        </table></div><div class="card-footer bg-transparent">{{ $fines->links() }}</div>@endif
    </div></div>
    @can('library.issue')
    <div class="col-lg-3"><form method="POST" action="{{ route('branch.library.fines.store') }}" class="card" x-data="{ q: '', results: [], key: '',
            async search() { if (this.q.trim().length < 2) { this.results = []; return; } const r = await fetch(@js(route('branch.library.desk.members')) + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } }); this.results = r.ok ? await r.json() : []; } }">
        @csrf <input type="hidden" name="member" :value="key">
        <div class="card-header bg-transparent fw-semibold">{{ __('Charge a fine') }}</div>
        <div class="card-body row g-2">
            <div class="col-12"><input type="search" x-model="q" @input.debounce.300ms="search()" class="form-control form-control-sm" placeholder="{{ __('Search member…') }}" aria-label="{{ __('Search member') }}">
                <div class="list-group mt-1" x-show="results.length"><template x-for="m in results" :key="m.key"><button type="button" class="list-group-item list-group-item-action small" @click="key = m.key; q = m.name; results = []" x-text="m.name + ' · ' + (m.code || '')"></button></template></div></div>
            <div class="col-12"><select name="kind" class="form-select form-select-sm" aria-label="{{ __('Kind') }}"><option value="damaged">{{ __('Damaged') }}</option><option value="lost">{{ __('Lost') }}</option><option value="overdue">{{ __('Overdue') }}</option></select></div>
            <div class="col-12"><input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" placeholder="{{ __('Amount') }}" required aria-label="{{ __('Amount') }}"></div>
            <div class="col-12"><input name="note" class="form-control form-control-sm" maxlength="255" placeholder="{{ __('Note') }}" aria-label="{{ __('Note') }}"></div>
        </div>
        <div class="card-footer bg-transparent"><button class="btn btn-sm btn-brand" :disabled="!key">{{ __('Record') }}</button></div>
    </form></div>
    @endcan
</div>
@endsection
