@extends('layouts.branch')
@section('title', $item->exists ? __('Edit') : __('New room'))

@section('content')
<x-page-header :title="$item->exists ? __('Edit').': '.$item->name : __('New room')"
    :breadcrumbs="[__('Academics') => null, ($item->exists ? $item->name : __('New')) => null]" />

<form method="POST" action="{{ $item->exists ? route('branch.academics.rooms.update', $item) : route('branch.academics.rooms.store') }}" class="card">
    @csrf
    @if($item->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Name')" :value="$item->name" required col="col-md-4" maxlength="60" placeholder="Room 101" />
        <x-form.input name="code" :label="__('Code')" :value="$item->code" required col="col-md-2" maxlength="20" placeholder="R101" />
        <x-form.input name="building" :label="__('Building')" :value="$item->building" col="col-md-3" maxlength="60" />
        <x-form.select name="type" :label="__('Type')" :value="$item->type" required col="col-md-3"
            :options="collect(App\Models\Academic\Room::TYPES)->mapWithKeys(fn ($t) => [$t => __(ucfirst($t))])" />
        <x-form.input name="capacity" type="number" min="1" max="2000" :label="__('Capacity')" :value="$item->capacity" required col="col-md-3" />
        <x-form.switch name="is_active" :label="__('Active')" :checked="$item->is_active" />
    </div>
    <x-form.actions :cancel="route('branch.academics.rooms.index')" />
</form>
@endsection
