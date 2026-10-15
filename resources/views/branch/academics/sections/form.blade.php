@extends('layouts.branch')
@section('title', $section->exists ? __('Edit section') : __('New section'))

@section('content')
<x-page-header :title="$section->exists ? __('Edit section') : __('New section')"
    :breadcrumbs="[__('Classes') => route('branch.academics.classes.index'), ($section->exists ? $section->name : __('New section')) => null]">
    @if($section->exists)
        <x-slot:actions>
            @can('delete', $section)
                <form method="POST" action="{{ route('branch.academics.sections.destroy', $section) }}" data-confirm="{{ __('Delete this section?') }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>{{ __('Delete') }}</button>
                </form>
            @endcan
        </x-slot:actions>
    @endif
</x-page-header>

<form method="POST" action="{{ $section->exists ? route('branch.academics.sections.update', $section) : route('branch.academics.sections.store') }}" class="card">
    @csrf
    @if($section->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.select name="school_class_id" :label="__('Class')" :options="$classes" :value="$section->school_class_id" required col="col-md-4" :placeholder="__('Choose…')" />
        <x-form.input name="name" :label="__('Section name')" :value="$section->name" required col="col-md-4" maxlength="40" placeholder="A" />
        <x-form.select name="shift" :label="__('Shift')" :value="$section->shift" col="col-md-4" :placeholder="__('—')"
            :options="['morning' => __('Morning'), 'day' => __('Day'), 'evening' => __('Evening')]" />
        <x-form.input name="capacity" type="number" min="1" max="500" :label="__('Capacity')" :value="$section->capacity" required col="col-md-4" />
        <x-form.select name="class_teacher_id" :label="__('Class teacher')" :options="$teachers" :value="$section->class_teacher_id" col="col-md-4" :placeholder="__('Not assigned')" />
        <x-form.select name="room_id" :label="__('Room')" :options="$rooms" :value="$section->room_id" col="col-md-4" :placeholder="__('Not assigned')" />
        <x-form.switch name="is_active" :label="__('Active')" :checked="$section->is_active" />
    </div>
    <x-form.actions :cancel="route('branch.academics.classes.index')" />
</form>
@endsection
