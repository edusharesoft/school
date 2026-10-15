@extends('layouts.branch')
@section('title', __('Results').' — '.$exam->name)

@php
    $statusLabel = fn ($s) => __(ucfirst(str_replace('_', ' ', $s)));
@endphp

@section('content')
<x-page-header :title="__('Results').' — '.$exam->name" :breadcrumbs="[__('Examinations') => route('branch.exams.index'), $exam->name => route('branch.exams.show', $exam), __('Results') => null]">
    <x-slot:actions>
        @if($classId)
            <a href="{{ route('branch.exams.results.tabulation', [$exam, 'class_id' => $classId, 'section_id' => $sectionId]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-table me-1"></i>{{ __('Tabulation sheet') }}</a>
        @endif
        @if($sectionId)
            <a href="{{ route('branch.exams.results.marksheets', [$exam, 'section_id' => $sectionId]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>{{ __('Mark sheets (section)') }}</a>
            <a href="{{ route('branch.exams.results.marksheets.pdf', [$exam, 'section_id' => $sectionId]) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>{{ __('PDF') }}</a>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div>
            <div class="small text-body-secondary">{{ __('Status') }}</div>
            <span class="badge fs-6 text-bg-{{ $exam->tone() }} {{ $exam->tone() === 'light' ? 'border' : '' }}">{{ $statusLabel($exam->status) }}</span>
            @if($exam->approved_at)<div class="small text-body-secondary mt-1">{{ __('Approved by :name on :date', ['name' => $exam->approver?->name, 'date' => $exam->approved_at->format('d M Y H:i')]) }}</div>@endif
            @if($exam->published_at)<div class="small text-body-secondary">{{ __('Published on :date', ['date' => $exam->published_at->format('d M Y H:i')]) }}</div>@endif
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            @if(in_array($exam->status, ['marks_entry', 'processing'], true))
                @can('results.process')
                    <form method="POST" action="{{ route('branch.exams.results.process', $exam) }}" data-confirm="{{ __('Process results from the current marks?') }}">@csrf
                        <button class="btn btn-brand btn-sm"><i class="bi bi-cpu me-1"></i>{{ $exam->status === 'processing' ? __('Process again') : __('Process results') }}</button></form>
                @endcan
            @endif
            @if($exam->status === 'processing')
                @can('results.process')
                    <form method="POST" action="{{ route('branch.exams.results.reopen', $exam) }}" data-confirm="{{ __('Reopen marks entry? Processed results are discarded.') }}">@csrf
                        <button class="btn btn-outline-secondary btn-sm">{{ __('Reopen marks entry') }}</button></form>
                @endcan
                @can('results.approve')
                    <form method="POST" action="{{ route('branch.exams.results.approve', $exam) }}" data-confirm="{{ __('Approve these results?') }}">@csrf
                        <button class="btn btn-primary btn-sm"><i class="bi bi-patch-check me-1"></i>{{ __('Approve results') }}</button></form>
                @endcan
            @endif
            @if($exam->status === 'approved')
                @can('results.publish')
                    <form method="POST" action="{{ route('branch.exams.results.publish', $exam) }}" data-confirm="{{ __('Publish results to students, parents and the public result page?') }}">@csrf
                        <button class="btn btn-success btn-sm"><i class="bi bi-megaphone me-1"></i>{{ __('Publish results') }}</button></form>
                @endcan
            @endif
            @if($exam->status === 'published')
                @can('results.publish')
                    <form method="POST" action="{{ route('branch.exams.results.unpublish', $exam) }}" data-confirm="{{ __('Withdraw the results from publication?') }}">@csrf
                        <button class="btn btn-outline-danger btn-sm">{{ __('Unpublish') }}</button></form>
                @endcan
            @endif
        </div>
    </div>
    @error('status')<div class="alert alert-danger m-3 mt-0 small">{{ $message }}</div>@enderror
</div>

@if($results->isEmpty() && $classes->isEmpty())
    <div class="card"><x-empty-state icon="clipboard-data" :title="__('No results yet')" :message="__('Enter marks, then process the results. Processing needs the exam to be in marks entry.')" /></div>
