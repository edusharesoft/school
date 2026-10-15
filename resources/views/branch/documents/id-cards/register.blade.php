@extends('layouts.branch')
@section('title', __('ID card register'))

@section('content')
<x-page-header :title="__('ID card register')" :breadcrumbs="[__('Documents') => null, __('ID cards') => route('branch.idcards.index'), __('Register') => null]" />

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Card no., name or ID') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-2"><select name="holder" class="form-select form-select-sm" aria-label="{{ __('Holder') }}"><option value="">{{ __('Everyone') }}</option>
                <option value="student" @selected(request('holder') === 'student')>{{ __('Students') }}</option><option value="employee" @selected(request('holder') === 'employee')>{{ __('Staff') }}</option></select></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option>
                @foreach(App\Models\Documents\IdCard::STATUSES as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ __(ucfirst($st)) }}</option>@endforeach</select></div>
            <div class="col-auto"><button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button></div>
        </form>
    </div>
    @if($cards->isEmpty())
        <x-empty-state icon="person-vcard" :title="__('No cards issued yet')" :message="__('Print cards from the ID cards page.')" />
    @else
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>{{ __('Card no.') }}</th><th>{{ __('Holder') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Valid until') }}</th><th>{{ __('Printed') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($cards as $c)
                <tr>
                    <td><code>{{ $c->card_no }}</code></td>
                    <td>{{ $c->holderName() }} <code class="small">{{ $c->holder_type === 'student' ? $c->student?->student_code : $c->employee?->employee_code }}</code></td>
                    <td class="small">{{ $c->issued_on->format('d M Y') }}<br>{{ $c->issuer?->name }}</td>
                    <td class="small">{{ $c->valid_until->format('d M Y') }}@if($c->isActive() && $c->isExpired()) <span class="badge text-bg-warning">{{ __('Expired') }}</span>@endif</td>
                    <td class="small">{{ $c->printed_count }}×</td>
                    <td>@switch($c->status)
                        @case('active')<span class="badge text-bg-success">{{ __('Active') }}</span>@break
                        @case('lost')<span class="badge text-bg-danger" title="{{ $c->status_reason }}">{{ __('Lost') }}</span>@break
                        @case('revoked')<span class="badge text-bg-dark" title="{{ $c->status_reason }}">{{ __('Revoked') }}</span>@break
                        @default<span class="badge text-bg-secondary" title="{{ $c->status_reason }}">{{ __('Replaced') }}</span>
                    @endswitch</td>
                    <td class="text-end text-nowrap">
                        @if($c->isActive())
                            @can('id-cards.issue')<a href="{{ route('branch.idcards.reprint', $c) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" aria-label="{{ __('Reprint') }}"><i class="bi bi-printer"></i></a>@endcan
                            @canany(['id-cards.issue', 'id-cards.revoke'])
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#ic{{ $c->id }}" aria-label="{{ __('Lost or replace') }}"><i class="bi bi-slash-circle"></i></button>
                            @endcanany
                        @endif
                    </td>
                </tr>
                @if($c->isActive())
                <tr class="collapse" id="ic{{ $c->id }}"><td colspan="7">
                    <div class="row g-2">
                        @can('id-cards.revoke')
                        <form method="POST" action="{{ route('branch.idcards.revoke', $c) }}" class="col-lg-6 d-flex gap-2">@csrf
                            <select name="status" class="form-select form-select-sm" style="max-width:130px" aria-label="{{ __('Mark as') }}"><option value="lost">{{ __('Lost') }}</option><option value="revoked">{{ __('Revoked') }}</option></select>
                            <input name="reason" class="form-control form-control-sm" placeholder="{{ __('Reason') }}" required minlength="3" maxlength="255" aria-label="{{ __('Reason') }}">
                            <button class="btn btn-sm btn-danger">{{ __('Mark') }}</button></form>
                        @endcan
                        @can('id-cards.issue')
                        <form method="POST" action="{{ route('branch.idcards.reissue', $c) }}" class="col-lg-6 d-flex gap-2" data-confirm="{{ __('Issue a new card? The old card stops being valid.') }}">@csrf
                            <input name="reason" class="form-control form-control-sm" placeholder="{{ __('Why a new card (lost, damaged …)') }}" required minlength="3" maxlength="255" aria-label="{{ __('Reason') }}">
                            <button class="btn btn-sm btn-outline-primary text-nowrap">{{ __('Issue new card') }}</button></form>
                        @endcan
                    </div>
                </td></tr>
                @endif
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $cards->links() }}</div>
    @endif
</div>
@endsection
