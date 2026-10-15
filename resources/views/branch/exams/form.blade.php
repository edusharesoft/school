@extends('layouts.branch')
@section('title', $exam->exists ? __('Edit exam') : __('New exam'))

@section('content')
<x-page-header :title="$exam->exists ? __('Edit exam') : __('New exam')"
    :breadcrumbs="[__('Examinations') => route('branch.exams.index'), ($exam->exists ? $exam->name : __('New')) => null]" />

<form method="POST" action="{{ $exam->exists ? route('branch.exams.update', $exam) : route('branch.exams.store') }}" class="card">
    @csrf
    @if($exam->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Exam name')" :value="$exam->name" required col="col-md-6" :placeholder="__('e.g. Half-yearly examination 2026')" />
        <x-form.select name="type" :label="__('Type')" :value="$exam->type" required col="col-md-3"
            :options="collect(App\Models\Exams\Examination::TYPES)->mapWithKeys(fn ($t) => [$t => __(ucfirst(str_replace('_', ' ', $t)))])->all()" />
        <x-form.select name="academic_session_id" :label="__('Session')" :value="$exam->academic_session_id" :options="$sessions->all()" required col="col-md-3" />
        <x-form.select name="term_id" :label="__('Term')" :value="$exam->term_id" :placeholder="__('— None —')" col="col-md-3"
            :options="$terms->mapWithKeys(fn ($t) => [$t->id => $t->name])->all()" />
        <x-form.select name="grading_scale_id" :label="__('Grading scale')" :value="$exam->grading_scale_id" :options="$scales->all()" required col="col-md-3" />
        <x-form.input name="starts_on" type="date" :label="__('Starts on')" :value="$exam->starts_on?->toDateString()" col="col-md-2" />
        <x-form.input name="ends_on" type="date" :label="__('Ends on')" :value="$exam->ends_on?->toDateString()" col="col-md-2" />
        <x-form.input name="min_attendance" type="number" :label="__('Min. attendance %')" :value="$exam->min_attendance" min="0" max="100" col="col-md-2"
            :help="__('Needed for an admit card. Leave empty for no check.')" />
        <div class="col-12">
            <label class="form-label" for="instructions">{{ __('Instructions for candidates') }}</label>
            <textarea id="instructions" name="instructions" rows="4" @class(['form-control', 'is-invalid' => $errors->has('instructions')])>{{ old('instructions', $exam->instructions) }}</textarea>
            @error('instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('Printed on admit cards and the exam routine.') }}</div>
        </div>
    </div>
    <x-form.actions :cancel="$exam->exists ? route('branch.exams.show', $exam) : route('branch.exams.index')" />
</form>
@endsection
