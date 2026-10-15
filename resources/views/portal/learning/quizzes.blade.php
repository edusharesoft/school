@extends('layouts.portal')
@section('title', __('Quizzes'))

@section('content')
<x-page-header :title="__('Quizzes')" :breadcrumbs="$mode === 'parent' ? [__('My children') => route('portal.parent'), $student->name => null] : [__('Learning') => null]" />

<div class="dp blue">
    <div class="dp-head"><h2><i class="fa-solid fa-circle-question me-1"></i> {{ __('Quizzes') }}</h2><span class="sub">{{ bn_digits($rows->count()) }}</span></div>
    <div class="dp-body p-0">
        @if($rows->isEmpty())
            <p class="text-body-secondary p-3 mb-0">{{ __('No quizzes have been given yet.') }}</p>
        @else
        <div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Quiz') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Closes') }}</th><th class="text-end">{{ __('Best score') }}</th><th class="text-end">{{ $mode === 'student' ? '' : __('Attempts') }}</th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                @php($q = $r['quiz'])
                <tr>
                    <td class="fw-semibold">@if($mode === 'student')<a href="{{ route('portal.student.quizzes.show', $q) }}" class="text-decoration-none">{{ $q->title }}</a>@else{{ $q->title }}@endif
                        <div class="text-body-secondary fw-normal">{{ $q->questions_count }} {{ __('questions') }}@if($q->time_limit_minutes) · {{ $q->time_limit_minutes }} {{ __('minutes') }}@endif</div></td>
                    <td>{{ $q->subject?->displayName() ?? '—' }}</td>
                    <td>{{ $q->available_until?->format('d M Y H:i') ?? '—' }}@if($q->status === 'closed') <span class="badge text-bg-dark">{{ __('Closed') }}</span>@endif</td>
                    <td class="text-end">@if($r['best'])<strong>{{ ($r['best']->percent + 0) }}%</strong> <span class="text-body-secondary">({{ $r['best']->score + 0 }}/{{ $r['best']->max_score + 0 }})</span>
                        @if($q->pass_percent !== null)<span class="badge text-bg-{{ (float) $r['best']->percent >= (float) $q->pass_percent ? 'success' : 'danger' }}">{{ (float) $r['best']->percent >= (float) $q->pass_percent ? __('Passed') : __('Failed') }}</span>@endif
                        @else<span class="text-body-secondary">—</span>@endif</td>
                    <td class="text-end">@if($mode === 'student')
                            @if($r['blocker'] === null)<a href="{{ route('portal.student.quizzes.show', $q) }}" class="btn btn-sm btn-success">{{ $r['attempts']->isEmpty() ? __('Start') : __('Try again') }}</a>
                            @else<a href="{{ route('portal.student.quizzes.show', $q) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a>@endif
                        @else{{ $r['attempts']->count() }}@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @endif
    </div>
</div>
@endsection