@else
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><x-stat-card :label="__('Students')" :value="$summary['students']" icon="fa-solid fa-users" color="#4f46e5" bg="#e0e7ff" /></div>
        <div class="col-6 col-md-3"><x-stat-card :label="__('Pass rate')" :value="$summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '—'" icon="fa-solid fa-circle-check" color="#15803d" bg="#dcfce7" /></div>
        <div class="col-6 col-md-3"><x-stat-card :label="__('Failed')" :value="$summary['fail']" icon="fa-solid fa-circle-xmark" color="#b91c1c" bg="#fee2e2" /></div>
        <div class="col-6 col-md-3"><x-stat-card :label="__('Incomplete')" :value="$summary['incomplete']" icon="fa-solid fa-hourglass-half" color="#b45309" bg="#fef3c7" /></div>
    </div>

    <div class="card">
        <div class="card-header bg-transparent d-flex flex-wrap align-items-center gap-2">
            <ul class="nav nav-pills small me-auto">
                @foreach($classes as $c)
                    <li class="nav-item"><a @class(['nav-link py-1', 'active' => $c->id === $classId]) href="{{ route('branch.exams.results.index', [$exam, 'class_id' => $c->id]) }}">{{ $c->name }}</a></li>
                @endforeach
            </ul>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="class_id" value="{{ $classId }}">
                <select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Section') }}">
                    <option value="">{{ __('All sections') }}</option>
                    @foreach($sections as $s)<option value="{{ $s->id }}" @selected($sectionId === $s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="card-body border-bottom small d-flex flex-wrap gap-4">
            <span>{{ __('Passed') }}: <strong>{{ $classSummary['pass'] }}</strong></span>
            <span>{{ __('Failed') }}: <strong>{{ $classSummary['fail'] }}</strong></span>
            <span>{{ __('Pass rate') }}: <strong>{{ $classSummary['pass_rate'] !== null ? $classSummary['pass_rate'].'%' : '—' }}</strong></span>
            <span>{{ __('Average GPA') }}: <strong>{{ $classSummary['avg_gpa'] ?? '—' }}</strong></span>
            <span class="ms-md-auto">@foreach($grades as $g => $n)<span class="badge text-bg-light border me-1">{{ $g }}: {{ $n }}</span>@endforeach</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Merit') }}</th><th>{{ __('Roll') }}</th><th>{{ __('Student') }}</th><th>{{ __('Section') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-center">{{ __('GPA') }}</th><th class="text-center">{{ __('Grade') }}</th><th>{{ __('Result') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($results as $r)
                    <tr>
                        <td class="fw-semibold">{{ $r->class_position ?? '—' }}<span class="small text-body-secondary">@if($r->section_position) / {{ $r->section_position }}@endif</span></td>
                        <td>{{ $r->roll_no }}</td>
                        <td><div class="fw-semibold">{{ $r->student?->name }}</div><div class="small text-body-secondary">{{ $r->student?->student_code }}</div></td>
                        <td>{{ $r->section?->name }}</td>
                        <td class="text-end">{{ (float) $r->total_marks }} <span class="small text-body-secondary">/ {{ (float) $r->full_marks }}</span></td>
                        <td class="text-center fw-semibold">{{ $r->gpaLabel() }}</td>
                        <td class="text-center">{{ $r->grade ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $r->tone() }}">{{ __(ucfirst($r->status)) }}</span>
                            @if($r->status !== 'pass')<div class="small text-body-secondary">{{ $r->remarks }}</div>@endif</td>
                        <td class="text-end"><a href="{{ route('branch.exams.results.marksheet', [$exam, $r]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" aria-label="{{ __('Mark sheet') }}"><i class="bi bi-file-earmark-text"></i></a> <a href="{{ route('branch.exams.results.marksheet.pdf', [$exam, $r]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" aria-label="{{ __('PDF') }}"><i class="bi bi-file-earmark-pdf"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent small text-body-secondary">{{ __('Merit: position in class / in section. Passed students are ranked first by GPA, then total marks.') }}</div>
    </div>
@endif
@endsection
