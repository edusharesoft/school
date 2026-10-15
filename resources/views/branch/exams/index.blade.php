@extends('layouts.branch')
@section('title', __('Examinations'))

@section('content')
<x-page-header :title="__('Examinations')" :breadcrumbs="[__('Examinations') => null]">
    <x-slot:actions>
        <a href="{{ route('branch.exams.grading.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-sliders me-1"></i>{{ __('Grading scales') }}</a>
        @can('examinations.create')
            <a href="{{ route('branch.exams.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New exam') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <select name="academic_session_id" class="form-select form-select-sm" aria-label="{{ __('Session') }}" onchange="this.form.submit()">
                    <option value="">{{ __('All sessions') }}</option>
                    @foreach($sessions as $id => $name)<option value="{{ $id }}" @selected((int) request('academic_session_id') === (int) $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
        </form>
    </div>
    @if($exams->isEmpty())
        <x-empty-state icon="journal-check" :title="__('No exams yet')" :message="__('Create an exam, add the classes that sit it and set the paper schedule.')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Exam') }}</th><th>{{ __('Session') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Papers') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($exams as $exam)
                    <tr>
                        <td><a href="{{ route('branch.exams.show', $exam) }}" class="fw-semibold text-decoration-none">{{ $exam->name }}</a>
                            <div class="small text-body-secondary">{{ __(ucfirst(str_replace('_', ' ', $exam->type))) }}@if($exam->term) · {{ $exam->term->name }}@endif</div></td>
                        <td>{{ $exam->session?->name }}</td>
                        <td class="text-nowrap small">{{ $exam->starts_on?->format('d M Y') ?? '—' }}@if($exam->ends_on) – {{ $exam->ends_on->format('d M Y') }}@endif</td>
                        <td>{{ $exam->subjects_count }}</td>
                        <td><span class="badge text-bg-{{ $exam->tone() }} {{ $exam->tone() === 'light' ? 'border' : '' }}">{{ __(ucfirst(str_replace('_', ' ', $exam->status))) }}</span></td>
                        <td><x-row-actions :edit="route('branch.exams.edit', $exam)" :destroy="route('branch.exams.destroy', $exam)"
                                :can-edit="auth()->user()->can('examinations.update') && ! in_array($exam->status, ['approved', 'published'], true)"
                                :can-delete="auth()->user()->can('examinations.delete') && $exam->status === 'draft'">
                                <a href="{{ route('branch.exams.show', $exam) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Open') }}"><i class="bi bi-eye"></i></a>
                            </x-row-actions></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $exams->links() }}</div>
    @endif
</div>
@endsection
