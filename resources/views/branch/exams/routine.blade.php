@extends('layouts.branch')
@section('title', __('Exam routine').' — '.$exam->name)

@section('content')
<x-page-header :title="__('Exam routine')" :breadcrumbs="[__('Examinations') => route('branch.exams.index'), $exam->name => route('branch.exams.show', $exam), __('Routine') => null]">
    <x-slot:actions>
        <button type="button" class="btn btn-brand btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body text-center border-bottom">
        <div class="fs-5 fw-bold">{{ tenant()->branch()?->name }}</div>
        <div class="fw-semibold">{{ $exam->name }} — {{ $exam->session?->name }}</div>
        @if($exam->starts_on)<div class="small text-body-secondary">{{ $exam->starts_on->format('d M Y') }}@if($exam->ends_on) – {{ $exam->ends_on->format('d M Y') }}@endif</div>@endif
    </div>
    @forelse($papers as $classPapers)
        <div class="card-body pb-0"><h2 class="h6 fw-bold mb-2">{{ $classPapers->first()->schoolClass?->name }}</h2></div>
        <div class="table-responsive px-3">
            <table class="table table-bordered table-sm small mb-3">
                <thead class="table-light"><tr><th style="width:130px">{{ __('Date') }}</th><th style="width:110px">{{ __('Day') }}</th><th style="width:120px">{{ __('Time') }}</th><th>{{ __('Subject') }}</th><th style="width:90px">{{ __('Code') }}</th><th style="width:90px" class="text-center">{{ __('Full marks') }}</th></tr></thead>
                <tbody>
                @foreach($classPapers as $p)
                    <tr>
                        <td>{{ $p->exam_date?->format('d M Y') ?? __('Not set') }}</td>
                        <td>{{ $p->exam_date ? __($p->exam_date->format('l')) : '' }}</td>
                        <td class="text-nowrap">{{ $p->starts_at ? substr($p->starts_at, 0, 5) : '' }}@if($p->ends_at) – {{ substr($p->ends_at, 0, 5) }}@endif</td>
                        <td>{{ $p->subject?->name }}@if($p->studyGroup) <span class="text-body-secondary">({{ $p->studyGroup->name }})</span>@endif @if($p->is_optional)<span class="badge text-bg-light border">{{ __('4th') }}</span>@endif</td>
                        <td>{{ $p->subject?->code }}</td>
                        <td class="text-center">{{ (float) $p->full_marks }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <x-empty-state icon="calendar-x" :title="__('No papers yet')" />
    @endforelse
    @if($exam->instructions)
        <div class="card-body border-top small"><div class="fw-semibold mb-1">{{ __('Instructions for candidates') }}</div>{!! nl2br(e($exam->instructions)) !!}</div>
    @endif
</div>
@endsection
