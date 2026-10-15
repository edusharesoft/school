@extends('layouts.central')
@section('title', __('Newsletter subscribers'))

@section('content')
<x-page-header :title="__('Newsletter subscribers')" :breadcrumbs="[__('Website') => route('central.website.settings'), __('Newsletter subscribers') => null]">
    <x-slot:actions><a href="{{ route('central.website.subscribers.export') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>{{ __('Export CSV') }}</a></x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom d-flex flex-wrap justify-content-between gap-2">
        <form method="GET" class="d-flex gap-2"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search e-mail') }}" aria-label="{{ __('Search') }}">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button></form>
        <span class="small text-body-secondary align-self-center">{{ __('Active subscribers') }}: <b>{{ $active }}</b></span>
    </div>
    @if($subscribers->isEmpty())
        <x-empty-state icon="envelope" :title="__('No subscribers yet')" />
    @else
        <div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('E-mail') }}</th><th>{{ __('Language') }}</th><th>{{ __('Subscribed') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @foreach($subscribers as $s)
                <tr><td>{{ $s->email }}</td><td>{{ strtoupper($s->locale) }}</td><td>{{ $s->created_at->format('d M Y H:i') }}</td>
                    <td>@if($s->unsubscribed_at)<span class="badge text-bg-secondary">{{ __('Unsubscribed') }}</span>@else<span class="badge text-bg-success">{{ __('Active') }}</span>@endif</td></tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="card-footer bg-transparent">{{ $subscribers->links() }}</div>
    @endif
</div>
@endsection
