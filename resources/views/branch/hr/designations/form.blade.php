@extends('layouts.branch')
@section('title', $designation->exists ? __('Edit designation') : __('New designation'))

@section('content')
<x-page-header :title="$designation->exists ? __('Edit designation') : __('New designation')"
    :breadcrumbs="[__('Designations') => route('branch.hr.designations.index'), ($designation->exists ? $designation->name : __('New')) => null]" />

<form method="POST" action="{{ $designation->exists ? route('branch.hr.designations.update', $designation) : route('branch.hr.designations.store') }}" class="card">
    @csrf
    @if($designation->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Name')" :value="$designation->name" required col="col-md-5" maxlength="100" />
        <x-form.input name="name_bn" :label="__('Name (Bangla)')" :value="$designation->name_bn" col="col-md-5" class="bn" maxlength="100" />
        <x-form.input name="code" :label="__('Code')" :value="$designation->code" required col="col-md-2" class="text-uppercase" maxlength="30" />
        <x-form.select name="category" :label="__('Category')" :value="$designation->category" required col="col-md-4"
            :options="['teaching' => __('Teaching'), 'non_teaching' => __('Non-teaching')]" />
        <x-form.input name="rank" type="number" :label="__('Rank')" :value="$designation->rank" required col="col-md-2" min="0" max="999"
            :help="__('Lower = more senior; used for ordering.')" />
        <x-form.switch name="is_active" :label="__('Active')" :checked="$designation->is_active" />
    </div>
    <x-form.actions :cancel="route('branch.hr.designations.index')" />
</form>
@endsection
