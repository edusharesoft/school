@extends('layouts.branch')
@section('title', __('Staff certificates'))

@section('content')
<x-page-header :title="__('Staff certificate register')" :breadcrumbs="[__('Staff & HR') => route('branch.hr.employees.index'), __('Certificates') => null]" />

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Serial no., name or employee ID') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-3"><select name="type" class="form-select form-select-sm" aria-label="{{ __('Type') }}">
                <option value="">{{ __('All certificates') }}</option>
                @foreach(['experience' => __('Experience Certificate'), 'service' => __('Service Certificate')] as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach
            </select></div>
            <div class="col-auto"><button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button></div>
        </form>
    </div>
    @if($certificates->isEmpty())
        <x-empty-state icon="award" :title="__('No staff certificates issued yet')" :message="__('Issue certificates from an employee’s profile.')" />
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>{{ __('Serial no.') }}</th><th>{{ __('Certificate') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @foreach($certificates as $c)
                    <tr>
                        <td><code>{{ $c->serial_no }}</code></td>
                        <td>{{ $c->title() }}</td>
                        <td>{{ $c->employee?->name }} <code class="small">{{ $c->employee?->employee_code }}</code></td>
                        <td class="small">{{ $c->issued_on->format('d M Y') }} · {{ $c->issuer?->name }}</td>
                        <td>@if($c->isRevoked())<span class="badge text-bg-danger" title="{{ $c->revoke_reason }}">{{ __('Revoked') }}</span>@else<span class="badge text-bg-success">{{ __('Valid') }}</span>@endif</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('branch.hr.certificates.pdf', $c) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" aria-label="{{ __('PDF') }}"><i class="bi bi-file-earmark-pdf"></i></a>
                            @if(! $c->isRevoked())@can('staff-certificates.issue')
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rv{{ $c->id }}" aria-label="{{ __('Revoke') }}"><i class="bi bi-slash-circle"></i></button>
                            @endcan @endif
                        </td>
                    </tr>
                    @if(! $c->isRevoked())
                        <tr class="collapse" id="rv{{ $c->id }}"><td colspan="6">
                            <form method="POST" action="{{ route('branch.hr.certificates.revoke', $c) }}" class="d-flex gap-2">@csrf
                                <input name="reason" class="form-control form-control-sm" placeholder="{{ __('Reason for revoking') }}" required minlength="3" maxlength="500" aria-label="{{ __('Reason') }}">
                                <button class="btn btn-sm btn-danger">{{ __('Revoke') }}</button></form>
                        </td></tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $certificates->links() }}</div>
    @endif
</div>
@endsection
