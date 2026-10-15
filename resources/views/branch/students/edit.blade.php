@extends('layouts.branch')
@section('title', __('Edit student'))

@section('content')
<x-page-header :title="__('Edit student: :name', ['name' => $student->name])"
    :breadcrumbs="[__('Students') => route('branch.students.index'), $student->student_code => route('branch.students.show', $student), __('Edit') => null]" />

<form method="POST" action="{{ route('branch.students.update', $student) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('branch.students.partials.profile-fields')
    <div class="d-flex gap-2">
        <button class="btn btn-brand" type="submit">{{ __('Save changes') }}</button>
        <a href="{{ route('branch.students.show', $student) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
    <p class="small text-body-secondary mt-2">{{ __('Class, section and roll are changed through promotion so that history is kept.') }}</p>
</form>
@endsection
