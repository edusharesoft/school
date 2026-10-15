@extends('layouts.branch')
@section('title', __('Monthly attendance sheet'))

@section('content')
<x-page-header :title="__('Monthly attendance sheet')" :breadcrumbs="[__('Attendance') => route('branch.attendance.students.index'), __('Monthly sheets') => null]">
    <x-slot:actions>
        @if($section)
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i>{{ __('Export CSV') }}</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
        @endif
    </x-slot:actions>
</x-page-header>

@include('branch.attendance.reports._tabs')

<form method="GET" class="panel panel-body mb-4 d-flex flex-wrap align-items-end gap-2 no-print">
    <div><label class="form-label small mb-1" for="month">{{ __('Month') }}</label>
        <input type="month" id="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm"></div>
    <div><label class="form-label small mb-1" for="section_id">{{ __('Class & section') }}</label>
        <select id="section_id" name="section_id" class="form-select form-select-sm" required>
            <option value="">—</option>
            @foreach($sections as $s)<option value="{{ $s->id }}" @selected($section?->id === $s->id)>{{ $s->label() }}</option>@endforeach
        </select></div>
    <button class="btn btn-brand btn-sm"><i class="bi bi-table me-1"></i>{{ __('Show sheet') }}</button>
</form>

<div class="card">
    @if(! $section)
        <x-empty-state icon="table" :title="__('Choose a class & section')" :message="__('The sheet shows every day of the month with totals and percentage per student.')" />
    @elseif($sheet['rows']->isEmpty())
        <x-empty-state icon="people" :title="__('No students in this section for that month')" />
    @else
        <div class="card-header bg-transparent"><strong>{{ $section->label() }}</strong> · {{ $month->translatedFormat('F Y') }} · {{ $currentBranch->name }}</div>
        @include('branch.attendance.reports._sheet', [
            'firstHead' => __('Roll').' / '.__('Student'),
            'label' => fn ($row) => '<strong>'.e(bn_digits($row['enrollment']->roll_no)).'</strong> '.e($row['enrollment']->student?->name),
        ])
    @endif
</div>
@endsection
