@extends('layouts.branch')
@section('title', $exam->name)

@php
    $editable = auth()->user()->can('examinations.update') && ! in_array($exam->status, ['approved', 'published'], true);
    $removable = $editable && in_array($exam->status, ['draft', 'scheduled'], true);
    $statusLabel = fn ($s) => __(ucfirst(str_replace('_', ' ', $s)));
    $componentLabels = ['theory' => __('Theory'), 'practical' => __('Practical'), 'ca' => __('CA')];
@endphp

@section('content')
<x-page-header :title="$exam->name" :breadcrumbs="[__('Examinations') => route('branch.exams.index'), $exam->name => null]">
    <x-slot:actions>
        <a href="{{ route('branch.exams.routine', $exam) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>{{ __('Exam routine') }}</a>
        @if($exam->status !== 'draft')
            <a href="{{ route('branch.exams.admit-cards', $exam) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-badge me-1"></i>{{ __('Admit cards') }}</a>
            <a href="{{ route('branch.exams.seat-plan', $exam) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-grid-3x3 me-1"></i>{{ __('Seat plan') }}</a>
            @if(auth()->user()->can('marks.enter') || auth()->user()->can('marks.view'))
                <a href="{{ route('branch.exams.marks.index', ['exam_id' => $exam->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square me-1"></i>{{ __('Marks entry') }}</a>
            @endif
            @can('results.view')
                <a href="{{ route('branch.exams.results.index', $exam) }}" class="btn btn-brand btn-sm"><i class="bi bi-clipboard-data me-1"></i>{{ __('Results') }}</a>
            @endcan
        @endif
        @if($editable)
            <a href="{{ route('branch.exams.edit', $exam) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-sm-4"><div class="text-body-secondary">{{ __('Session') }}</div><div class="fw-semibold">{{ $exam->session?->name }}@if($exam->term) · {{ $exam->term->name }}@endif</div></div>
                    <div class="col-sm-4"><div class="text-body-secondary">{{ __('Type') }}</div><div class="fw-semibold">{{ $statusLabel($exam->type) }}</div></div>
                    <div class="col-sm-4"><div class="text-body-secondary">{{ __('Dates') }}</div><div class="fw-semibold">{{ $exam->starts_on?->format('d M Y') ?? '—' }}@if($exam->ends_on) – {{ $exam->ends_on->format('d M Y') }}@endif</div></div>
                    <div class="col-sm-4"><div class="text-body-secondary">{{ __('Grading scale') }}</div><div class="fw-semibold">{{ $exam->gradingScale?->name }}</div></div>
                    <div class="col-sm-4"><div class="text-body-secondary">{{ __('Min. attendance %') }}</div><div class="fw-semibold">{{ $exam->min_attendance !== null ? $exam->min_attendance.'%' : __('No check') }}</div></div>
                    <div class="col-sm-4"><div class="text-body-secondary">{{ __('Classes') }}</div><div class="fw-semibold">{{ $classes->count() }}</div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-body-secondary small mb-1">{{ __('Status') }}</div>
                <div class="mb-3"><span class="badge fs-6 text-bg-{{ $exam->tone() }} {{ $exam->tone() === 'light' ? 'border' : '' }}">{{ $statusLabel($exam->status) }}</span></div>
                <ol class="small text-body-secondary ps-3 mb-3">
                    @foreach(App\Models\Exams\Examination::STATUSES as $s)
                        <li @class(['fw-semibold text-body' => $s === $exam->status])>{{ $statusLabel($s) }}</li>
                    @endforeach
                </ol>
                @can('examinations.update')
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($transitions as $to)
                            <form method="POST" action="{{ route('branch.exams.status', $exam) }}" data-confirm="{{ __('Change the exam status to :status?', ['status' => $statusLabel($to)]) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $to }}">
                                <button class="btn btn-sm {{ array_search($to, App\Models\Exams\Examination::STATUSES) > array_search($exam->status, App\Models\Exams\Examination::STATUSES) ? 'btn-brand' : 'btn-outline-secondary' }}">
                                    <i class="bi bi-arrow-right-circle me-1"></i>{{ $statusLabel($to) }}</button>
                            </form>
                        @endforeach
                    </div>
                    @error('status')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                @endcan
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
        <ul class="nav nav-pills small">
            @forelse($classes as $c)
                <li class="nav-item"><a @class(['nav-link py-1', 'active' => $c->id === $classId]) href="{{ route('branch.exams.show', [$exam, 'class_id' => $c->id]) }}">{{ $c->name }}</a></li>
            @empty
                <li class="nav-item text-body-secondary">{{ __('No classes added yet.') }}</li>
            @endforelse
        </ul>
        @if($editable && $allClasses->isNotEmpty())
            <form method="POST" action="{{ route('branch.exams.classes.add', $exam) }}" class="d-flex gap-2">
                @csrf
                <select name="school_class_id" class="form-select form-select-sm @error('school_class_id') is-invalid @enderror" aria-label="{{ __('Class') }}" required>
                    @foreach($allClasses as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
                <button class="btn btn-brand btn-sm text-nowrap"><i class="bi bi-plus-lg me-1"></i>{{ __('Add class') }}</button>
            </form>
        @endif
    </div>
    @error('school_class_id')<div class="alert alert-danger m-3 mb-0 small">{{ $message }}</div>@enderror

    @if($papers->isEmpty())
        <x-empty-state icon="journal-plus" :title="__('No papers yet')" :message="__('Add a class: every subject of the class becomes a paper with default marks you can adjust.')" />
    @else
        @if($errors->hasAny(['rows', 'rows.*']) || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'rows.')))
            <div class="alert alert-danger m-3 mb-0 small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" action="{{ route('branch.exams.classes.update', $exam) }}" id="papers-form">
            @csrf @method('PUT')
            <input type="hidden" name="school_class_id" value="{{ $classId }}">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2">{{ __('Subject') }}</th>
                            <th rowspan="2">{{ __('Date') }}</th>
                            <th rowspan="2">{{ __('Time') }}</th>
                            @foreach($componentLabels as $label)<th colspan="2" class="text-center">{{ $label }}</th>@endforeach
                            <th rowspan="2" class="text-center">{{ __('Total') }}</th>
                            <th rowspan="2" class="text-center">{{ __('Pass (total)') }}</th>
                            <th rowspan="2" class="text-center" title="{{ __('Optional (4th) subject') }}">{{ __('4th') }}</th>
                            <th rowspan="2" class="text-center" title="{{ __('Counts in GPA') }}">{{ __('GPA') }}</th>
                            <th rowspan="2"></th>
                        </tr>
                        <tr>@foreach($componentLabels as $label)<th class="text-center fw-normal">{{ __('Full') }}</th><th class="text-center fw-normal">{{ __('Pass') }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                    @foreach($papers as $paper)
                        @php($r = "rows.{$paper->id}")
                        <tr>
                            <td class="text-nowrap"><div class="fw-semibold">{{ $paper->subject?->name }}</div>
                                <div class="text-body-secondary">{{ $paper->subject?->code }}@if($paper->studyGroup) · {{ $paper->studyGroup->name }}@endif</div></td>
                            <td><input type="date" name="rows[{{ $paper->id }}][exam_date]" value="{{ old("$r.exam_date", $paper->exam_date?->toDateString()) }}"
                                    @class(['form-control form-control-sm', 'is-invalid' => $errors->has("$r.exam_date")]) @disabled(! $editable) style="min-width:130px" aria-label="{{ __('Date') }}"></td>
                            <td class="text-nowrap">
                                <input type="time" name="rows[{{ $paper->id }}][starts_at]" value="{{ old("$r.starts_at", $paper->starts_at ? substr($paper->starts_at, 0, 5) : '') }}"
                                    @class(['form-control form-control-sm d-inline-block', 'is-invalid' => $errors->has("$r.starts_at")]) style="width:96px" @disabled(! $editable) aria-label="{{ __('Starts at') }}">
                                <input type="time" name="rows[{{ $paper->id }}][ends_at]" value="{{ old("$r.ends_at", $paper->ends_at ? substr($paper->ends_at, 0, 5) : '') }}"
                                    class="form-control form-control-sm d-inline-block" style="width:96px" @disabled(! $editable) aria-label="{{ __('Ends at') }}">
                            </td>
                            @foreach(array_keys($componentLabels) as $comp)
                                @foreach(['full', 'pass'] as $f)
                                    <td><input type="number" step="0.5" min="0" max="1000" name="rows[{{ $paper->id }}][components][{{ $comp }}][{{ $f }}]"
                                            value="{{ old("$r.components.$comp.$f", isset($paper->components[$comp]) ? (float) $paper->components[$comp][$f] : '') }}"
                                            @class(['form-control form-control-sm text-end', 'is-invalid' => $errors->has("$r.components.$comp.$f")]) style="width:64px" @disabled(! $editable)
                                            aria-label="{{ $componentLabels[$comp] }} {{ $f }}"></td>
                                @endforeach
                            @endforeach
                            <td class="text-center fw-semibold">{{ (float) $paper->full_marks }}</td>
                            <td><input type="number" step="0.5" min="0" name="rows[{{ $paper->id }}][pass_marks]" value="{{ old("$r.pass_marks", (float) $paper->pass_marks) }}"
                                    @class(['form-control form-control-sm text-end', 'is-invalid' => $errors->has("$r.pass_marks")]) style="width:64px" @disabled(! $editable) aria-label="{{ __('Pass (total)') }}"></td>
                            <td class="text-center"><input type="checkbox" class="form-check-input" name="rows[{{ $paper->id }}][is_optional]" value="1" @checked(old("$r.is_optional", $paper->is_optional)) @disabled(! $editable) aria-label="{{ __('Optional (4th) subject') }}"></td>
                            <td class="text-center"><input type="checkbox" class="form-check-input" name="rows[{{ $paper->id }}][counts_in_gpa]" value="1" @checked(old("$r.counts_in_gpa", $paper->counts_in_gpa)) @disabled(! $editable) aria-label="{{ __('Counts in GPA') }}"></td>
                            <td class="text-end">
                                @if($removable)
                                    <button type="submit" form="remove-paper-{{ $paper->id }}" class="btn btn-sm btn-outline-danger" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($editable)
                <div class="card-footer bg-transparent d-flex flex-wrap gap-2 align-items-center">
                    <button class="btn btn-brand" type="submit"><i class="bi bi-check2 me-1"></i>{{ __('Save papers & schedule') }}</button>
                    <span class="text-body-secondary small">{{ __('A component with pass marks 0 has no separate pass requirement. Leave full marks empty to drop a component.') }}</span>
                </div>
            @endif
        </form>
        @if($removable)
            @foreach($papers as $paper)
                <form method="POST" action="{{ route('branch.exams.papers.destroy', [$exam, $paper]) }}" id="remove-paper-{{ $paper->id }}" class="d-none" data-confirm="{{ __('Remove this paper from the exam?') }}">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        @endif
    @endif
</div>
@endsection
