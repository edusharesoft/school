@extends('layouts.branch')
@section('title', $session->exists ? __('Edit session') : __('New session'))

@section('content')
<x-page-header :title="$session->exists ? __('Edit session: :name', ['name' => $session->name]) : __('New session')"
    :breadcrumbs="[__('Sessions') => route('branch.academics.sessions.index'), ($session->exists ? $session->name : __('New')) => null]" />

@if($years->isEmpty())
    <div class="alert alert-warning">{{ __('No academic year exists yet. Ask the central administration to create one first.') }}</div>
@endif

<form method="POST" action="{{ $session->exists ? route('branch.academics.sessions.update', $session) : route('branch.academics.sessions.store') }}" class="card mb-3">
    @csrf
    @if($session->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.select name="academic_year_id" :label="__('Academic year')" :options="$years" :value="$session->academic_year_id" required col="col-md-3" />
        <x-form.input name="name" :label="__('Session name')" :value="$session->name" required col="col-md-3" maxlength="50" placeholder="2026" />
        <x-form.input name="starts_on" type="date" :label="__('Starts on')" :value="$session->starts_on?->toDateString()" required col="col-md-3" />
        <x-form.input name="ends_on" type="date" :label="__('Ends on')" :value="$session->ends_on?->toDateString()" required col="col-md-3" />
        <x-form.select name="status" :label="__('Status')" :options="['open' => __('Open'), 'closed' => __('Closed')]" :value="$session->status" col="col-md-3"
            :help="__('Closed sessions accept no new enrollments.')" />
        <x-form.switch name="is_current" :label="__('Current session for this branch')" :checked="$session->is_current" col="col-md-9 align-self-center" />
    </div>
    <x-form.actions :cancel="route('branch.academics.sessions.index')" />
</form>

@if($session->exists)
    <div class="card">
        <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Terms / semesters') }}</h2></div>
        <div class="card-body">
            @if($session->terms->isNotEmpty())
                <ul class="list-group mb-3">
                    @foreach($session->terms as $term)
                        <li class="list-group-item d-flex align-items-center">
                            <span class="fw-semibold me-2">{{ $term->name }}</span>
                            <span class="small text-body-secondary">{{ $term->starts_on?->format('d M') }} – {{ $term->ends_on?->format('d M Y') }}</span>
                            <form method="POST" action="{{ route('branch.academics.sessions.terms.destroy', [$session, $term]) }}" class="ms-auto" data-confirm="{{ __('Remove this term?') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-link text-danger p-0">{{ __('Remove') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('branch.academics.sessions.terms.store', $session) }}" class="row g-2 align-items-end">
                @csrf
                <x-form.input name="name" :label="__('Term name')" col="col-md-4" required placeholder="{{ __('Half-yearly') }}" />
                <x-form.input name="starts_on" type="date" :label="__('Starts on')" col="col-md-3" />
                <x-form.input name="ends_on" type="date" :label="__('Ends on')" col="col-md-3" />
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100">{{ __('Add term') }}</button></div>
            </form>
        </div>
    </div>
@endif
@endsection
