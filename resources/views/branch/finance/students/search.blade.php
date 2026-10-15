@extends('layouts.branch')
@section('title', __('Collect fees'))

@section('content')
<x-page-header :title="__('Collect fees')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Collect fees') => null]" />

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2" role="search">
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="{{ __('Student ID, name, mobile or birth registration no.') }}" aria-label="{{ __('Search') }}" autofocus>
            <button class="btn btn-brand"><i class="bi bi-search me-1"></i>{{ __('Find') }}</button>
        </form>
    </div>
</div>

@if($q !== '')
    <div class="card">
        @if($students->isEmpty())
            <x-empty-state icon="person-x" :title="__('No student found')" />
        @else
            <div class="list-group list-group-flush">
                @foreach($students as $s)
                    <a href="{{ route('branch.finance.students.show', $s) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                        <span class="avatar">{{ $s->initials() }}</span>
                        <div class="me-auto">
                            <div class="fw-semibold">{{ $s->name }} <code class="small">{{ $s->student_code }}</code></div>
                            <div class="small text-body-secondary">{{ $s->currentEnrollment?->schoolClass?->name }} {{ $s->currentEnrollment?->section?->name }} · {{ __('Roll') }} {{ $s->currentEnrollment?->roll_no ?? '—' }}</div>
                        </div>
                        @php($due = (float) ($dues[$s->id] ?? 0))
                        <span class="badge {{ $due > 0 ? 'text-bg-danger' : 'text-bg-success' }}">{{ $due > 0 ? __('Due ৳:amount', ['amount' => number_format($due, 2)]) : __('No dues') }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
@endsection
