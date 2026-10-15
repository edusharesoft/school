@extends('layouts.branch')
@section('title', __('Absence alerts'))

@section('content')
<x-page-header :title="__('Consecutive absence alerts')" :breadcrumbs="[__('Attendance') => route('branch.attendance.students.index'), __('Absence alerts') => null]" />

<div class="alert alert-light border small">
    <i class="fa-solid fa-circle-info text-primary me-1"></i>
    {{ __('Students absent on :n or more consecutive register days are listed here automatically (checked every afternoon and evening). Guardian SMS / e-mail for these alerts is sent by the notification hub once it is configured (Part 5).', ['n' => bn_digits(setting('attendance', 'absence_alert_days', 3))]) }}
</div>

<div class="card">
    <div class="card-body border-bottom d-flex gap-2">
        <a href="{{ route('branch.attendance.alerts') }}" @class(['btn btn-sm', 'btn-brand' => request('status') !== 'all', 'btn-outline-secondary' => request('status') === 'all'])>{{ __('Open') }}</a>
        <a href="{{ route('branch.attendance.alerts', ['status' => 'all']) }}" @class(['btn btn-sm', 'btn-brand' => request('status') === 'all', 'btn-outline-secondary' => request('status') !== 'all'])>{{ __('All') }}</a>
    </div>
    @if($alerts->isEmpty())
        <x-empty-state icon="emoji-smile" :title="__('No absence alerts')" />
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Absent days') }}</th><th>{{ __('From – to') }}</th><th>{{ __('Guardian') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($alerts as $a)
                    @php($g = $a->student?->primaryGuardian())
                    <tr>
                        <td><div class="fw-medium">{{ $a->student?->name }}</div><code class="small">{{ $a->student?->student_code }}</code></td>
                        <td>{{ $a->section?->schoolClass?->name }} {{ $a->section?->name }}</td>
                        <td><span class="badge text-bg-danger fs-6">{{ bn_digits($a->streak_days) }}</span></td>
                        <td class="small text-nowrap">{{ $a->first_absent_on->format('d M') }} – {{ $a->last_absent_on->format('d M Y') }}</td>
                        <td class="small">@can('guardians.view'){{ $g?->name }}<div>{{ $g?->phone }}</div>@else — @endcan</td>
                        <td>@if($a->status === 'open')<span class="badge text-bg-warning">{{ __('Open') }}</span>@else<span class="badge text-bg-success">{{ __('Followed up') }}</span><div class="small text-body-secondary">{{ $a->acknowledger?->name }}</div>@endif</td>
                        <td class="text-end">
                            @if($a->status === 'open' && (auth()->user()->can('student-attendance.correct') || auth()->user()->can('student-attendance.approve-correction')))
                                <form method="POST" action="{{ route('branch.attendance.alerts.acknowledge', $a) }}">@csrf
                                    <button class="btn btn-sm btn-outline-success"><i class="bi bi-check2 me-1"></i>{{ __('Followed up') }}</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $alerts->links() }}</div>
    @endif
</div>
@endsection
