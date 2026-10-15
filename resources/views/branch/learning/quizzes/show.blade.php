@extends($layout)
@section('title', $quiz->title)

@section('content')
<x-page-header :title="$quiz->title" :breadcrumbs="[__('Quizzes') => route($routePrefix.'learning.quizzes.index'), $quiz->title => null]">
    @if($canManage)
        <x-slot:actions>
            @if($quiz->status === 'draft')<form method="POST" action="{{ route($routePrefix.'learning.quizzes.publish', $quiz) }}" class="d-inline" onsubmit="return confirm(@js(__('Publish now? Students and guardians will be notified.')))">@csrf<button class="btn btn-success btn-sm"><i class="bi bi-send me-1"></i>{{ __('Publish') }}</button></form>@endif
            @if($quiz->status === 'published')<form method="POST" action="{{ route($routePrefix.'learning.quizzes.close', $quiz) }}" class="d-inline" onsubmit="return confirm(@js(__('Close this quiz? Students can no longer start it.')))">@csrf<button class="btn btn-outline-dark btn-sm">{{ __('Close quiz') }}</button></form>@endif
            @if($quiz->status === 'closed')<form method="POST" action="{{ route($routePrefix.'learning.quizzes.reopen', $quiz) }}" class="d-inline">@csrf<button class="btn btn-outline-success btn-sm">{{ __('Reopen') }}</button></form>@endif
            <a href="{{ route($routePrefix.'learning.quizzes.edit', $quiz) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
        </x-slot:actions>
    @endif
</x-page-header>

@if($quiz->status === 'draft')<div class="alert alert-warning small">{{ __('This is a draft. Students see it only after you publish it.') }}</div>@endif

<div class="row g-3 mb-3">
    <div class="col-lg-5"><div class="card h-100"><div class="card-body small">
        <dl class="row mb-0">
            <dt class="col-5">{{ __('Class') }}</dt><dd class="col-7">{{ $quiz->schoolClass?->name }}@if($quiz->section) — {{ $quiz->section->name }}@endif</dd>
            <dt class="col-5">{{ __('Subject') }}</dt><dd class="col-7">{{ $quiz->subject?->displayName() ?? '—' }}</dd>
            <dt class="col-5">{{ __('Questions') }}</dt><dd class="col-7">{{ $quiz->questions->count() }} · {{ __('Total marks') }} {{ $quiz->questions->sum('marks') + 0 }}</dd>
            <dt class="col-5">{{ __('Time limit') }}</dt><dd class="col-7">{{ $quiz->time_limit_minutes ? $quiz->time_limit_minutes.' '.__('minutes') : __('No time limit') }}</dd>
            <dt class="col-5">{{ __('Attempts allowed') }}</dt><dd class="col-7">{{ $quiz->max_attempts }}</dd>
            <dt class="col-5">{{ __('Opens') }}</dt><dd class="col-7">{{ $quiz->available_from?->format('d M Y H:i') ?? __('Immediately') }}</dd>
            <dt class="col-5">{{ __('Closes') }}</dt><dd class="col-7">{{ $quiz->available_until?->format('d M Y H:i') ?? '—' }}</dd>
            <dt class="col-5">{{ __('Pass mark') }}</dt><dd class="col-7">{{ $quiz->pass_percent !== null ? ($quiz->pass_percent + 0).'%' : '—' }}</dd>
            <dt class="col-5">{{ __('Show correct answers') }}</dt><dd class="col-7">{{ ['never' => __('Never'), 'after_submit' => __('After each attempt'), 'after_close' => __('After the quiz is closed')][$quiz->show_answers] }}</dd>
            <dt class="col-5">{{ __('State') }}</dt><dd class="col-7"><span class="badge text-bg-{{ ['draft' => 'secondary', 'published' => 'success', 'closed' => 'dark'][$quiz->status] }}">{{ __(ucfirst($quiz->status)) }}</span></dd>
        </dl>
    </div></div></div>
    @if($results)
    <div class="col-lg-7"><div class="row g-3 h-100">
        @foreach([[__('Students who took it'), $results['taken'].' / '.$results['rows']->count()], [__('Average score'), $results['average'] !== null ? $results['average'].'%' : '—']] as [$l, $v])
            <div class="col-6"><div class="card h-100"><div class="card-body text-center"><div class="text-body-secondary small">{{ $l }}</div><div class="h3 mb-0">{{ $v }}</div></div></div></div>
        @endforeach
    </div></div>
    @endif
