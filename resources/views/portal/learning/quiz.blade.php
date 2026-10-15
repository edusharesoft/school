@extends('layouts.portal')
@section('title', $quiz->title)

@section('content')
<x-page-header :title="$quiz->title" :breadcrumbs="[__('Quizzes') => route('portal.student.quizzes'), $quiz->title => null]" />

<div class="row g-3">
    <div class="col-lg-6"><div class="dp blue h-100">
        <div class="dp-head"><h2><i class="fa-solid fa-circle-question me-1"></i> {{ __('About this quiz') }}</h2></div>
        <div class="dp-body small">
            @if($quiz->instructions)<p>{{ $quiz->instructions }}</p>@endif
            <dl class="row mb-3">
                <dt class="col-5">{{ __('Subject') }}</dt><dd class="col-7">{{ $quiz->subject?->displayName() ?? '—' }}</dd>
                <dt class="col-5">{{ __('Questions') }}</dt><dd class="col-7">{{ $quiz->questions_count }} · {{ $total + 0 }} {{ __('marks') }}</dd>
                <dt class="col-5">{{ __('Time limit') }}</dt><dd class="col-7">{{ $quiz->time_limit_minutes ? $quiz->time_limit_minutes.' '.__('minutes') : __('No time limit') }}</dd>
                <dt class="col-5">{{ __('Attempts allowed') }}</dt><dd class="col-7">{{ $quiz->max_attempts }}</dd>
                <dt class="col-5">{{ __('Closes') }}</dt><dd class="col-7">{{ $quiz->available_until?->format('d M Y H:i') ?? '—' }}</dd>
                @if($quiz->pass_percent !== null)<dt class="col-5">{{ __('Pass mark') }}</dt><dd class="col-7">{{ $quiz->pass_percent + 0 }}%</dd>@endif
            </dl>
            @if($open)
                <a href="{{ route('portal.student.quizzes.attempt', $open) }}" class="btn btn-warning"><i class="bi bi-play-fill me-1"></i>{{ __('Continue your attempt') }}</a>
            @elseif($blocker)
                <div class="alert alert-secondary mb-0">{{ $blocker }}</div>
            @else
                <form method="POST" action="{{ route('portal.student.quizzes.start', $quiz) }}" onsubmit="return confirm(@js($quiz->time_limit_minutes ? __('Start now? The :n-minute timer begins immediately.', ['n' => $quiz->time_limit_minutes]) : __('Start the quiz now?')))">@csrf
                    <button class="btn btn-success"><i class="bi bi-play-fill me-1"></i>{{ $attempts->isEmpty() ? __('Start the quiz') : __('Try again') }}</button></form>
            @endif
            @error('quiz')<div class="text-danger mt-2">{{ $message }}</div>@enderror
        </div>
    </div></div>
    <div class="col-lg-6"><div class="dp green h-100">
        <div class="dp-head"><h2><i class="fa-solid fa-clock-rotate-left me-1"></i> {{ __('Your attempts') }}</h2></div>
        <div class="dp-body p-0">
            @if($attempts->isEmpty())<p class="text-body-secondary p-3 mb-0">{{ __('You have not taken this quiz yet.') }}</p>
            @else
            <table class="table align-middle mb-0 small"><thead><tr><th>#</th><th>{{ __('Date') }}</th><th class="text-end">{{ __('Score') }}</th><th></th></tr></thead><tbody>
                @foreach($attempts as $a)
                    <tr><td>{{ $a->attempt_no }}</td><td>{{ $a->started_at->format('d M H:i') }}</td>
                        <td class="text-end">@if($a->isFinished()){{ ($a->score + 0).' / '.($a->max_score + 0) }} ({{ $a->percent + 0 }}%)@else<span class="badge text-bg-warning">{{ __('In progress') }}</span>@endif</td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route($a->isFinished() ? 'portal.student.quizzes.result' : 'portal.student.quizzes.attempt', $a) }}">{{ $a->isFinished() ? __('Result') : __('Continue') }}</a></td></tr>
                @endforeach
            </tbody></table>
            @endif
        </div>
    </div></div>
</div>
@endsection
