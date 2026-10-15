@extends($layout)
@section('title', __('Homework & assignments'))

@section('content')
<x-page-header :title="__('Homework & assignments')" :breadcrumbs="[__('Learning') => null, __('Homework & assignments') => null]">
    <x-slot:actions>
        <a href="{{ route($routePrefix.'learning.progress.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-graph-up me-1"></i>{{ __('Learning progress') }}</a>
        @foreach(['homework' => __('New homework'), 'assignment' => __('New assignment'), 'project' => __('New project')] as $k => $label)
            @if(in_array($k, $kinds, true) && auth()->user()->can(($k === 'homework' ? 'homework' : 'assignments').'.create'))
                <a href="{{ route($routePrefix.'learning.assignments.create', ['kind' => $k]) }}" class="btn {{ $k === 'homework' ? 'btn-brand' : 'btn-outline-primary' }} btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ $label }}</a>
            @endif
        @endforeach
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2"><select name="kind" class="form-select form-select-sm" aria-label="{{ __('Type') }}">
                <option value="">{{ __('All types') }}</option>
                @foreach($kinds as $k)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ __(ucfirst($k)) }}</option>@endforeach
            </select></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(['draft' => __('Draft'), 'published' => __('Published'), 'closed' => __('Closed')] as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select></div>
            <div class="col-md-3"><select name="class_id" class="form-select form-select-sm" aria-label="{{ __('Class') }}">
                <option value="">{{ __('All classes') }}</option>
                @foreach($classes as $id => $name)<option value="{{ $id }}" @selected((int) request('class_id') === $id)>{{ $name }}</option>@endforeach
            </select></div>
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search title') }}" aria-label="{{ __('Search title') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($assignments->isEmpty())
        <x-empty-state icon="journal-check" :title="__('Nothing here yet')" :message="__('Create homework or an assignment for one of your classes.')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Class') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Handed in') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach($assignments as $a)
                    <tr>
                        <td><a href="{{ route($routePrefix.'learning.assignments.show', $a) }}" class="fw-semibold text-decoration-none">{{ $a->title }}</a>
                            <div class="text-body-secondary">{{ $a->kindLabel() }}@if($a->creator) · {{ $a->creator->name }}@endif</div></td>
                        <td>{{ $a->schoolClass?->name }}@if($a->section) — {{ $a->section->name }}@endif</td>
                        <td>{{ $a->subject?->displayName() ?? '—' }}</td>
                        <td @class(['text-danger' => $a->status === 'published' && $a->isPastDue()])>{{ $a->due_at->format('d M Y H:i') }}</td>
                        <td class="text-end">@if($a->status === 'draft')—@else{{ bn_digits($a->submitted_count) }}@if($audiences[$a->id] !== null) / {{ bn_digits($audiences[$a->id]) }}@endif @if($a->evaluated_count)<span class="text-body-secondary">({{ bn_digits($a->evaluated_count) }} {{ __('evaluated') }})</span>@endif @endif</td>
                        <td><span class="badge text-bg-{{ ['draft' => 'secondary', 'published' => 'success', 'closed' => 'dark'][$a->status] }}">{{ __(ucfirst($a->status)) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent">{{ $assignments->links() }}</div>
    @endif
</div>
@endsection
