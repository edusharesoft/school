@extends('layouts.branch')
@section('title', __('Offline application'))

@section('content')
<x-page-header :title="__('Offline application')" :breadcrumbs="[__('Applications') => route('branch.admissions.applications.index'), __('New') => null]" />

@if(! $campaign)
    <div class="card"><x-empty-state icon="calendar-x" :title="__('No open admission')" :message="__('Open an admission round first.')" /></div>
@else
    <form method="GET" class="panel panel-body mb-3 d-flex gap-2 align-items-end">
        <div><label class="form-label small mb-1" for="campaign_id">{{ __('Admission') }}</label>
            <select id="campaign_id" name="campaign_id" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($campaigns as $c)<option value="{{ $c->id }}" @selected($campaign->id === $c->id)>{{ $c->title }} ({{ $c->schoolClass?->name }})</option>@endforeach
            </select></div>
    </form>
    <form method="POST" action="{{ route('branch.admissions.applications.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="admission_campaign_id" value="{{ $campaign->id }}">
        @include('public.admission._fields')
        <div class="d-flex gap-2">
            <button class="btn btn-brand">{{ __('Save application') }}</button>
            <a href="{{ route('branch.admissions.applications.index', ['campaign_id' => $campaign->id]) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endif
@endsection