</div>

@if($results)
<div class="card mb-3">
    <div class="card-header"><strong>{{ __('Results') }}</strong> <span class="small text-body-secondary">{{ __('Best attempt of each student') }}</span></div>
    @if($results['rows']->isEmpty())
        <div class="card-body text-body-secondary">{{ __('No students are enrolled in this class yet.') }}</div>
    @else
    <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
        <thead><tr><th>{{ __('Roll') }}</th><th>{{ __('Student') }}</th><th class="text-center">{{ __('Attempts') }}</th><th class="text-end">{{ __('Score') }}</th><th class="text-end">%</th><th>{{ __('Result') }}</th></tr></thead>
        <tbody>
        @foreach($results['rows'] as $r)
            @php($b = $r['best'])
            <tr>
                <td>{{ $r['student']->currentEnrollment?->roll_no }}</td><td>{{ $r['student']->name }}</td>
                <td class="text-center">{{ $r['attempts'] ?: '—' }}</td>
                <td class="text-end">{{ $b ? ($b->score + 0).' / '.($b->max_score + 0) : '—' }}</td>
                <td class="text-end">{{ $b ? ($b->percent + 0).'%' : '—' }}</td>
                <td>@if(! $b)<span class="badge text-bg-light border">{{ __('Not taken') }}</span>
                    @elseif($quiz->pass_percent !== null)<span class="badge text-bg-{{ (float) $b->percent >= (float) $quiz->pass_percent ? 'success' : 'danger' }}">{{ (float) $b->percent >= (float) $quiz->pass_percent ? __('Passed') : __('Failed') }}</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</div>
@endif

<div class="card">
    <div class="card-header"><strong>{{ __('Questions') }}</strong>@if($locked)<span class="small text-body-secondary ms-2">{{ __('Locked: students have started this quiz.') }}</span>@endif</div>
    <div class="card-body">
        @php($stats = $results ? $results['questions']->keyBy(fn ($r) => $r['question']->id) : collect())
        @foreach($quiz->questions as $i => $q)
            <div class="mb-3 {{ ! $loop->last ? 'pb-3 border-bottom' : '' }}">
                <div class="d-flex justify-content-between gap-2">
                    <div class="fw-semibold">{{ $i + 1 }}. {{ $q->text }}</div>
                    <div class="small text-body-secondary text-nowrap">{{ $q->marks + 0 }} {{ __('marks') }}@if($stats->has($q->id) && $stats[$q->id]['total'] > 0) · <span title="{{ __('Students who got it right') }}">{{ $stats[$q->id]['correct'] }}/{{ $stats[$q->id]['total'] }}</span>@endif</div>
                </div>
                <ul class="list-unstyled small mb-0 mt-1">
                    @foreach($q->options as $oi => $opt)
                        <li class="{{ in_array($oi, $q->correct, true) ? 'text-success fw-semibold' : '' }}"><i class="bi {{ in_array($oi, $q->correct, true) ? 'bi-check-circle-fill' : 'bi-circle' }} me-1"></i>{{ $q->type === 'true_false' ? __($opt) : $opt }}</li>
                    @endforeach
                </ul>
                @if($q->explanation)<div class="small text-body-secondary mt-1"><i class="bi bi-info-circle me-1"></i>{{ $q->explanation }}</div>@endif
            </div>
        @endforeach
    </div>
</div>
@endsection
