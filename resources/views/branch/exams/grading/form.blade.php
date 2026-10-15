@extends('layouts.branch')
@section('title', $scale->exists ? __('Edit grading scale') : __('New grading scale'))

@php
    $source = (array) old('grades', array_values($scale->grades ?? []));
    $count = max(count($source) + 2, 9, $source ? max(array_keys($source)) + 1 : 0);
    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $rows[$i] = $source[$i] ?? ['grade' => '', 'min' => '', 'max' => '', 'point' => '', 'remark' => ''];
    }
@endphp

@section('content')
<x-page-header :title="$scale->exists ? __('Edit grading scale') : __('New grading scale')"
    :breadcrumbs="[__('Examinations') => route('branch.exams.index'), __('Grading scales') => route('branch.exams.grading.index'), ($scale->exists ? $scale->name : __('New')) => null]" />

<form method="POST" action="{{ $scale->exists ? route('branch.exams.grading.update', $scale) : route('branch.exams.grading.store') }}" class="card">
    @csrf
    @if($scale->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Name')" :value="$scale->name" required col="col-md-6" maxlength="100" />
        <x-form.input name="max_gpa" type="number" step="0.01" :label="__('Maximum GPA')" :value="$scale->max_gpa" required col="col-md-2" />
        <x-form.input name="optional_base_point" type="number" step="0.01" :label="__('4th subject base point')" :value="$scale->optional_base_point" required col="col-md-2"
            :help="__('Only points above this are added to the GPA.')" />
        <x-form.input name="gpa_decimals" type="number" :label="__('GPA decimals')" :value="$scale->gpa_decimals" required min="0" max="4" col="col-md-2" />
        <x-form.select name="rounding" :label="__('GPA rounding')" :value="$scale->rounding" required col="col-md-3"
            :options="['round' => __('Round'), 'floor' => __('Truncate (floor)')]" />
        <x-form.switch name="is_default" :label="__('Default scale for new exams')" :checked="$scale->is_default" col="col-md-9 d-flex align-items-end" />

        <div class="col-12">
            <div class="fw-semibold mb-2">{{ __('Grade bands (percentage of full marks)') }}</div>
            @error('grades')<div class="alert alert-danger small py-2">{{ $message }}</div>@enderror
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-1">
                    <thead class="table-light"><tr><th>{{ __('Grade') }}</th><th>{{ __('From %') }}</th><th>{{ __('To %') }}</th><th>{{ __('Grade point') }}</th><th>{{ __('Remark') }}</th></tr></thead>
                    <tbody>
                    @foreach($rows as $i => $g)
                        <tr>
                            <td><input name="grades[{{ $i }}][grade]" value="{{ $g['grade'] ?? '' }}" maxlength="5" @class(['form-control form-control-sm', 'is-invalid' => $errors->has("grades.$i.grade")]) aria-label="{{ __('Grade') }}"></td>
                            <td><input name="grades[{{ $i }}][min]" type="number" step="0.01" min="0" max="100" value="{{ $g['min'] ?? '' }}" @class(['form-control form-control-sm', 'is-invalid' => $errors->has("grades.$i.min")]) aria-label="{{ __('From %') }}"></td>
                            <td><input name="grades[{{ $i }}][max]" type="number" step="0.01" min="0" max="100" value="{{ $g['max'] ?? '' }}" @class(['form-control form-control-sm', 'is-invalid' => $errors->has("grades.$i.max")]) aria-label="{{ __('To %') }}"></td>
                            <td><input name="grades[{{ $i }}][point]" type="number" step="0.01" min="0" max="10" value="{{ $g['point'] ?? '' }}" @class(['form-control form-control-sm', 'is-invalid' => $errors->has("grades.$i.point")]) aria-label="{{ __('Grade point') }}"></td>
                            <td><input name="grades[{{ $i }}][remark]" value="{{ $g['remark'] ?? '' }}" maxlength="40" class="form-control form-control-sm" aria-label="{{ __('Remark') }}"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-text">{{ __('Fill one row per grade; empty rows are ignored. One band must have point 0 (fail) and the lowest band must start at 0 %.') }}</div>
        </div>
    </div>
    <x-form.actions :cancel="route('branch.exams.grading.index')" />
</form>
@endsection
