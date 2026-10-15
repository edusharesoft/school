@extends('layouts.branch')
@section('title', __('Grading scales'))

@section('content')
<x-page-header :title="__('Grading scales')" :breadcrumbs="[__('Examinations') => route('branch.exams.index'), __('Grading scales') => null]">
    <x-slot:actions>
        @can('examinations.create')
            <a href="{{ route('branch.exams.grading.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New scale') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    @foreach($scales as $scale)
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-semibold">{{ $scale->name }}</span>
                        @if($scale->is_default)<span class="badge text-bg-primary ms-1">{{ __('Default') }}</span>@endif
                    </div>
                    @can('examinations.update')
                        <a href="{{ route('branch.exams.grading.edit', $scale) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 small">
                        <thead class="table-light"><tr><th>{{ __('Grade') }}</th><th>{{ __('Marks (%)') }}</th><th>{{ __('Point') }}</th><th>{{ __('Remark') }}</th></tr></thead>
                        <tbody>
                        @foreach($scale->grades as $g)
                            <tr @class(['table-danger' => (float) $g['point'] === 0.0])>
                                <td class="fw-semibold">{{ $g['grade'] }}</td>
                                <td>{{ (float) $g['min'] }} – {{ (float) $g['max'] }}</td>
                                <td>{{ number_format((float) $g['point'], 2) }}</td>
                                <td>{{ __($g['remark'] ?? '') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-transparent small text-body-secondary">
                    {{ __('Max GPA :max · optional subject: points above :base are added · :decimals decimals (:rounding)', [
                        'max' => number_format($scale->max_gpa, 2), 'base' => number_format($scale->optional_base_point, 2),
                        'decimals' => $scale->gpa_decimals, 'rounding' => __($scale->rounding === 'floor' ? 'truncated' : 'rounded'),
                    ]) }}
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
