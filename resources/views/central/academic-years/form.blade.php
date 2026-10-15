@extends('layouts.central')
@section('title', $year->exists ? __('Edit academic year') : __('New academic year'))

@section('content')
<x-page-header :title="$year->exists ? __('Edit academic year') : __('New academic year')"
    :breadcrumbs="[__('Academic years') => route('central.academic-years.index'), ($year->exists ? $year->name : __('New')) => null]" />

<form method="POST" action="{{ $year->exists ? route('central.academic-years.update', $year) : route('central.academic-years.store') }}" class="card">
    @csrf
    @if($year->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        @if($institutions->isNotEmpty())
            <x-form.select name="institution_id" :label="__('Institution')" :options="$institutions" required col="col-md-4" />
        @endif
        <x-form.input name="name" :label="__('Name')" :value="$year->name" required col="col-md-2" maxlength="30" placeholder="2026" />
        <x-form.input name="starts_on" type="date" :label="__('Starts on')" :value="$year->starts_on?->toDateString()" required col="col-md-3" />
        <x-form.input name="ends_on" type="date" :label="__('Ends on')" :value="$year->ends_on?->toDateString()" required col="col-md-3" />
        <x-form.switch name="is_current" :label="__('Current academic year')" :checked="$year->is_current" />
    </div>
    <x-form.actions :cancel="route('central.academic-years.index')" />
</form>
@endsection
