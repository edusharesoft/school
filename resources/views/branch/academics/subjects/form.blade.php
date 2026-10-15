@extends('layouts.branch')
@section('title', $item->exists ? __('Edit') : __('New subject'))

@section('content')
<x-page-header :title="$item->exists ? __('Edit').': '.$item->name : __('New subject')"
    :breadcrumbs="[__('Academics') => null, ($item->exists ? $item->name : __('New')) => null]" />

<form method="POST" action="{{ $item->exists ? route('branch.academics.subjects.update', $item) : route('branch.academics.subjects.store') }}" class="card">
    @csrf
    @if($item->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Name')" :value="$item->name" required col="col-md-5" maxlength="100" placeholder="Bangla 1st Paper" />
        <x-form.input name="name_bn" :label="__('Name (Bangla)')" :value="$item->name_bn" col="col-md-4" maxlength="100" class="bn" />
        <x-form.input name="code" :label="__('Code')" :value="$item->code" required col="col-md-3" maxlength="20" placeholder="101" />
        <x-form.select name="type" :label="__('Type')" :value="$item->type" required col="col-md-4"
            :options="['theory' => __('Theory'), 'practical' => __('Practical'), 'theory_practical' => __('Theory + Practical')]" />
        <x-form.switch name="is_active" :label="__('Active')" :checked="$item->is_active" />
    </div>
    <x-form.actions :cancel="route('branch.academics.subjects.index')" />
</form>
@endsection
