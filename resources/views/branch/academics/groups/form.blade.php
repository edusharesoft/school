@extends('layouts.branch')
@section('title', $item->exists ? __('Edit') : __('New group'))

@section('content')
<x-page-header :title="$item->exists ? __('Edit').': '.$item->name : __('New group')"
    :breadcrumbs="[__('Academics') => null, ($item->exists ? $item->name : __('New')) => null]" />

<form method="POST" action="{{ $item->exists ? route('branch.academics.groups.update', $item) : route('branch.academics.groups.store') }}" class="card">
    @csrf
    @if($item->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Name')" :value="$item->name" required col="col-md-5" maxlength="60" placeholder="Science" />
        <x-form.input name="name_bn" :label="__('Name (Bangla)')" :value="$item->name_bn" col="col-md-4" maxlength="60" class="bn" placeholder="বিজ্ঞান" />
        <x-form.input name="code" :label="__('Code')" :value="$item->code" required col="col-md-3" maxlength="20" placeholder="SCI" />
        <x-form.switch name="is_active" :label="__('Active')" :checked="$item->is_active" />
    </div>
    <x-form.actions :cancel="route('branch.academics.groups.index')" />
</form>
@endsection
