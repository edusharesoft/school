@extends($layout)
@section('title', __('Quizzes'))

@section('content')
<x-page-header :title="__('Quizzes')" :breadcrumbs="[__('Learning') => null, __('Quizzes') => null]">
    @can('quizzes.create')
        <x-slot:actions><a href="{{ route($routePrefix.'learning.quizzes.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New quiz') }}</a></x-slot:actions>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(['draft' => __('Draft'), 'published' => __('Published'), 'closed' => __('Closed')] as $k => $label)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach
            </select></div>
            <div class="col-md-3"><select name="class_id" class="form-select form-select-sm" aria-label="{{ __('Class') }}">
                <option value="">{{ __('All classes') }}</option>
                @foreach($classes as $id => $name)<option value="{{ $id }}" @selected((int) request('class_id') === $id)>{{ $name }}</option>@endforeach
            </select></div>
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search title') }}" aria-label="{{ __('Search title') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($quizzes->isEmpty())
        <x-empty-state icon="patch-question" :title="__('No quizzes yet')" :message="__('Create a quiz with multiple-choice and true/false questions that is graded automatically.')" />
    @else
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>{{ __('Quiz') }}</th><th>{{ __('Class') }}</th><th class="text-center">{{ __('Questions') }}</th><th class="text-center">{{ __('Taken') }}</th><th>{{ __('Closes') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($quizzes as $q)
                <tr>
                    <td class="fw-semibold"><a href="{{ route($routePrefix.'learning.quizzes.show', $q) }}" class="text-decoration-none">{{ $q->title }}</a><div class="text-body-secondary fw-normal">{{ $q->subject?->displayName() ?? '—' }} · {{ $q->creator?->name }}</div></td>
                    <td>{{ $q->schoolClass?->name }}@if($q->section) — {{ $q->section->name }}@endif</td>
                    <td class="text-center">{{ $q->questions_count }}</td>
                    <td class="text-center">{{ $q->finished_attempts_count }}</td>
                    <td>{{ $q->available_until?->format('d M Y H:i') ?? '—' }}</td>
                    <td><span class="badge text-bg-{{ ['draft' => 'secondary', 'published' => 'success', 'closed' => 'dark'][$q->status] }}">{{ __(ucfirst($q->status)) }}</span></td>
                    <td class="text-end"><a href="{{ route($routePrefix.'learning.quizzes.show', $q) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Open') }}"><i class="bi bi-eye"></i></a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="card-footer bg-transparent">{{ $quizzes->links() }}</div>
    @endif
</div>
@endsection
