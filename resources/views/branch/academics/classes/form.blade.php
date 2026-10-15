@extends('layouts.branch')
@section('title', $class->exists ? __('Edit class') : __('New class'))

@section('content')
<x-page-header :title="$class->exists ? __('Edit class') : __('New class')"
    :breadcrumbs="[__('Classes') => route('branch.academics.classes.index'), ($class->exists ? $class->name : __('New')) => null]" />

<form method="POST" action="{{ $class->exists ? route('branch.academics.classes.update', $class) : route('branch.academics.classes.store') }}" class="card">
    @csrf
    @if($class->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Name')" :value="$class->name" required col="col-md-4" maxlength="60" placeholder="Six" />
        <x-form.input name="name_bn" :label="__('Name (Bangla)')" :value="$class->name_bn" col="col-md-4" maxlength="60" class="bn" placeholder="ষষ্ঠ" />
        <x-form.input name="code" :label="__('Code')" :value="$class->code" required col="col-md-4" maxlength="20" placeholder="VI" />
        <x-form.select name="level" :label="__('Level')" required col="col-md-4" :value="$class->level"
            :options="collect(App\Models\Academic\SchoolClass::LEVELS)->mapWithKeys(fn ($l) => [$l => App\Models\Academic\SchoolClass::levelLabel($l)])" />
        <x-form.input name="numeric_order" type="number" min="0" max="100" :label="__('Promotion order')" :value="$class->numeric_order" required col="col-md-4"
            :help="__('Students are promoted to the next higher number.')" />
        <div class="col-md-4 d-flex flex-column justify-content-center gap-2">
            <x-form.switch name="has_groups" :label="__('Uses groups (Science / Humanities / Business)')" :checked="$class->has_groups" col="" />
            <x-form.switch name="is_active" :label="__('Active')" :checked="$class->is_active" col="" />
        </div>
    </div>
    <x-form.actions :cancel="route('branch.academics.classes.index')" />
</form>
@endsection
