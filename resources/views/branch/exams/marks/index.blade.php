@extends($layout)
@section('title', __('Marks entry'))

@section('content')
<x-page-header :title="__('Marks entry')" :breadcrumbs="$routePrefix === 'branch.' ? [__('Examinations') => route('branch.exams.index'), __('Marks entry') => null] : []">
    <x-slot:actions>
        <a href="{{ route($routePrefix.'exams.corrections.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square me-1"></i>{{ __('Correction requests') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small" for="f_exam">{{ __('Exam') }}</label>
                <select id="f_exam" name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($exams as $e)<option value="{{ $e->id }}" @selected($exam?->id === $e->id)>{{ $e->name }} — {{ __(ucfirst(str_replace('_', ' ', $e->status))) }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary">{{ __('Show') }}</button></div>
        </form>
    </div>
</div>

@if(! $exam)
    <div class="card"><x-empty-state icon="journal-check" :title="__('No exam is open for marks')" :message="__('An exam appears here once it is scheduled.')" /></div>
@else
    @if(! $exam->acceptsMarks())
        <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>{{ __('Marks entry for this exam is closed (:status). Open a sheet to request a correction.', ['status' => __(ucfirst(str_replace('_', ' ', $exam->status)))]) }}</div>
    @endif
    <div class="card">
        @if($rows->isEmpty())
            <x-empty-state icon="person-video3" :title="__('No papers assigned to you')" :message="__('Teachers see the subjects and sections they teach in the class routine of this session.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>{{ __('Class') }}</th><th>{{ __('Section') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Date') }}</th><th style="width:220px">{{ __('Progress') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach($rows as $row)
                        @php($p = $row['progress'])
                        @php($pct = $p['expected'] ? min(100, round($p['entered'] / $p['expected'] * 100)) : 0)
                        <tr>
                            <td>{{ $row['paper']->schoolClass?->name }}</td>
                            <td>{{ $row['section']->name }}</td>
                            <td class="fw-semibold">{{ $row['paper']->subject?->name }}@if($row['paper']->studyGroup) <span class="small text-body-secondary">({{ $row['paper']->studyGroup->name }})</span>@endif</td>
                            <td class="small text-nowrap">{{ $row['paper']->exam_date?->format('d M Y') ?? '—' }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:8px" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : 'bg-warning' }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="small text-nowrap">{{ $p['entered'] }}/{{ $p['expected'] }}</span>
                                </div>
                            </td>
                            <td class="text-end">
                                <a href="{{ route($routePrefix.'exams.marks.sheet', [$exam, $row['paper'], $row['section']]) }}" class="btn btn-sm {{ $row['mine'] && $exam->acceptsMarks() ? 'btn-brand' : 'btn-outline-secondary' }}">
                                    {{ $row['mine'] && $exam->acceptsMarks() ? __('Enter marks') : __('Open') }}</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
@endsection
