@extends('layouts.portal')
@section('title', $quiz->title)

@section('content')
<x-page-header :title="$quiz->title" :breadcrumbs="[__('Quizzes') => route('portal.student.quizzes'), $quiz->title => null]" />

<div class="row g-3 mb-3" style="max-width: 860px">
    <div class="col-6 col-md-3"><div class="panel panel-body text-center"><div class="text-body-secondary small">{{ __('Score') }}</div><div class="h4 mb-0">{{ ($attempt->score + 0).' / '.($attempt->max_score + 0) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="panel panel-body text-center"><div class="text-body-secondary small">{{ __('Percent') }}</div><div class="h4 mb-0 text-{{ $passed === null ? 'primary' : ($passed ? 'success' : 'danger') }}">{{ $attempt->percent + 0 }}%</div></div></div>
    <div class="col-6 col-md-3"><div class="panel panel-body text-center"><div class="text-body-secondary small">{{ __('Attempt') }}</div><div class="h4 mb-0">{{ $attempt->attempt_no }} / {{ $quiz->max_attempts }}</div></div></div>
    <div class="col-6 col-md-3"><div class="panel panel-body text-center"><div class="text-body-secondary small">{{ __('Result') }}</div>
        <div class="h5 mb-0">@if($passed === null)—@else<span class="badge text-bg-{{ $passed ? 'success' : 'danger' }}">{{ $passed ? __('Passed') : __('Failed') }}</span>@endif</div></div></div>
</div>
@if($attempt->timed_out)<div class="alert alert-warning small" style="max-width: 860px">{{ __('Time ran out — your saved answers were handed in.') }}</div>@endif

<div class="d-flex gap-2 mb-3">
    <a href="{{ route('portal.student.quizzes.show', $quiz) }}" class="btn btn-outline-secondary btn-sm">{{ __('Back to the quiz') }}</a>
    @if($canRetry)<form method="POST" action="{{ route('portal.student.quizzes.start', $quiz) }}">@csrf<button class="btn btn-success btn-sm">{{ __('Try again') }}</button></form>@endif
</div>

@if($reveal)
    <div style="max-width: 860px">
    @foreach($review as $i => $r)
        @php($q = $r['question'])
        <div class="dp {{ $r['right'] ? 'green' : 'red' }} mb-3">
            <div class="dp-head"><h2>{{ $i + 1 }}. {{ $q->text }}</h2><span class="sub">{{ $r['right'] ? $q->marks + 0 : 0 }} / {{ $q->marks + 0 }}</span></div>
            <div class="dp-body small">
                @foreach($q->options as $oi => $opt)
                    @php($isCorrect = in_array($oi, $q->correct, true))
                    @php($picked = in_array($oi, $r['given'], true))
                    <div class="py-1 {{ $isCorrect ? 'text-success fw-semibold' : ($picked ? 'text-danger' : '') }}">
                        <i class="bi {{ $isCorrect ? 'bi-check-circle-fill' : ($picked ? 'bi-x-circle-fill' : 'bi-circle') }} me-1"></i>{{ $q->type === 'true_false' ? __($opt) : $opt }}
                        @if($picked)<span class="badge text-bg-light border ms-1">{{ __('Your answer') }}</span>@endif
                    </div>
                @endforeach
                @if($r['given'] === [])<div class="text-body-secondary">{{ __('Not answered') }}</div>@endif
                @if($q->explanation)<div class="text-body-secondary mt-1"><i class="bi bi-info-circle me-1"></i>{{ $q->explanation }}</div>@endif
            </div>
        </div>
    @endforeach
    </div>
@else
    <div class="alert alert-info small" style="max-width: 860px">{{ $quiz->show_answers === 'never' ? __('The correct answers are not shown for this quiz.') : __('The correct answers will be shown after the quiz is closed.') }}</div>
@endif
@endsection
